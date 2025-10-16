<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institucion;
use App\Models\Ciudad;
use App\Models\Convenio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InstitucionController extends Controller
{
    /**
     * Listar instituciones con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Institucion::with([
            'ciudad:id,nombre_ciudad,Provincia_id',
            'ciudad.provincia:id,nombre_provincia,Pais_id',
            'ciudad.provincia.pais:id,nombre_pais',
            'programas:id,nombre,Institucion_id',
            'convenios' => function($query) {
                $query->where('fecha_fin', '>=', now())->where('estado', 1);
            }
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where('nombre', 'ILIKE', "%{$buscar}%");
        }

        if ($request->filled('ciudad_id')) {
            $query->where('ciudad_id', $request->get('ciudad_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }

        if ($request->filled('con_convenios_activos')) {
            $query->conConveniosActivos();
        }

        // Ordenamiento
        $query->orderBy('nombre');

        // Paginación con caché
        $cacheKey = 'instituciones_' . md5(serialize($request->all()));

        $instituciones = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $instituciones,
            'message' => 'Instituciones obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener instituciones activas
     */
    public function activas(): JsonResponse
    {
        $instituciones = Cache::remember('instituciones_activas', 600, function() {
            return Institucion::select('id', 'nombre', 'ciudad_id')
                ->activas()
                ->with('ciudad:id,nombre_ciudad')
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $instituciones,
            'message' => 'Instituciones activas obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener institución específica con todas sus relaciones
     */
    public function show(int $id): JsonResponse
    {
        $institucion = Institucion::with([
            'ciudad:id,nombre_ciudad,Provincia_id',
            'ciudad.provincia:id,nombre_provincia,Pais_id',
            'ciudad.provincia.pais:id,nombre_pais',
            'programas' => function($query) {
                $query->with(['tipoPrograma:id,nombre', 'ramaAcademica:id,nombre']);
            },
            'convenios' => function($query) {
                $query->with(['tipoConvenio:id,nombre_tipo'])
                      ->where('fecha_fin', '>=', now())
                      ->where('estado', 1);
            }
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $institucion,
            'message' => 'Institución obtenida exitosamente'
        ]);
    }

    /**
     * Crear nueva institución
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'direccion' => 'nullable|string|max:300',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'sitio_web' => 'nullable|url|max:200',
            'fecha_fundacion' => 'nullable|date',
            'estado' => 'required|integer|in:0,1',
            'ciudad_id' => 'required|exists:Ciudad,id'
        ]);

        DB::beginTransaction();
        try {
            $institucion = Institucion::create($request->validated());

            // Limpiar caché
            Cache::forget('instituciones_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $institucion->load(['ciudad', 'ciudad.provincia', 'ciudad.provincia.pais']),
                'message' => 'Institución creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear institución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar institución
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $institucion = Institucion::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:200',
            'direccion' => 'nullable|string|max:300',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'sitio_web' => 'nullable|url|max:200',
            'fecha_fundacion' => 'nullable|date',
            'estado' => 'required|integer|in:0,1',
            'ciudad_id' => 'required|exists:Ciudad,id'
        ]);

        $institucion->update($request->validated());

        // Limpiar caché
        Cache::forget('instituciones_*');

        return response()->json([
            'success' => true,
            'data' => $institucion->load(['ciudad', 'ciudad.provincia', 'ciudad.provincia.pais']),
            'message' => 'Institución actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar institución (soft delete lógico)
     */
    public function destroy(int $id): JsonResponse
    {
        $institucion = Institucion::findOrFail($id);

        // Verificar si tiene programas activos
        if ($institucion->programas()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la institución porque tiene programas asociados'
            ], 422);
        }

        // Verificar si tiene convenios activos
        if ($institucion->convenios()->where('fecha_fin', '>=', now())->where('estado', 1)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la institución porque tiene convenios activos'
            ], 422);
        }

        // Cambiar estado a inactivo en lugar de eliminar
        $institucion->update(['estado' => 0]);

        // Limpiar caché
        Cache::forget('instituciones_*');

        return response()->json([
            'success' => true,
            'message' => 'Institución desactivada exitosamente'
        ]);
    }

    /**
     * Buscar instituciones para autocompletado
     */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $query = $request->get('q');

        $instituciones = Institucion::select('id', 'nombre', 'ciudad_id')
            ->with('ciudad:id,nombre_ciudad')
            ->where('nombre', 'ILIKE', "%{$query}%")
            ->activas()
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $instituciones,
            'message' => 'Búsqueda completada'
        ]);
    }

    /**
     * Obtener estadísticas de instituciones
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_instituciones';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Institucion::count(),
                'activas' => Institucion::activas()->count(),
                'inactivas' => Institucion::where('estado', 0)->count(),
                'con_programas' => Institucion::whereHas('programas')->count(),
                'con_convenios_activos' => Institucion::conConveniosActivos()->count(),
                'por_ciudad' => Institucion::withCount('programas')
                    ->with('ciudad:id,nombre_ciudad')
                    ->activas()
                    ->get()
                    ->groupBy('ciudad.nombre_ciudad')
                    ->map(function($grupo) {
                        return [
                            'cantidad' => $grupo->count(),
                            'programas' => $grupo->sum('programas_count')
                        ];
                    }),
                'programas_promedio' => Institucion::withCount('programas')->activas()->get()->avg('programas_count')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
