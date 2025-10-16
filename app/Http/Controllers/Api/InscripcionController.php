<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Descuento;
use App\Models\PlanPagos;
use App\Models\Cuota;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InscripcionController extends Controller
{
    /**
     * Listar inscripciones con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Inscripcion::with([
            'estudiante:id,ci,nombre,apellido,registro_estudiante',
            'programa:id,nombre,duracion_meses,costo',
            'programa.tipoPrograma:id,nombre',
            'programa.institucion:id,nombre',
            'descuento:id,nombre,descuento',
            'planPagos:id,monto_total,total_cuotas'
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->whereHas('estudiante', function($q) use ($buscar) {
                $q->where('ci', 'ILIKE', "%{$buscar}%")
                  ->orWhere('nombre', 'ILIKE', "%{$buscar}%")
                  ->orWhere('apellido', 'ILIKE', "%{$buscar}%")
                  ->orWhere('registro_estudiante', 'ILIKE', "%{$buscar}%");
            });
        }

        if ($request->filled('programa_id')) {
            $query->where('Programa_id', $request->get('programa_id'));
        }

        if ($request->filled('estudiante_id')) {
            $query->where('Estudiante_id', $request->get('estudiante_id'));
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->get('fecha_hasta'));
        }

        if ($request->filled('con_plan_pagos')) {
            if ($request->boolean('con_plan_pagos')) {
                $query->whereHas('planPagos');
            } else {
                $query->whereDoesntHave('planPagos');
            }
        }

        // Ordenamiento
        $query->latest('fecha');

        // Paginación con caché
        $cacheKey = 'inscripciones_' . md5(serialize($request->all()));

        $inscripciones = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $inscripciones,
            'message' => 'Inscripciones obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener inscripción específica con todas sus relaciones
     */
    public function show(int $id): JsonResponse
    {
        $inscripcion = Inscripcion::with([
            'estudiante:id,ci,nombre,apellido,registro_estudiante,celular',
            'programa:id,nombre,duracion_meses,costo',
            'programa.tipoPrograma:id,nombre',
            'programa.ramaAcademica:id,nombre',
            'programa.institucion:id,nombre',
            'descuento:id,nombre,descuento',
            'planPagos.cuotas' => function($query) {
                $query->with('pagos')->orderBy('fecha_ini');
            }
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $inscripcion,
            'message' => 'Inscripción obtenida exitosamente'
        ]);
    }

    /**
     * Crear nueva inscripción
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'Programa_id' => 'required|exists:Programa,id',
            'Estudiante_id' => 'required|exists:Estudiante,id',
            'Descuento_id' => 'nullable|exists:Descuento,id',
            'plan_pagos' => 'nullable|array',
            'plan_pagos.total_cuotas' => 'required_with:plan_pagos|integer|min:1',
            'plan_pagos.cuotas' => 'required_with:plan_pagos|array|min:1',
            'plan_pagos.cuotas.*.fecha_ini' => 'required|date',
            'plan_pagos.cuotas.*.fecha_fin' => 'required|date|after:plan_pagos.cuotas.*.fecha_ini',
            'plan_pagos.cuotas.*.monto' => 'required|numeric|min:0'
        ]);

        DB::beginTransaction();
        try {
            // Verificar que el estudiante no esté ya inscrito en el programa
            $inscripcionExistente = Inscripcion::where('Estudiante_id', $request->get('Estudiante_id'))
                ->where('Programa_id', $request->get('Programa_id'))
                ->exists();

            if ($inscripcionExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante ya está inscrito en este programa'
                ], 422);
            }

            // Crear inscripción
            $inscripcion = Inscripcion::create([
                'Programa_id' => $request->get('Programa_id'),
                'Estudiante_id' => $request->get('Estudiante_id'),
                'Descuento_id' => $request->get('Descuento_id'),
                'fecha' => now()
            ]);

            // Crear plan de pagos si se proporciona
            if ($request->has('plan_pagos')) {
                $planPagosData = $request->get('plan_pagos');
                $montoTotal = collect($planPagosData['cuotas'])->sum('monto');

                $planPagos = PlanPagos::create([
                    'monto_total' => $montoTotal,
                    'total_cuotas' => $planPagosData['total_cuotas'],
                    'Inscripcion_id' => $inscripcion->id
                ]);

                // Crear cuotas
                foreach ($planPagosData['cuotas'] as $cuotaData) {
                    Cuota::create([
                        'fecha_ini' => $cuotaData['fecha_ini'],
                        'fecha_fin' => $cuotaData['fecha_fin'],
                        'monto' => $cuotaData['monto'],
                        'plan_pagos_id' => $planPagos->id
                    ]);
                }
            }

            // Limpiar caché
            Cache::forget('inscripciones_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $inscripcion->load([
                    'estudiante:id,ci,nombre,apellido',
                    'programa:id,nombre',
                    'descuento:id,nombre,descuento',
                    'planPagos.cuotas'
                ]),
                'message' => 'Inscripción creada exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar inscripción
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $inscripcion = Inscripcion::findOrFail($id);

        $request->validate([
            'Descuento_id' => 'nullable|exists:Descuento,id'
        ]);

        $inscripcion->update($request->validated());

        // Limpiar caché
        Cache::forget('inscripciones_*');

        return response()->json([
            'success' => true,
            'data' => $inscripcion->load([
                'estudiante:id,ci,nombre,apellido',
                'programa:id,nombre',
                'descuento:id,nombre,descuento'
            ]),
            'message' => 'Inscripción actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar inscripción
     */
    public function destroy(int $id): JsonResponse
    {
        $inscripcion = Inscripcion::findOrFail($id);

        // Verificar si tiene pagos realizados
        if ($inscripcion->pagos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la inscripción porque tiene pagos realizados'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Eliminar plan de pagos y cuotas si existen
            if ($inscripcion->planPagos) {
                $inscripcion->planPagos->cuotas()->delete();
                $inscripcion->planPagos()->delete();
            }

            $inscripcion->delete();

            // Limpiar caché
            Cache::forget('inscripciones_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscripción eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos para formularios
     */
    public function datosFormulario(): JsonResponse
    {
        $cacheKey = 'inscripciones_datos_formulario';

        $datos = Cache::remember($cacheKey, 600, function() {
            return [
                'programas' => Programa::select('id', 'nombre', 'costo')
                    ->activos()
                    ->orderBy('nombre')
                    ->get(),
                'descuentos' => Descuento::select('id', 'nombre', 'descuento')
                    ->activos()
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
     * Obtener estadísticas de inscripciones
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_inscripciones';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Inscripcion::count(),
                'recientes' => Inscripcion::recientes()->count(),
                'con_plan_pagos' => Inscripcion::whereHas('planPagos')->count(),
                'sin_plan_pagos' => Inscripcion::whereDoesntHave('planPagos')->count(),
                'por_programa' => Programa::withCount('inscripciones')->activos()->get(),
                'por_mes' => Inscripcion::selectRaw('DATE_TRUNC(\'month\', fecha) as mes, COUNT(*) as total')
                    ->where('fecha', '>=', now()->subYear())
                    ->groupBy('mes')
                    ->orderBy('mes')
                    ->get(),
                'ingresos_totales' => Inscripcion::with('descuento', 'programa')
                    ->get()
                    ->sum('costo_final')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
