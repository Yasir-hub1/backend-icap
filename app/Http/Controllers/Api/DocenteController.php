<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DocenteController extends Controller
{
    /**
     * Listar docentes con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Docente::with([
            'grupos.programa:id,nombre',
            'grupos.horario:id,dias,hora_ini,hora_fin'
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where(function($q) use ($buscar) {
                $q->where('ci', 'ILIKE', "%{$buscar}%")
                  ->orWhere('nombre', 'ILIKE', "%{$buscar}%")
                  ->orWhere('apellido', 'ILIKE', "%{$buscar}%")
                  ->orWhere('registro_docente', 'ILIKE', "%{$buscar}%");
            });
        }

        if ($request->filled('especializacion')) {
            $query->where('area_de_especializacion', 'ILIKE', "%{$request->get('especializacion')}%");
        }

        if ($request->filled('modalidad_contratacion')) {
            $query->where('modalidad_de_contratacion', $request->get('modalidad_contratacion'));
        }

        if ($request->filled('con_grupos_activos')) {
            $query->conGruposActivos();
        }

        // Ordenamiento
        $query->orderBy('apellido')->orderBy('nombre');

        // Paginación con caché
        $cacheKey = 'docentes_' . md5(serialize($request->all()));

        $docentes = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $docentes,
            'message' => 'Docentes obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener docente específico con todas sus relaciones
     */
    public function show(int $id): JsonResponse
    {
        $docente = Docente::with([
            'grupos' => function($query) {
                $query->with([
                    'programa:id,nombre,duracion_meses',
                    'programa.tipoPrograma:id,nombre',
                    'programa.institucion:id,nombre',
                    'horario:id,dias,hora_ini,hora_fin',
                    'estudiantes:id,ci,nombre,apellido'
                ])->where('fecha_fin', '>=', now());
            }
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $docente,
            'message' => 'Docente obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo docente
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ci' => 'required|string|max:20|unique:Docente,ci',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:300',
            'fotografia' => 'nullable|string',
            'registro_docente' => 'required|string|max:50|unique:Docente,registro_docente',
            'cargo' => 'nullable|string|max:100',
            'area_de_especializacion' => 'nullable|string|max:200',
            'modalidad_de_contratacion' => 'nullable|string|max:100'
        ]);

        DB::beginTransaction();
        try {
            $docente = Docente::create($request->validated());

            // Limpiar caché
            Cache::forget('docentes_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $docente,
                'message' => 'Docente creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear docente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar docente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $docente = Docente::findOrFail($id);

        $request->validate([
            'ci' => 'required|string|max:20|unique:Docente,ci,' . $id,
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:300',
            'fotografia' => 'nullable|string',
            'registro_docente' => 'required|string|max:50|unique:Docente,registro_docente,' . $id,
            'cargo' => 'nullable|string|max:100',
            'area_de_especializacion' => 'nullable|string|max:200',
            'modalidad_de_contratacion' => 'nullable|string|max:100'
        ]);

        $docente->update($request->validated());

        // Limpiar caché
        Cache::forget('docentes_*');

        return response()->json([
            'success' => true,
            'data' => $docente,
            'message' => 'Docente actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar docente (soft delete lógico)
     */
    public function destroy(int $id): JsonResponse
    {
        $docente = Docente::findOrFail($id);

        // Verificar si tiene grupos activos
        if ($docente->grupos()->where('fecha_fin', '>=', now())->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el docente porque tiene grupos activos'
            ], 422);
        }

        // Cambiar registro a inactivo (soft delete lógico)
        $docente->update(['registro_docente' => null]);

        // Limpiar caché
        Cache::forget('docentes_*');

        return response()->json([
            'success' => true,
            'message' => 'Docente desactivado exitosamente'
        ]);
    }

    /**
     * Buscar docentes para autocompletado
     */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $query = $request->get('q');

        $docentes = Docente::select('id', 'ci', 'nombre', 'apellido', 'registro_docente', 'area_de_especializacion')
            ->where(function($q) use ($query) {
                $q->where('ci', 'ILIKE', "%{$query}%")
                  ->orWhere('nombre', 'ILIKE', "%{$query}%")
                  ->orWhere('apellido', 'ILIKE', "%{$query}%")
                  ->orWhere('registro_docente', 'ILIKE', "%{$query}%");
            })
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $docentes,
            'message' => 'Búsqueda completada'
        ]);
    }

    /**
     * Obtener estadísticas de docentes
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_docentes';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Docente::count(),
                'activos' => Docente::activos()->count(),
                'con_grupos_activos' => Docente::conGruposActivos()->count(),
                'por_especializacion' => Docente::selectRaw('area_de_especializacion, COUNT(*) as total')
                    ->whereNotNull('area_de_especializacion')
                    ->groupBy('area_de_especializacion')
                    ->orderBy('total', 'desc')
                    ->get(),
                'por_modalidad' => Docente::selectRaw('modalidad_de_contratacion, COUNT(*) as total')
                    ->whereNotNull('modalidad_de_contratacion')
                    ->groupBy('modalidad_de_contratacion')
                    ->orderBy('total', 'desc')
                    ->get(),
                'carga_horaria_promedio' => Docente::conGruposActivos()
                    ->get()
                    ->avg('carga_horaria_actual')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
