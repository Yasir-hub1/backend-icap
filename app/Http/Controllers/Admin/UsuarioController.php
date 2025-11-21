<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Persona;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UsuarioController extends Controller
{
    /**
     * Listar usuarios con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $rolId = $request->get('rol_id');

            // Obtener IDs de roles ADMIN y DOCENTE (excluir ESTUDIANTE)
            $rolesPermitidos = Rol::whereIn('nombre_rol', ['ADMIN', 'DOCENTE'])
                                 ->where('activo', true)
                                 ->pluck('rol_id')
                                 ->toArray();

            $query = Usuario::with(['persona', 'rol'])
                           ->whereIn('rol_id', $rolesPermitidos);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('email', 'ILIKE', "%{$search}%")
                      ->orWhereHas('persona', function($personaQuery) use ($search) {
                          $personaQuery->where('nombre', 'ILIKE', "%{$search}%")
                                       ->orWhere('apellido', 'ILIKE', "%{$search}%")
                                       ->orWhere('ci', 'ILIKE', "%{$search}%");
                      });
                });
            }

            if ($rolId) {
                // Validar que el rol_id esté en los roles permitidos
                if (in_array($rolId, $rolesPermitidos)) {
                    $query->where('rol_id', $rolId);
                }
            }

            $usuarios = $query->orderBy('usuario_id', 'desc')
                             ->paginate($perPage);

            // Ocultar password en la respuesta
            $usuarios->getCollection()->transform(function ($usuario) {
                unset($usuario->password);
                return $usuario;
            });

            return response()->json([
                'success' => true,
                'data' => $usuarios,
                'message' => 'Usuarios obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            Log::error('Error al listar usuarios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener usuario por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $usuario = Usuario::with(['persona', 'rol.permisos'])->findOrFail($id);
            unset($usuario->password);

            // Verificar que el usuario no sea ESTUDIANTE
            if ($usuario->rol && $usuario->rol->nombre_rol === 'ESTUDIANTE') {
                return response()->json([
                    'success' => false,
                    'message' => 'Los usuarios estudiantes se gestionan desde el módulo de estudiantes'
                ], 403)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            return response()->json([
                'success' => true,
                'data' => $usuario,
                'message' => 'Usuario obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo usuario
     */
    public function crear(Request $request): JsonResponse
    {
        // Validar que el rol no sea ESTUDIANTE
        $rolEstudiante = Rol::where('nombre_rol', 'ESTUDIANTE')->first();
        if ($rolEstudiante && $request->rol_id == $rolEstudiante->rol_id) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede crear un usuario con rol ESTUDIANTE desde este módulo. Los estudiantes se registran desde el portal de estudiantes.',
                'errors' => ['rol_id' => ['El rol ESTUDIANTE no está permitido en este módulo']]
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        // Obtener IDs de roles permitidos (ADMIN y DOCENTE)
        $rolesPermitidos = Rol::whereIn('nombre_rol', ['ADMIN', 'DOCENTE'])
                             ->where('activo', true)
                             ->pluck('rol_id')
                             ->toArray();

        $validator = Validator::make($request->all(), [
            // Datos de persona
            'ci' => 'required|string|max:20|unique:persona,ci',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'nullable|string|max:20',
            'sexo' => 'nullable|string|in:M,F',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'direccion' => 'nullable|string|max:300',
            'fotografia' => 'nullable|string',

            // Datos de usuario
            'email' => 'required|email|max:100|unique:usuario,email',
            'password' => 'required|string|min:6|confirmed',
            'rol_id' => ['required', 'integer', 'exists:roles,rol_id', function($attribute, $value, $fail) use ($rolesPermitidos) {
                if (!in_array($value, $rolesPermitidos)) {
                    $fail('El rol seleccionado no está permitido. Solo se permiten roles ADMIN y DOCENTE.');
                }
            }]
        ], [
            'ci.required' => 'El CI es obligatorio',
            'ci.unique' => 'Este CI ya está registrado en el sistema',
            'ci.max' => 'El CI no puede tener más de 20 caracteres',
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres',
            'apellido.required' => 'El apellido es obligatorio',
            'apellido.max' => 'El apellido no puede tener más de 100 caracteres',
            'celular.max' => 'El celular no puede tener más de 20 caracteres',
            'sexo.in' => 'El sexo debe ser Masculino (M) o Femenino (F)',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'direccion.max' => 'La dirección no puede tener más de 300 caracteres',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado',
            'email.max' => 'El email no puede tener más de 100 caracteres',
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'rol_id.required' => 'El rol es obligatorio',
            'rol_id.exists' => 'El rol seleccionado no existe',
            'rol_id.integer' => 'El rol debe ser un número válido'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación. Por favor, revisa los campos marcados',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            // Crear persona
            $persona = Persona::create([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'celular' => $request->celular,
                'sexo' => $request->sexo,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'direccion' => $request->direccion,
                'fotografia' => $request->fotografia
            ]);

            // Crear usuario vinculado a la persona
            $usuario = Usuario::create([
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'persona_id' => $persona->id,
                'rol_id' => $request->rol_id
            ]);

            DB::commit();

            $usuario->load(['persona', 'rol']);
            unset($usuario->password);

            return response()->json([
                'success' => true,
                'data' => $usuario,
                'message' => 'Usuario creado exitosamente'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear usuario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar usuario
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        try {
            $usuario = Usuario::with('persona')->findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        // Validar que no se intente cambiar a rol ESTUDIANTE
        if ($request->has('rol_id')) {
            $rolEstudiante = Rol::where('nombre_rol', 'ESTUDIANTE')->first();
            if ($rolEstudiante && $request->rol_id == $rolEstudiante->rol_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede asignar el rol ESTUDIANTE desde este módulo. Los estudiantes se gestionan desde el portal de estudiantes.',
                    'errors' => ['rol_id' => ['El rol ESTUDIANTE no está permitido en este módulo']]
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }
        }

        // Obtener IDs de roles permitidos (ADMIN y DOCENTE)
        $rolesPermitidos = Rol::whereIn('nombre_rol', ['ADMIN', 'DOCENTE'])
                             ->where('activo', true)
                             ->pluck('rol_id')
                             ->toArray();

        $validator = Validator::make($request->all(), [
            // Datos de persona
            'ci' => 'sometimes|required|string|max:20|unique:persona,ci,' . $usuario->persona_id,
            'nombre' => 'sometimes|required|string|max:100',
            'apellido' => 'sometimes|required|string|max:100',
            'celular' => 'nullable|string|max:20',
            'sexo' => 'nullable|string|in:M,F',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'direccion' => 'nullable|string|max:300',
            'fotografia' => 'nullable|string',

            // Datos de usuario
            'email' => 'sometimes|required|email|max:100|unique:usuario,email,' . $id . ',usuario_id',
            'password' => ['sometimes', 'nullable', 'string', 'min:6', function($attribute, $value, $fail) use ($request) {
                // Solo validar si password no está vacío
                if (!empty(trim($value))) {
                    // Validar longitud mínima
                    if (strlen($value) < 6) {
                        $fail('La contraseña debe tener al menos 6 caracteres');
                    }
                    // Validar que password_confirmation coincida solo si password está presente
                    if ($request->has('password_confirmation') && $request->password_confirmation !== $value) {
                        $fail('Las contraseñas no coinciden');
                    }
                }
            }],
            'password_confirmation' => 'nullable|string',
            'rol_id' => ['sometimes', 'required', 'integer', 'exists:roles,rol_id', function($attribute, $value, $fail) use ($rolesPermitidos) {
                if (!in_array($value, $rolesPermitidos)) {
                    $fail('El rol seleccionado no está permitido. Solo se permiten roles ADMIN y DOCENTE.');
                }
            }]
        ], [
            'ci.required' => 'El CI es obligatorio',
            'ci.unique' => 'Este CI ya está registrado en el sistema',
            'ci.max' => 'El CI no puede tener más de 20 caracteres',
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres',
            'apellido.required' => 'El apellido es obligatorio',
            'apellido.max' => 'El apellido no puede tener más de 100 caracteres',
            'celular.max' => 'El celular no puede tener más de 20 caracteres',
            'sexo.in' => 'El sexo debe ser Masculino (M) o Femenino (F)',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'direccion.max' => 'La dirección no puede tener más de 300 caracteres',
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email debe ser válido',
            'email.unique' => 'Este email ya está registrado',
            'email.max' => 'El email no puede tener más de 100 caracteres',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'rol_id.required' => 'El rol es obligatorio',
            'rol_id.exists' => 'El rol seleccionado no existe',
            'rol_id.integer' => 'El rol debe ser un número válido'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación. Por favor, revisa los campos marcados',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            // Actualizar persona
            if ($usuario->persona) {
                $personaData = $request->only([
                    'ci', 'nombre', 'apellido', 'celular', 'sexo',
                    'fecha_nacimiento', 'direccion', 'fotografia'
                ]);
                $personaData = array_filter($personaData, function($value) {
                    return $value !== null;
                });
                $usuario->persona->update($personaData);
            }

            // Actualizar usuario
            $usuarioData = [];
            if ($request->has('email')) {
                $usuarioData['email'] = $request->email;
            }
            // Solo actualizar password si se proporciona y no está vacío
            // Si no se envía password o está vacío, se mantiene la contraseña actual
            if ($request->has('password') && !empty(trim($request->password))) {
                $usuarioData['password'] = Hash::make($request->password);
            }
            if ($request->has('rol_id')) {
                $usuarioData['rol_id'] = $request->rol_id;
            }

            if (!empty($usuarioData)) {
                $usuario->update($usuarioData);
            }

            DB::commit();

            $usuario->load(['persona', 'rol']);
            unset($usuario->password);

            return response()->json([
                'success' => true,
                'data' => $usuario,
                'message' => 'Usuario actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar usuario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar usuario (desactivar)
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $usuario = Usuario::findOrFail($id);

            // Verificar que el usuario no sea ESTUDIANTE
            if ($usuario->rol && $usuario->rol->nombre_rol === 'ESTUDIANTE') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar usuarios estudiantes desde este módulo. Los estudiantes se gestionan desde el módulo de estudiantes.'
                ], 403)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // No permitir eliminar el usuario actual
            if (auth('api')->id() == $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar su propio usuario'
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            // En lugar de eliminar, podríamos desactivar el usuario
            // Por ahora, eliminamos físicamente
            $usuario->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al eliminar usuario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener roles disponibles
     */
    public function obtenerRoles(): JsonResponse
    {
        try {
            // Solo obtener roles ADMIN y DOCENTE (excluir ESTUDIANTE)
            $roles = Rol::where('activo', true)
                       ->whereIn('nombre_rol', ['ADMIN', 'DOCENTE'])
                       ->orderBy('nombre_rol')
                       ->get(['rol_id', 'nombre_rol', 'descripcion']);

            return response()->json([
                'success' => true,
                'data' => $roles,
                'message' => 'Roles obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener roles. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}

