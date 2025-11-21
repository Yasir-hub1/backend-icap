<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Models\RamaAcademica;
use App\Models\Version;
use App\Models\TipoPrograma;
use App\Models\Modulo;
use App\Models\Institucion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProgramaController extends Controller
{
    /**
     * Listar programas con paginación y filtros
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $ramaAcademicaId = $request->get('rama_academica_id');
            $tipoProgramaId = $request->get('tipo_programa_id');
            $versionId = $request->get('version_id');
            $institucionId = $request->get('institucion_id');

            $query = Programa::with([
                'ramaAcademica:id,nombre',
                'tipoPrograma:id,nombre',
                'version:id,nombre,año',
                'institucion:id,nombre'
            ])->withCount(['modulos', 'subprogramas', 'inscripciones']);

            if ($search) {
                $query->where('nombre', 'ILIKE', "%{$search}%");
            }

            if ($ramaAcademicaId) {
                $query->where('rama_academica_id', $ramaAcademicaId);
            }

            if ($tipoProgramaId) {
                $query->where('tipo_programa_id', $tipoProgramaId);
            }

            if ($versionId) {
                $query->where('version_id', $versionId);
            }

            if ($institucionId) {
                $query->where('institucion_id', $institucionId);
            }

            $programas = $query->orderBy('nombre', 'asc')
                               ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $programas,
                'message' => 'Programas obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programas: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener programa por ID
     */
    public function obtener($id): JsonResponse
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de programa inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $programa = Programa::with([
                'ramaAcademica',
                'tipoPrograma',
                'version',
                'institucion',
                'modulos',
                'subprogramas:id,nombre,tipo_programa_id',
                'programasPadre:id,nombre,tipo_programa_id'
            ])->find($id);

            if (!$programa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programa no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            return response()->json([
                'success' => true,
                'data' => $programa,
                'message' => 'Programa obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programa: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo programa
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:200',
            'duracion_meses' => 'required|integer|min:1',
            'total_modulos' => 'nullable|integer|min:0',
            'costo' => 'nullable|numeric|min:0',
            'version_id' => 'required|exists:version,id',
            'rama_academica_id' => 'required|exists:rama_academica,id',
            'tipo_programa_id' => 'required|exists:tipo_programa,id',
            'institucion_id' => 'required|exists:institucion,id',
            'modulos' => 'nullable|array',
            'modulos.*.id' => 'required|exists:modulo,modulo_id',
            'modulos.*.edicion' => 'nullable|integer|min:1',
            'subprogramas' => 'nullable|array',
            'subprogramas.*' => 'required|exists:programa,id'
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

            $data = $validator->validated();
            $modulos = $data['modulos'] ?? [];
            $subprogramas = $data['subprogramas'] ?? [];
            unset($data['modulos'], $data['subprogramas']);

            // Crear programa
            $programa = Programa::create($data);

            // Asociar módulos
            if (!empty($modulos)) {
                $modulosData = [];
                foreach ($modulos as $modulo) {
                    $modulosData[$modulo['id']] = [
                        'edicion' => $modulo['edicion'] ?? null
                    ];
                }
                $programa->modulos()->attach($modulosData);
            }

            // Asociar subprogramas
            if (!empty($subprogramas)) {
                $programa->subprogramas()->attach($subprogramas);
            }

            Cache::forget('programas_all');
            Cache::forget('catalogos_tipos_programa');
            Cache::forget('catalogos_ramas_academicas');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $programa->load(['ramaAcademica', 'tipoPrograma', 'version', 'institucion', 'modulos', 'subprogramas']),
                'message' => 'Programa creado exitosamente'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear programa: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar programa
     */
    public function actualizar(Request $request, $id): JsonResponse
    {
        $id = (int) $id;

        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ID de programa inválido'
            ], 400)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $programa = Programa::find($id);

        if (!$programa) {
            return response()->json([
                'success' => false,
                'message' => 'Programa no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:200',
            'duracion_meses' => 'required|integer|min:1',
            'total_modulos' => 'nullable|integer|min:0',
            'costo' => 'nullable|numeric|min:0',
            'version_id' => 'required|exists:version,id',
            'rama_academica_id' => 'required|exists:rama_academica,id',
            'tipo_programa_id' => 'required|exists:tipo_programa,id',
            'institucion_id' => 'required|exists:institucion,id',
            'modulos' => 'nullable|array',
            'modulos.*.id' => 'required|exists:modulo,modulo_id',
            'modulos.*.edicion' => 'nullable|integer|min:1',
            'subprogramas' => 'nullable|array',
            'subprogramas.*' => 'required|exists:programa,id'
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

            $data = $validator->validated();
            $modulos = $data['modulos'] ?? null;
            $subprogramas = $data['subprogramas'] ?? null;
            unset($data['modulos'], $data['subprogramas']);

            // Actualizar programa
            $programa->update($data);

            // Actualizar módulos si se proporcionaron
            if ($modulos !== null) {
                $modulosData = [];
                foreach ($modulos as $modulo) {
                    $modulosData[$modulo['id']] = [
                        'edicion' => $modulo['edicion'] ?? null
                    ];
                }
                $programa->modulos()->sync($modulosData);
            }

            // Actualizar subprogramas si se proporcionaron
            if ($subprogramas !== null) {
                $programa->subprogramas()->sync($subprogramas);
            }

            Cache::forget('programas_all');
            Cache::forget('catalogos_tipos_programa');
            Cache::forget('catalogos_ramas_academicas');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $programa->load(['ramaAcademica', 'tipoPrograma', 'version', 'institucion', 'modulos', 'subprogramas']),
                'message' => 'Programa actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar programa: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar programa
     */
    public function eliminar($id): JsonResponse
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de programa inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $programa = Programa::find($id);

            if (!$programa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programa no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Verificar si tiene inscripciones
            if ($programa->inscripciones()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el programa porque tiene inscripciones asociadas'
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Verificar si tiene grupos
            if ($programa->grupos()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el programa porque tiene grupos asociados'
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            // Desasociar módulos y subprogramas
            $programa->modulos()->detach();
            $programa->subprogramas()->detach();
            $programa->programasPadre()->detach();

            $programa->delete();

            Cache::forget('programas_all');
            Cache::forget('catalogos_tipos_programa');
            Cache::forget('catalogos_ramas_academicas');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Programa eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar programa: ' . $e->getMessage()
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
            $datos = [
                'ramas_academicas' => RamaAcademica::select('id', 'nombre')
                    ->orderBy('nombre')
                    ->get(),
                'versiones' => Version::select('id', 'nombre', 'año')
                    ->orderBy('año', 'desc')
                    ->orderBy('nombre')
                    ->get(),
                'tipos_programa' => TipoPrograma::select('id', 'nombre')
                    ->orderBy('nombre')
                    ->get(),
                'instituciones' => Institucion::select('id', 'nombre')
                    ->where('estado', 1)
                    ->orderBy('nombre')
                    ->get(),
                'modulos' => Modulo::select('modulo_id', 'nombre', 'credito', 'horas_academicas')
                    ->orderBy('nombre')
                    ->get()
                    ->map(function($modulo) {
                        return [
                            'id' => $modulo->modulo_id,
                            'nombre' => $modulo->nombre,
                            'credito' => $modulo->credito,
                            'horas_academicas' => $modulo->horas_academicas
                        ];
                    })
                    ->values()
            ];

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
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}

