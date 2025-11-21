<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Bitacora;
use App\Helpers\CodigoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class AutenticacionEstudianteController extends Controller
{
    /**
     * Registrar un nuevo estudiante
     */
    public function registrar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ci' => 'required|string|max:20|unique:estudiante,ci',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'required|string|max:20',
            'fecha_nacimiento' => 'required|date|before:-14 years',
            'direccion' => 'required|string|max:300',
            'provincia' => 'required|string|max:100',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            'password_confirmation' => 'required|same:password'
        ], [
            'ci.unique' => 'El CI ya está registrado en el sistema',
            'fecha_nacimiento.before' => 'Debe ser mayor de 14 años',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula y un número'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generar código único de 5 dígitos para el estudiante
            $registroEstudiante = CodigoHelper::generarCodigoEstudiante();

            // Con PostgreSQL INHERITS, Estudiante hereda de Persona
            // Crear Estudiante directamente (que incluye los campos de Persona)
            $estudiante = Estudiante::create([
                'id' => null, // Se generará automáticamente
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'celular' => $request->celular,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'direccion' => $request->direccion,
                'provincia' => $request->provincia,
                'registro_estudiante' => $registroEstudiante,
                'estado_id' => 1 // Estado inicial
            ]);

            Log::info('Estudiante creado', [
                'id' => $estudiante->id,
                'registro_estudiante' => $estudiante->registro_estudiante
            ]);

            // Obtener el rol ESTUDIANTE
            $rolEstudiante = \App\Models\Rol::where('nombre_rol', 'ESTUDIANTE')->first();

            if (!$rolEstudiante) {
                Log::error('Rol ESTUDIANTE no encontrado en la base de datos');
                return response()->json([
                    'success' => false,
                    'message' => 'Error de configuración: Rol ESTUDIANTE no encontrado'
                ], 500);
            }

            // Create usuario with password
            // Estudiante hereda de Persona, usa el mismo id
            $usuario = \App\Models\Usuario::create([
                'email' => $request->ci . '@estudiante.com', // Usar CI como email temporal
                'password' => Hash::make($request->password),
                'persona_id' => $estudiante->id, // Estudiante hereda de Persona, usa el mismo id
                'rol_id' => $rolEstudiante->rol_id // Asignar rol ESTUDIANTE
            ]);

            Log::info('Usuario creado', [
                'usuario_id' => $usuario->usuario_id,
                'rol_id' => $usuario->rol_id,
                'rol_nombre' => $rolEstudiante->nombre_rol
            ]);

            // Log to Bitacora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Estudiante',
                'cod_tabla' => $estudiante->registro_estudiante,
                'transaccion' => 'REGISTRO_NUEVO_ESTUDIANTE',
                'usuario_id' => $usuario->usuario_id
            ]);

            // Generar token JWT automáticamente después del registro
            $token = JWTAuth::fromUser($estudiante);

            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso. Bienvenido al sistema',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $estudiante->registro_estudiante,
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'nombre_completo' => trim($estudiante->nombre . ' ' . $estudiante->apellido),
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'Estado_id' => $estudiante->Estado_id,
                    'provincia' => $estudiante->provincia,
                    'celular' => $estudiante->celular,
                    'email' => $usuario->email,
                    'rol' => 'ESTUDIANTE',
                    'rol_id' => $usuario->rol_id
                ],
                'data' => [
                    'registro_estudiante' => $estudiante->registro_estudiante
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Iniciar sesión de estudiante
     */
    public function iniciarSesion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ci' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Buscar estudiante por CI
            $estudiante = Estudiante::where('ci', $request->ci)->first();

            Log::info('Login attempt', [
                'ci' => $request->ci,
                'estudiante_found' => $estudiante ? true : false,
                'estudiante_id' => $estudiante ? $estudiante->registro_estudiante : null,
                'id' => $estudiante ? $estudiante->id : null // Estudiante hereda de Persona, usa el mismo id
            ]);

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'CI o contraseña incorrectos'
                ], 401);
            }

            // Buscar el usuario asociado a la persona del estudiante
            $usuario = $estudiante->usuario;

            Log::info('Usuario found', [
                'usuario_found' => $usuario ? true : false,
                'usuario_id' => $usuario ? $usuario->usuario_id : null,
                'has_password' => $usuario ? !empty($usuario->password) : false,
                'rol_id' => $usuario ? $usuario->rol_id : null
            ]);

            if (!$usuario) {
                Log::warning('Estudiante sin usuario asociado', [
                    'estudiante_id' => $estudiante->registro_estudiante,
                    'id' => $estudiante->id // Estudiante hereda de Persona, usa el mismo id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'CI o contraseña incorrectos'
                ], 401);
            }

            // Verificar contraseña
            if (!Hash::check($request->password, $usuario->password)) {
                Log::warning('Contraseña incorrecta', [
                    'usuario_id' => $usuario->usuario_id,
                    'ci' => $request->ci
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'CI o contraseña incorrectos'
                ], 401);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($estudiante);

            // Log to Bitacora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Estudiante',
                'cod_tabla' => $estudiante->registro_estudiante,
                'transaccion' => 'LOGIN_ESTUDIANTE',
                'usuario_id' => $usuario->usuario_id
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $estudiante->registro_estudiante,
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'nombre_completo' => trim($estudiante->nombre . ' ' . $estudiante->apellido),
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'Estado_id' => $estudiante->Estado_id,
                    'provincia' => $estudiante->provincia,
                    'celular' => $estudiante->celular,
                    'email' => $usuario->email,
                    'rol' => 'ESTUDIANTE',
                    'rol_id' => $usuario->rol_id
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos del usuario autenticado (estudiante, admin o docente)
     * Este método puede ser usado por cualquier tipo de usuario autenticado
     */
    public function obtenerPerfil()
    {
        try {
            // Intentar obtener usuario desde auth:api primero
            $user = auth('api')->user();

            // Si no está en auth:api, intentar desde auth_user (agregado por RoleMiddleware)
            if (!$user) {
                $user = request()->auth_user;
            }

            // Si aún no está, intentar obtenerlo desde el token directamente
            if (!$user) {
                try {
                    $payload = JWTAuth::parseToken()->getPayload();
                    $rol = $payload->get('rol');

                    if ($rol === 'ESTUDIANTE') {
                        $registroEstudiante = $payload->get('sub');
                        $user = Estudiante::where('registro_estudiante', $registroEstudiante)->first();
                    } else {
                        // Para admin/docente, el sub es usuario_id
                        $usuarioId = $payload->get('sub');
                        $user = \App\Models\Usuario::with('rol', 'persona')->find($usuarioId);
                    }
                } catch (\Exception $e) {
                    Log::error('Error obteniendo usuario desde token en obtenerPerfil', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Si es Estudiante
            if ($user instanceof Estudiante) {
                try {
                    $usuario = $user->usuario;
                    $fechaNacimiento = $user->fecha_nacimiento;

                    // Formatear fecha_nacimiento si es un objeto Carbon
                    if ($fechaNacimiento && method_exists($fechaNacimiento, 'format')) {
                        $fechaNacimiento = $fechaNacimiento->format('Y-m-d');
                    } elseif ($fechaNacimiento && is_string($fechaNacimiento)) {
                        // Ya es string, mantenerlo
                    } else {
                        $fechaNacimiento = null;
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $user->registro_estudiante,
                            'ci' => $user->ci,
                            'nombre' => $user->nombre,
                            'apellido' => $user->apellido,
                            'nombre_completo' => trim($user->nombre . ' ' . $user->apellido),
                            'celular' => $user->celular,
                            'fecha_nacimiento' => $fechaNacimiento,
                            'direccion' => $user->direccion,
                            'registro_estudiante' => $user->registro_estudiante,
                            'provincia' => $user->provincia,
                            'Estado_id' => $user->Estado_id,
                            'fotografia' => $user->fotografia,
                            'email' => $usuario ? $usuario->email : null,
                            'rol' => 'ESTUDIANTE',
                            'rol_id' => $usuario ? $usuario->rol_id : null
                        ]
                    ], 200);
                } catch (\Exception $e) {
                    Log::error('Error procesando datos de estudiante en obtenerPerfil', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            // Si es Usuario (Admin o Docente)
            if ($user instanceof \App\Models\Usuario) {
                try {
                    // Cargar relaciones si no están cargadas
                    if (!$user->relationLoaded('rol')) {
                        $user->load('rol');
                    }
                    if (!$user->relationLoaded('persona')) {
                        $user->load('persona');
                    }

                    $rolNombre = $user->rol ? $user->rol->nombre_rol : 'ADMIN';

                    // Cargar permisos del rol
                    $permisos = [];
                    if ($user->rol) {
                        if (!$user->rol->relationLoaded('permisos')) {
                            $user->rol->load('permisos');
                        }
                        $permisos = $user->rol->permisos->map(function($permiso) {
                            return [
                                'id' => $permiso->permiso_id,
                                'nombre_permiso' => $permiso->nombre_permiso,
                                'modulo' => $permiso->modulo,
                                'accion' => $permiso->accion
                            ];
                        })->toArray();
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $user->usuario_id,
                            'email' => $user->email,
                            'nombre' => $user->persona->nombre ?? 'Admin',
                            'apellido' => $user->persona->apellido ?? 'Sistema',
                            'nombre_completo' => trim(($user->persona->nombre ?? 'Admin') . ' ' . ($user->persona->apellido ?? 'Sistema')),
                            'ci' => $user->persona->ci ?? null,
                            'rol' => $rolNombre,
                            'rol_id' => $user->rol_id,
                            'permisos' => $permisos
                        ]
                    ], 200);
                } catch (\Exception $e) {
                    Log::error('Error procesando datos de usuario en obtenerPerfil', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Tipo de usuario no reconocido'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error obteniendo perfil', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cerrar sesión del estudiante
     */
    public function cerrarSesion()
    {
        try {
            // Invalidar el token JWT
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            // Si el token ya es inválido o no existe, igualmente retornar éxito
            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
        }
    }

    /**
     * Refrescar token de autenticación
     */
    public function refrescarToken()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al refrescar token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
