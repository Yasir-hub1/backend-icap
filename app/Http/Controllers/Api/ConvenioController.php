<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\TipoConvenio;
use App\Models\Institucion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ConvenioController extends Controller
{
    /**
     * Listar convenios con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Convenio::with([
            'tipoConvenio:id,nombre_tipo,descripcion',
            'instituciones:id,nombre'
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where('numero_convenio', 'ILIKE', "%{$buscar}%")
                  ->orWhere('objeto_convenio', 'ILIKE', "%{$buscar}%");
        }

        if ($request->filled('tipo_convenio_id')) {
            $query->where('Tipo_convenio_id', $request->get('tipo_convenio_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha_ini', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_fin', '<=', $request->get('fecha_hasta'));
        }

        // Ordenamiento
        $query->orderBy('fecha_ini', 'desc');

        // Paginación con caché
        $cacheKey = 'convenios_' . md5(serialize($request->all()));

        $convenios = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $convenios,
            'message' => 'Convenios obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener convenios activos
     */
    public function activos(): JsonResponse
    {
        $convenios = Cache::remember('convenios_activos', 600, function() {
            return Convenio::with([
                'tipoConvenio:id,nombre_tipo',
                'instituciones:id,nombre'
            ])
            ->activos()
            ->orderBy('fecha_fin')
            ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $convenios,
            'message' => 'Convenios activos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener convenios vencidos
     */
    public function vencidos(): JsonResponse
    {
        $convenios = Cache::remember('convenios_vencidos', 600, function() {
            return Convenio::with([
                'tipoConvenio:id,nombre_tipo',
                'instituciones:id,nombre'
            ])
            ->vencidos()
            ->orderBy('fecha_fin', 'desc')
            ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $convenios,
            'message' => 'Convenios vencidos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener convenio específico con todas sus relaciones
     */
    public function show(int $id): JsonResponse
    {
        $convenio = Convenio::with([
            'tipoConvenio:id,nombre_tipo,descripcion',
            'instituciones' => function($query) {
                $query->withPivot('porcentaje_participacion', 'monto_asignado', 'estado');
            },
            'documentos.tipoDocumento:id,nombre_entidad'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $convenio,
            'message' => 'Convenio obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo convenio
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'numero_convenio' => 'required|string|max:50|unique:Convenio,numero_convenio',
            'objeto_convenio' => 'required|string',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'fecha_firma' => 'nullable|date',
            'moneda' => 'nullable|string|max:10',
            'observaciones' => 'nullable|string',
            'estado' => 'required|integer|in:0,1',
            'Tipo_convenio_id' => 'required|exists:Tipo_convenio,id',
            'instituciones' => 'required|array|min:1',
            'instituciones.*.id' => 'required|exists:Institucion,id',
            'instituciones.*.porcentaje_participacion' => 'nullable|numeric|min:0|max:100',
            'instituciones.*.monto_asignado' => 'nullable|numeric|min:0'
        ]);

        DB::beginTransaction();
        try {
            // Crear convenio
            $convenio = Convenio::create($request->except('instituciones'));

            // Asociar instituciones
            $institucionesData = [];
            foreach ($request->get('instituciones') as $institucion) {
                $institucionesData[$institucion['id']] = [
                    'porcentaje_participacion' => $institucion['porcentaje_participacion'] ?? null,
                    'monto_asignado' => $institucion['monto_asignado'] ?? null,
                    'estado' => 1
                ];
            }
            $convenio->instituciones()->attach($institucionesData);

            // Limpiar caché
            Cache::forget('convenios_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $convenio->load(['tipoConvenio', 'instituciones']),
                'message' => 'Convenio creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear convenio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar convenio
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $convenio = Convenio::findOrFail($id);

        $request->validate([
            'numero_convenio' => 'required|string|max:50|unique:Convenio,numero_convenio,' . $id,
            'objeto_convenio' => 'required|string',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'fecha_firma' => 'nullable|date',
            'moneda' => 'nullable|string|max:10',
            'observaciones' => 'nullable|string',
            'estado' => 'required|integer|in:0,1',
            'Tipo_convenio_id' => 'required|exists:Tipo_convenio,id'
        ]);

        $convenio->update($request->validated());

        // Limpiar caché
        Cache::forget('convenios_*');

        return response()->json([
            'success' => true,
            'data' => $convenio->load(['tipoConvenio', 'instituciones']),
            'message' => 'Convenio actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar convenio
     */
    public function destroy(int $id): JsonResponse
    {
        $convenio = Convenio::findOrFail($id);

        // Verificar si tiene documentos asociados
        if ($convenio->documentos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el convenio porque tiene documentos asociados'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Desasociar instituciones
            $convenio->instituciones()->detach();

            $convenio->delete();

            // Limpiar caché
            Cache::forget('convenios_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Convenio eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar convenio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar institución al convenio
     */
    public function agregarInstitucion(Request $request, int $id): JsonResponse
    {
        $convenio = Convenio::findOrFail($id);

        $request->validate([
            'institucion_id' => 'required|exists:Institucion,id',
            'porcentaje_participacion' => 'nullable|numeric|min:0|max:100',
            'monto_asignado' => 'nullable|numeric|min:0'
        ]);

        // Verificar que la institución no esté ya asociada
        if ($convenio->instituciones()->where('Institucion_id', $request->get('institucion_id'))->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'La institución ya está asociada a este convenio'
            ], 422);
        }

        $convenio->instituciones()->attach($request->get('institucion_id'), [
            'porcentaje_participacion' => $request->get('porcentaje_participacion'),
            'monto_asignado' => $request->get('monto_asignado'),
            'estado' => 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Institución agregada al convenio exitosamente'
        ]);
    }

    /**
     * Remover institución del convenio
     */
    public function removerInstitucion(int $id, int $institucionId): JsonResponse
    {
        $convenio = Convenio::findOrFail($id);

        if (!$convenio->instituciones()->where('Institucion_id', $institucionId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'La institución no está asociada a este convenio'
            ], 422);
        }

        $convenio->instituciones()->detach($institucionId);

        return response()->json([
            'success' => true,
            'message' => 'Institución removida del convenio exitosamente'
        ]);
    }

    /**
     * Obtener datos para formularios
     */
    public function datosFormulario(): JsonResponse
    {
        $cacheKey = 'convenios_datos_formulario';

        $datos = Cache::remember($cacheKey, 600, function() {
            return [
                'tipos_convenio' => TipoConvenio::select('id', 'nombre_tipo', 'descripcion')
                    ->orderBy('nombre_tipo')
                    ->get(),
                'instituciones' => Institucion::select('id', 'nombre', 'ciudad_id')
                    ->activas()
                    ->with('ciudad:id,nombre_ciudad')
                    ->orderBy('nombre')
                    ->get()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $datos,
            'message' => 'Datos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de convenios
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_convenios';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Convenio::count(),
                'activos' => Convenio::activos()->count(),
                'vencidos' => Convenio::vencidos()->count(),
                'por_tipo' => TipoConvenio::withCount('convenios')->get(),
                'por_estado' => Convenio::selectRaw('estado, COUNT(*) as total')
                    ->groupBy('estado')
                    ->get(),
                'por_anio' => Convenio::selectRaw('EXTRACT(YEAR FROM fecha_ini) as anio, COUNT(*) as total')
                    ->groupBy('anio')
                    ->orderBy('anio', 'desc')
                    ->get(),
                'duracion_promedio' => Convenio::selectRaw('AVG(fecha_fin - fecha_ini) as duracion_promedio')
                    ->value('duracion_promedio'),
                'por_vencer' => Convenio::where('fecha_fin', '<=', now()->addDays(30))
                    ->where('fecha_fin', '>', now())
                    ->where('estado', 1)
                    ->count()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
