<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Models\RamaAcademica;
use App\Models\TipoPrograma;
use App\Models\Institucion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProgramaController extends Controller
{
    /**
     * Listar programas con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Programa::with([
            'tipoPrograma:id,nombre',
            'ramaAcademica:id,nombre',
            'institucion:id,nombre',
            'version:id,nombre,anio'
        ])->activos();

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where('nombre', 'ILIKE', "%{$buscar}%");
        }

        if ($request->filled('tipo_programa_id')) {
            $query->where('Tipo_programa_id', $request->get('tipo_programa_id'));
        }

        if ($request->filled('rama_academica_id')) {
            $query->where('Rama_academica_id', $request->get('rama_academica_id'));
        }

        if ($request->filled('institucion_id')) {
            $query->where('Institucion_id', $request->get('institucion_id'));
        }

        if ($request->filled('es_curso')) {
            $esCurso = $request->boolean('es_curso');
            if ($esCurso) {
                $query->where('duracion_meses', '<', 12);
            } else {
                $query->where('duracion_meses', '>=', 12);
            }
        }

        if ($request->filled('costo_maximo')) {
            $query->where('costo', '<=', $request->get('costo_maximo'));
        }

        // Ordenamiento
        $query->orderBy('nombre');

        // Paginación con caché
        $cacheKey = 'programas_' . md5(serialize($request->all()));

        $programas = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $programas,
            'message' => 'Programas obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener programa específico con todas sus relaciones
     */
    public function show(int $id): JsonResponse
    {
        $programa = Programa::with([
            'tipoPrograma:id,nombre',
            'ramaAcademica:id,nombre',
            'institucion:id,nombre,ciudad_id',
            'institucion.ciudad:id,nombre_ciudad,Provincia_id',
            'institucion.ciudad.provincia:id,nombre_provincia,Pais_id',
            'institucion.ciudad.provincia.pais:id,nombre_pais',
            'version:id,nombre,anio',
            'modulos:id,nombre,credito,horas_academicas',
            'inscripciones' => function($query) {
                $query->with(['estudiante:id,ci,nombre,apellido'])->latest();
            },
            'grupos' => function($query) {
                $query->with([
                    'docente:id,nombre,apellido',
                    'horario:id,dias,hora_ini,hora_fin'
                ])->where('fecha_fin', '>=', now());
            }
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $programa,
            'message' => 'Programa obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo programa
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:250',
            'duracion_meses' => 'required|integer|min:1',
            'total_modulos' => 'nullable|integer|min:0',
            'costo' => 'required|numeric|min:0',
            'Rama_academica_id' => 'nullable|exists:Rama_academica,id',
            'Tipo_programa_id' => 'required|exists:Tipo_programa,id',
            'Programa_id' => 'nullable|exists:Programa,id',
            'Institucion_id' => 'required|exists:Institucion,id',
            'version_id' => 'nullable|exists:Version,id',
            'modulos' => 'nullable|array',
            'modulos.*' => 'exists:Modulo,id'
        ]);

        DB::beginTransaction();
        try {
            $programa = Programa::create($request->except('modulos'));

            // Asociar módulos si se proporcionan
            if ($request->has('modulos')) {
                $modulosData = [];
                foreach ($request->get('modulos') as $moduloId) {
                    $modulosData[$moduloId] = ['edicion' => '2024'];
                }
                $programa->modulos()->attach($modulosData);
            }

            // Limpiar caché
            Cache::forget('programas_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $programa->load(['tipoPrograma', 'ramaAcademica', 'institucion', 'version']),
                'message' => 'Programa creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar programa
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $programa = Programa::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:250',
            'duracion_meses' => 'required|integer|min:1',
            'total_modulos' => 'nullable|integer|min:0',
            'costo' => 'required|numeric|min:0',
            'Rama_academica_id' => 'nullable|exists:Rama_academica,id',
            'Tipo_programa_id' => 'required|exists:Tipo_programa,id',
            'Programa_id' => 'nullable|exists:Programa,id',
            'Institucion_id' => 'required|exists:Institucion,id',
            'version_id' => 'nullable|exists:Version,id',
            'modulos' => 'nullable|array',
            'modulos.*' => 'exists:Modulo,id'
        ]);

        DB::beginTransaction();
        try {
            $programa->update($request->except('modulos'));

            // Actualizar módulos si se proporcionan
            if ($request->has('modulos')) {
                $modulosData = [];
                foreach ($request->get('modulos') as $moduloId) {
                    $modulosData[$moduloId] = ['edicion' => '2024'];
                }
                $programa->modulos()->sync($modulosData);
            }

            // Limpiar caché
            Cache::forget('programas_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $programa->load(['tipoPrograma', 'ramaAcademica', 'institucion', 'version']),
                'message' => 'Programa actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar programa
     */
    public function destroy(int $id): JsonResponse
    {
        $programa = Programa::findOrFail($id);

        // Verificar si tiene inscripciones o grupos activos
        if ($programa->inscripciones()->exists() || $programa->grupos()->where('fecha_fin', '>=', now())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el programa porque tiene inscripciones o grupos activos'
            ], 422);
        }

        $programa->delete();

        // Limpiar caché
        Cache::forget('programas_*');

        return response()->json([
            'success' => true,
            'message' => 'Programa eliminado exitosamente'
        ]);
    }

    /**
     * Obtener datos para formularios
     */
    public function datosFormulario(): JsonResponse
    {
        $cacheKey = 'programas_datos_formulario';

        $datos = Cache::remember($cacheKey, 600, function() {
            return [
                'tipos_programa' => TipoPrograma::select('id', 'nombre')->orderBy('nombre')->get(),
                'ramas_academicas' => RamaAcademica::select('id', 'nombre')->orderBy('nombre')->get(),
                'instituciones' => Institucion::select('id', 'nombre')->activas()->orderBy('nombre')->get(),
                'versiones' => \App\Models\Version::select('id', 'nombre', 'anio')->recientes()->orderBy('anio', 'desc')->get(),
                'modulos' => \App\Models\Modulo::select('id', 'nombre', 'credito', 'horas_academicas')->orderBy('nombre')->get()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $datos,
            'message' => 'Datos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de programas
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_programas';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Programa::count(),
                'activos' => Programa::activos()->count(),
                'cursos' => Programa::where('duracion_meses', '<', 12)->count(),
                'programas' => Programa::where('duracion_meses', '>=', 12)->count(),
                'por_tipo' => TipoPrograma::withCount('programas')->get(),
                'por_rama' => RamaAcademica::withCount('programas')->get(),
                'por_institucion' => Institucion::withCount('programas')->activas()->get(),
                'costo_promedio' => Programa::avg('costo'),
                'duracion_promedio' => Programa::avg('duracion_meses')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
