<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Programa;
use App\Models\Modulo;
use App\Models\Docente;
use App\Models\Horario;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GrupoController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar grupos con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $programaId = $request->get('programa_id', '');
            $moduloId = $request->get('modulo_id', '');
            $docenteId = $request->get('docente_id', '');

            $query = Grupo::with([
                'programa:id,nombre',
                'modulo:modulo_id,nombre',
                'docente:id,nombre,apellido',
                'horarios'
            ])->withCount('estudiantes');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->whereHas('programa', function($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%");
                    })
                    ->orWhereHas('modulo', function($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%");
                    })
                    ->orWhereHas('docente', function($q2) use ($search) {
                        $q2->where('nombre', 'ILIKE', "%{$search}%")
                           ->orWhere('apellido', 'ILIKE', "%{$search}%");
                    });
                });
            }

            if ($programaId) {
                $query->where('programa_id', $programaId);
            }

            if ($moduloId) {
                $query->where('modulo_id', $moduloId);
            }

            if ($docenteId) {
                $query->where('docente_id', $docenteId);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'fecha_ini');
            $sortDirection = $request->get('sort_direction', 'desc');
            
            // Validar que sort_by sea una columna válida
            $allowedSortColumns = ['fecha_ini', 'fecha_fin', 'grupo_id', 'id', 'created_at'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'fecha_ini';
            }
            
            // Validar dirección de ordenamiento
            $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
            
            $grupos = $query->orderBy($sortBy, $sortDirection)
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $grupos,
                'message' => 'Grupos obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener grupo por ID
     */
    public function obtener($id): JsonResponse
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de grupo inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $grupo = Grupo::with([
                'programa',
                'modulo',
                'docente',
                'horarios' => function($query) {
                    $query->withPivot('aula');
                },
                'estudiantes'
            ])->find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            return response()->json([
                'success' => true,
                'data' => $grupo,
                'message' => 'Grupo obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupo: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo grupo
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'programa_id' => 'required|integer|exists:programa,id',
            'modulo_id' => 'required|integer|exists:modulo,modulo_id',
            'docente_id' => 'required|integer|exists:docente,id',
            'horarios' => 'nullable|array',
            'horarios.*.horario_id' => 'required|integer|exists:horario,horario_id',
            'horarios.*.aula' => 'nullable|string|max:50'
        ], [
            'fecha_ini.required' => 'La fecha de inicio es obligatoria',
            'fecha_ini.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.required' => 'La fecha de fin es obligatoria',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after' => 'La fecha de fin debe ser mayor a la fecha de inicio',
            'programa_id.required' => 'El programa es obligatorio',
            'programa_id.exists' => 'El programa seleccionado no existe',
            'modulo_id.required' => 'El módulo es obligatorio',
            'modulo_id.exists' => 'El módulo seleccionado no existe',
            'docente_id.required' => 'El docente es obligatorio',
            'docente_id.exists' => 'El docente seleccionado no existe',
            'horarios.*.horario_id.required' => 'El horario es obligatorio',
            'horarios.*.horario_id.exists' => 'El horario seleccionado no existe',
            'horarios.*.aula.max' => 'El nombre del aula no puede tener más de 50 caracteres'
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

            $data = $validator->validated();
            $horarios = $data['horarios'] ?? [];
            unset($data['horarios']);

            // Crear grupo
            $grupo = Grupo::create($data);

            // Asociar horarios
            if (!empty($horarios)) {
                $horariosData = [];
                foreach ($horarios as $horario) {
                    $horariosData[$horario['horario_id']] = [
                        'aula' => $horario['aula'] ?? null
                    ];
                }
                $grupo->horarios()->attach($horariosData);
            }

            DB::commit();

            // Registrar en bitácora
            $programaNombre = $grupo->programa ? $grupo->programa->nombre : 'N/A';
            $this->registrarCreacion('grupo', $grupo->id, "Grupo ID: {$grupo->id} - Programa: {$programaNombre}");

            return response()->json([
                'success' => true,
                'data' => $grupo->load(['programa', 'modulo', 'docente', 'horarios']),
                'message' => 'Grupo creado exitosamente'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar grupo
     */
    public function actualizar(Request $request, $id): JsonResponse
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de grupo inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $grupo = Grupo::find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $validator = Validator::make($request->all(), [
                'fecha_ini' => 'sometimes|required|date',
                'fecha_fin' => 'sometimes|required|date|after:fecha_ini',
                'programa_id' => 'sometimes|required|integer|exists:programa,id',
                'modulo_id' => 'sometimes|required|integer|exists:modulo,modulo_id',
                'docente_id' => 'sometimes|required|integer|exists:docente,id',
                'horarios' => 'nullable|array',
                'horarios.*.horario_id' => 'required|integer|exists:horario,horario_id',
                'horarios.*.aula' => 'nullable|string|max:50'
            ], [
                'fecha_ini.required' => 'La fecha de inicio es obligatoria',
                'fecha_ini.date' => 'La fecha de inicio debe ser una fecha válida',
                'fecha_fin.required' => 'La fecha de fin es obligatoria',
                'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
                'fecha_fin.after' => 'La fecha de fin debe ser mayor a la fecha de inicio',
                'programa_id.required' => 'El programa es obligatorio',
                'programa_id.exists' => 'El programa seleccionado no existe',
                'modulo_id.required' => 'El módulo es obligatorio',
                'modulo_id.exists' => 'El módulo seleccionado no existe',
                'docente_id.required' => 'El docente es obligatorio',
                'docente_id.exists' => 'El docente seleccionado no existe',
                'horarios.*.horario_id.required' => 'El horario es obligatorio',
                'horarios.*.horario_id.exists' => 'El horario seleccionado no existe',
                'horarios.*.aula.max' => 'El nombre del aula no puede tener más de 50 caracteres'
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

            DB::beginTransaction();

            $data = $validator->validated();
            $horarios = $data['horarios'] ?? null;
            unset($data['horarios']);

            // Actualizar grupo
            $grupo->update($data);

            // Actualizar horarios si se proporcionaron
            if ($horarios !== null) {
                $horariosData = [];
                foreach ($horarios as $horario) {
                    $horariosData[$horario['horario_id']] = [
                        'aula' => $horario['aula'] ?? null
                    ];
                }
                $grupo->horarios()->sync($horariosData);
            }

            DB::commit();

            // Registrar en bitácora
            $programaNombre = $grupo->programa ? $grupo->programa->nombre : 'N/A';
            $this->registrarEdicion('grupo', $grupo->id, "Grupo ID: {$grupo->id} - Programa: {$programaNombre}");

            return response()->json([
                'success' => true,
                'data' => $grupo->load(['programa', 'modulo', 'docente', 'horarios']),
                'message' => 'Grupo actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar grupo: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar grupo
     */
    public function eliminar($id): JsonResponse
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de grupo inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $grupo = Grupo::find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Verificar si tiene estudiantes
            if ($grupo->estudiantes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el grupo porque tiene estudiantes asignados'
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            // Guardar datos para bitácora antes de eliminar
            $grupoId = $grupo->id;
            $programaNombre = $grupo->programa ? $grupo->programa->nombre : 'N/A';

            // Desasociar horarios
            $grupo->horarios()->detach();

            $grupo->delete();

            DB::commit();

            // Registrar en bitácora
            $this->registrarEliminacion('grupo', $grupoId, "Grupo ID: {$grupoId} - Programa: {$programaNombre}");

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar grupo: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener datos para formularios
     */
    public function datosFormulario(): JsonResponse
    {
        try {
            // Obtener programas - asegurar que se devuelvan como array
            $programas = Programa::select('id', 'nombre')
                ->orderBy('nombre')
                ->get()
                ->map(function($programa) {
                    return [
                        'id' => $programa->id,
                        'nombre' => $programa->nombre
                    ];
                })
                ->values(); // Asegurar que sea un array indexado, no un objeto

            $datos = [
                'programas' => $programas,
                'modulos' => Modulo::select('modulo_id', 'nombre', 'credito', 'horas_academicas')
                    ->orderBy('nombre')
                    ->get()
                    ->map(function($modulo) {
                        return [
                            'id' => $modulo->modulo_id, // Usar modulo_id como id para el frontend
                            'modulo_id' => $modulo->modulo_id,
                            'nombre' => $modulo->nombre,
                            'credito' => $modulo->credito,
                            'horas_academicas' => $modulo->horas_academicas
                        ];
                    })
                    ->values(), // Asegurar que sea un array indexado
                'docentes' => Docente::select('id', 'registro_docente', 'nombre', 'apellido', 'ci')
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get()
                    ->map(function($docente) {
                        return [
                            'id' => $docente->id, // Usar id del docente (que viene de persona)
                            'registro_docente' => $docente->registro_docente,
                            'nombre_completo' => "{$docente->nombre} {$docente->apellido}",
                            'ci' => $docente->ci
                        ];
                    })
                    ->values(), // Asegurar que sea un array indexado
                'horarios' => Horario::select('horario_id', 'dias', 'hora_ini', 'hora_fin')
                    ->orderBy('dias')
                    ->orderBy('hora_ini')
                    ->get()
                    ->map(function($horario) {
                        // Usar los accessors del modelo si están disponibles
                        $horaIni = $horario->hora_ini_formatted ?? null;
                        $horaFin = $horario->hora_fin_formatted ?? null;

                        // Fallback: formatear manualmente
                        if (!$horaIni && $horario->hora_ini) {
                            if (is_string($horario->hora_ini) && strpos($horario->hora_ini, 'T') !== false) {
                                $horaIni = substr($horario->hora_ini, 11, 5);
                            } elseif (is_string($horario->hora_ini)) {
                                $horaIni = substr($horario->hora_ini, 0, 5);
                            } elseif ($horario->hora_ini instanceof \DateTime || $horario->hora_ini instanceof \Carbon\Carbon) {
                                $horaIni = $horario->hora_ini->format('H:i');
                            }
                        }

                        if (!$horaFin && $horario->hora_fin) {
                            if (is_string($horario->hora_fin) && strpos($horario->hora_fin, 'T') !== false) {
                                $horaFin = substr($horario->hora_fin, 11, 5);
                            } elseif (is_string($horario->hora_fin)) {
                                $horaFin = substr($horario->hora_fin, 0, 5);
                            } elseif ($horario->hora_fin instanceof \DateTime || $horario->hora_fin instanceof \Carbon\Carbon) {
                                $horaFin = $horario->hora_fin->format('H:i');
                            }
                        }

                        return [
                            'id' => $horario->horario_id,
                            'dias' => $horario->dias,
                            'hora_ini' => $horaIni,
                            'hora_ini_formatted' => $horaIni,
                            'hora_fin' => $horaFin,
                            'hora_fin_formatted' => $horaFin
                        ];
                    })
                    ->values() // Asegurar que sea un array indexado
            ];

            // Log para depuración
            Log::info('Datos del formulario enviados:', [
                'programas_count' => is_array($datos['programas']) ? count($datos['programas']) : $datos['programas']->count(),
                'modulos_count' => is_array($datos['modulos']) ? count($datos['modulos']) : $datos['modulos']->count(),
                'docentes_count' => is_array($datos['docentes']) ? count($datos['docentes']) : $datos['docentes']->count(),
                'horarios_count' => is_array($datos['horarios']) ? count($datos['horarios']) : $datos['horarios']->count(),
                'programas_sample' => is_array($datos['programas']) ? array_slice($datos['programas'], 0, 2) : $datos['programas']->take(2)->toArray(),
                'modulos_sample' => is_array($datos['modulos']) ? array_slice($datos['modulos'], 0, 2) : $datos['modulos']->take(2)->toArray(),
                'docentes_sample' => is_array($datos['docentes']) ? array_slice($datos['docentes'], 0, 2) : $datos['docentes']->take(2)->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => $datos,
                'message' => 'Datos obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}
