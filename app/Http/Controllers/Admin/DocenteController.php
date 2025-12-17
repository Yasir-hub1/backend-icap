<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Persona;
use App\Models\Grupo;
use App\Models\Usuario;
use App\Models\Rol;
use App\Helpers\CodigoHelper;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DocenteController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar docentes con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $especializacion = $request->get('especializacion', '');
            $sortBy = $request->get('sort_by', 'apellido');
            $sortDirection = $request->get('sort_direction', 'asc');

            // Validar dirección de ordenamiento
            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'asc';
            }

            // Validar columnas permitidas para ordenamiento
            $allowedSortColumns = ['nombre', 'apellido', 'ci', 'registro_docente', 'cargo', 'area_de_especializacion'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'apellido';
            }

            $query = Docente::withCount('grupos');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'ILIKE', "%{$search}%")
                      ->orWhere('apellido', 'ILIKE', "%{$search}%")
                      ->orWhere('ci', 'ILIKE', "%{$search}%")
                      ->orWhere('registro_docente', 'ILIKE', "%{$search}%");
                });
            }

            if ($especializacion) {
                $query->where('area_de_especializacion', 'ILIKE', "%{$especializacion}%");
            }

            // Si se ordena por apellido, agregar nombre como segundo criterio
            if ($sortBy === 'apellido') {
                $docentes = $query->orderBy($sortBy, $sortDirection)
                                 ->orderBy('nombre', 'asc')
                                 ->paginate($perPage);
            } else {
                $docentes = $query->orderBy($sortBy, $sortDirection)
                                 ->paginate($perPage);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $docentes->items(),
                    'current_page' => $docentes->currentPage(),
                    'per_page' => $docentes->perPage(),
                    'total' => $docentes->total(),
                    'last_page' => $docentes->lastPage(),
                    'from' => $docentes->firstItem(),
                    'to' => $docentes->lastItem()
                ],
                'message' => 'Docentes obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener docentes: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener docente por ID
     */
    public function obtener(string $registro): JsonResponse
    {
        try {
            // Convertir a entero y buscar por id
            $id = (int) $registro;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de docente inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Buscar por id
            $docente = Docente::where('id', $id)->first();

            if (!$docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Docente no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Cargar grupos usando la relación correcta (docente_id)
            $grupos = Grupo::where('docente_id', $docente->id)
                ->with(['programa', 'modulo', 'horarios'])
                ->get();

            // Asignar los grupos al docente manualmente
            $docente->setRelation('grupos', $grupos);

            return response()->json([
                'success' => true,
                'data' => $docente,
                'message' => 'Docente obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener docente: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener siguiente número de registro docente
     */
    public function siguienteRegistro(): JsonResponse
    {
        try {
            // Obtener todos los registros docentes que sean numéricos de 4 dígitos
            $registros = Docente::whereRaw("registro_docente ~ '^[0-9]{4}$'")
                ->pluck('registro_docente')
                ->map(function($registro) {
                    return (int) $registro;
                })
                ->sort()
                ->values();

            $siguienteNumero = 1;

            if ($registros->isNotEmpty()) {
                $ultimoNumero = $registros->last();
                $siguienteNumero = $ultimoNumero + 1;

                // Si excede 9999, buscar el primer número disponible desde 1
                if ($siguienteNumero > 9999) {
                    $numerosOcupados = $registros->toArray();
                    for ($i = 1; $i <= 9999; $i++) {
                        if (!in_array($i, $numerosOcupados)) {
                            $siguienteNumero = $i;
                            break;
                        }
                    }
                }
            }

            $siguienteRegistro = str_pad($siguienteNumero, 4, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'data' => [
                    'siguiente_registro' => $siguienteRegistro
                ],
                'message' => 'Siguiente registro obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener siguiente registro: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo docente
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            // Datos de Persona
            'ci' => 'required|string|max:20|unique:persona,ci',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'nullable|string|max:20|regex:/^[0-9]+$/',
            'sexo' => 'nullable|string|in:M,F',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'direccion' => 'nullable|string|max:255',
            'fotografia' => 'nullable|string|max:255',
            'usuario_id' => 'nullable|exists:usuario,id',
            // Datos de Usuario (requeridos para crear credenciales)
            'email' => 'required|email|max:255|unique:usuario,email',
            // Datos de Docente - registro_docente ya no es requerido, se genera automáticamente
            'cargo' => 'nullable|string|max:100',
            'area_de_especializacion' => 'nullable|string|max:200',
            'modalidad_de_contratacion' => 'nullable|string|max:100'
        ], [
            'ci.required' => 'El CI es obligatorio',
            'ci.unique' => 'El CI ya está registrado',
            'nombre.required' => 'El nombre es obligatorio',
            'apellido.required' => 'El apellido es obligatorio',
            'celular.regex' => 'El celular solo debe contener números',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'sexo.in' => 'El sexo debe ser M o F',
            'email.required' => 'El email es obligatorio para crear las credenciales de acceso',
            'email.email' => 'El email debe tener un formato válido',
            'email.unique' => 'El email ya está registrado en el sistema'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            // Generar código único de 5 dígitos para el docente
            $registroDocente = CodigoHelper::generarCodigoDocente();

            // Crear Persona primero (sin usuario_id - la relación es inversa)
            $persona = Persona::create([
                'ci' => trim($request->ci),
                'nombre' => trim($request->nombre),
                'apellido' => trim($request->apellido),
                'celular' => $request->celular ? trim($request->celular) : null,
                'sexo' => $request->sexo,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'direccion' => $request->direccion ? trim($request->direccion) : null,
                'fotografia' => $request->fotografia
            ]);

            // Crear Docente heredando de Persona (usando el mismo id)
            // Con PostgreSQL INHERITS, debemos insertar directamente usando DB
            DB::table('docente')->insert([
                'id' => $persona->id, // Usar el mismo ID de persona
                'ci' => trim($request->ci),
                'nombre' => trim($request->nombre),
                'apellido' => trim($request->apellido),
                'celular' => $request->celular ? trim($request->celular) : null,
                'sexo' => $request->sexo,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'direccion' => $request->direccion ? trim($request->direccion) : null,
                'fotografia' => $request->fotografia,
                'registro_docente' => $registroDocente,
                'cargo' => $request->cargo ? trim($request->cargo) : null,
                'area_de_especializacion' => $request->area_de_especializacion ? trim($request->area_de_especializacion) : null,
                'modalidad_de_contratacion' => $request->modalidad_de_contratacion ? trim($request->modalidad_de_contratacion) : null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Cargar el modelo Docente
            $docente = Docente::find($persona->id);
            if (!$docente) {
                DB::rollBack();
                throw new \Exception('Error: No se pudo cargar el docente después de la inserción.');
            }

            // Obtener rol DOCENTE
            $rolDocente = Rol::where('nombre_rol', 'DOCENTE')->where('activo', true)->first();
            if (!$rolDocente) {
                DB::rollBack();
                throw new \Exception('Error: El rol DOCENTE no existe en el sistema. Contacte al administrador.');
            }

            // Generar contraseña temporal segura (8 caracteres: mayúscula, minúscula, número)
            $passwordTemporal = Str::random(8) . rand(0, 9) . strtoupper(Str::random(1));

            // Crear Usuario con email y password temporal
            // El docente deberá cambiar la contraseña en su primer login
            $usuario = Usuario::create([
                'email' => trim(strtolower($request->email)),
                'password' => Hash::make($passwordTemporal),
                'persona_id' => $persona->id,
                'rol_id' => $rolDocente->rol_id,
                'debe_cambiar_password' => true // Requerir cambio de contraseña en primer login
            ]);

            Cache::forget('docentes_*');

            DB::commit();

            // Registrar en bitácora
            $this->registrarCreacion('docente', $docente->id, "Docente: {$docente->nombre} {$docente->apellido} - Registro: {$docente->registro_docente} - Usuario creado con email: {$usuario->email}");

            // Retornar datos incluyendo credenciales temporales (solo para mostrar al admin)
            return response()->json([
                'success' => true,
                'data' => [
                    'docente' => $docente,
                    'usuario' => [
                        'email' => $usuario->email,
                        'password_temporal' => $passwordTemporal, // Solo se muestra una vez
                        'debe_cambiar_password' => true
                    ]
                ],
                'message' => 'Docente y usuario creados exitosamente. El docente debe cambiar su contraseña en el primer login.'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear docente: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar docente
     */
    public function actualizar(Request $request, string $registro): JsonResponse
    {
        try {
            // Convertir a entero y buscar por id
            $id = (int) $registro;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de docente inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Buscar por id
            $docente = Docente::where('id', $id)->first();

            if (!$docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Docente no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $validator = Validator::make($request->all(), [
                // Datos de Persona
                'ci' => 'sometimes|string|max:20|unique:persona,ci,' . $docente->id . ',id',
                'nombre' => 'sometimes|string|max:100',
                'apellido' => 'sometimes|string|max:100',
                'celular' => 'nullable|string|max:20',
                'sexo' => 'nullable|string|in:M,F',
                'fecha_nacimiento' => 'nullable|date',
                'direccion' => 'nullable|string|max:255',
                'fotografia' => 'nullable|string|max:255',
                'usuario_id' => 'nullable|exists:usuario,id',
                // Datos de Docente
                'cargo' => 'nullable|string|max:100',
                'area_de_especializacion' => 'nullable|string|max:200',
                'modalidad_de_contratacion' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            // Actualizar Persona
            $persona = Persona::findOrFail($docente->id);
            $persona->update($validator->validated());

            // Actualizar Docente
            $docente->update($validator->validated());

            DB::commit();

            // Registrar en bitácora
            $docenteActualizado = $docente->fresh();
            $this->registrarEdicion('docente', $docenteActualizado->id, "Docente: {$docenteActualizado->nombre} {$docenteActualizado->apellido} - Registro: {$docenteActualizado->registro_docente}");

            return response()->json([
                'success' => true,
                'data' => $docenteActualizado,
                'message' => 'Docente actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar docente: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar docente
     */
    public function eliminar(string $registro): JsonResponse
    {
        try {
            // Convertir a entero y buscar por id
            $id = (int) $registro;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de docente inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Buscar por id
            $docente = Docente::where('id', $id)->first();

            if (!$docente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Docente no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Verificar si tiene grupos usando docente_id
            $tieneGrupos = Grupo::where('docente_id', $docente->id)->exists();

            if ($tieneGrupos) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el docente porque tiene grupos asignados'
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            // Guardar información antes de eliminar para bitácora
            $docenteNombre = "{$docente->nombre} {$docente->apellido}";
            $docenteRegistro = $docente->registro_docente;
            $docenteId = $docente->id;

            // Eliminar Persona (cascada eliminará Docente)
            $persona = Persona::findOrFail($docente->id);
            $persona->delete();

            DB::commit();

            // Registrar en bitácora
            $this->registrarEliminacion('docente', $docenteId, "Docente: {$docenteNombre} - Registro: {$docenteRegistro}");

            return response()->json([
                'success' => true,
                'message' => 'Docente eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar docente: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}

