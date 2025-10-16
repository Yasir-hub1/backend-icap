<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Institucion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReporteController extends Controller
{
    /**
     * Reporte de estudiantes por programa
     */
    public function estudiantesPorPrograma(Request $request): JsonResponse
    {
        $filtros = $request->only(['programa_id', 'fecha_desde', 'fecha_hasta']);

        $cacheKey = 'reporte_estudiantes_programa_' . md5(serialize($filtros));

        $reporte = Cache::remember($cacheKey, 300, function() use ($filtros) {
            $query = Programa::withCount('inscripciones')
                ->with(['tipoPrograma:id,nombre', 'institucion:id,nombre']);

            if (isset($filtros['programa_id'])) {
                $query->where('id', $filtros['programa_id']);
            }

            $programas = $query->activos()->get();

            $detalle = [];
            foreach ($programas as $programa) {
                $inscripcionesQuery = $programa->inscripciones()
                    ->with(['estudiante:id,ci,nombre,apellido']);

                if (isset($filtros['fecha_desde'])) {
                    $inscripcionesQuery->where('fecha', '>=', $filtros['fecha_desde']);
                }

                if (isset($filtros['fecha_hasta'])) {
                    $inscripcionesQuery->where('fecha', '<=', $filtros['fecha_hasta']);
                }

                $detalle[] = [
                    'programa' => $programa,
                    'estudiantes' => $inscripcionesQuery->get()
                ];
            }

            return [
                'resumen' => [
                    'total_programas' => $programas->count(),
                    'total_estudiantes' => $programas->sum('inscripciones_count'),
                    'promedio_estudiantes' => $programas->avg('inscripciones_count')
                ],
                'detalle' => $detalle
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $reporte,
            'message' => 'Reporte generado exitosamente'
        ]);
    }

    /**
     * Reporte de pagos por período
     */
    public function pagosPorPeriodo(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde'
        ]);

        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');

        $cacheKey = "reporte_pagos_periodo_{$fechaDesde}_{$fechaHasta}";

        $reporte = Cache::remember($cacheKey, 300, function() use ($fechaDesde, $fechaHasta) {
            $pagos = Pago::with([
                'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
                'cuota.planPagos.inscripcion.programa:id,nombre',
                'cuota.planPagos.inscripcion.programa.institucion:id,nombre'
            ])
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->orderBy('fecha')
            ->get();

            $resumen = [
                'total_pagos' => $pagos->count(),
                'monto_total' => $pagos->sum('monto'),
                'por_institucion' => $pagos->groupBy('cuota.planPagos.inscripcion.programa.institucion.nombre')
                    ->map(function($grupo) {
                        return [
                            'cantidad' => $grupo->count(),
                            'monto' => $grupo->sum('monto')
                        ];
                    }),
                'por_programa' => $pagos->groupBy('cuota.planPagos.inscripcion.programa.nombre')
                    ->map(function($grupo) {
                        return [
                            'cantidad' => $grupo->count(),
                            'monto' => $grupo->sum('monto')
                        ];
                    }),
                'por_dia' => $pagos->groupBy(function($pago) {
                    return $pago->fecha->format('Y-m-d');
                })->map(function($grupo) {
                    return [
                        'cantidad' => $grupo->count(),
                        'monto' => $grupo->sum('monto')
                    ];
                })
            ];

            return [
                'resumen' => $resumen,
                'detalle' => $pagos
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $reporte,
            'message' => 'Reporte generado exitosamente'
        ]);
    }

    /**
     * Reporte de ingresos por institución
     */
    public function ingresosPorInstitucion(Request $request): JsonResponse
    {
        $filtros = $request->only(['institucion_id', 'fecha_desde', 'fecha_hasta']);

        $cacheKey = 'reporte_ingresos_institucion_' . md5(serialize($filtros));

        $reporte = Cache::remember($cacheKey, 300, function() use ($filtros) {
            $query = Institucion::with(['programas.inscripciones.planPagos.cuotas.pagos']);

            if (isset($filtros['institucion_id'])) {
                $query->where('id', $filtros['institucion_id']);
            }

            $instituciones = $query->activas()->get();

            $detalle = [];
            foreach ($instituciones as $institucion) {
                $ingresos = 0;
                $totalPagos = 0;

                foreach ($institucion->programas as $programa) {
                    foreach ($programa->inscripciones as $inscripcion) {
                        if ($inscripcion->planPagos) {
                            foreach ($inscripcion->planPagos->cuotas as $cuota) {
                                $pagosQuery = $cuota->pagos();

                                if (isset($filtros['fecha_desde'])) {
                                    $pagosQuery->where('fecha', '>=', $filtros['fecha_desde']);
                                }

                                if (isset($filtros['fecha_hasta'])) {
                                    $pagosQuery->where('fecha', '<=', $filtros['fecha_hasta']);
                                }

                                $pagos = $pagosQuery->get();
                                $ingresos += $pagos->sum('monto');
                                $totalPagos += $pagos->count();
                            }
                        }
                    }
                }

                $detalle[] = [
                    'institucion' => $institucion,
                    'ingresos' => $ingresos,
                    'total_pagos' => $totalPagos,
                    'programas' => $institucion->programas->count()
                ];
            }

            $resumen = [
                'total_instituciones' => $instituciones->count(),
                'ingresos_totales' => collect($detalle)->sum('ingresos'),
                'pagos_totales' => collect($detalle)->sum('total_pagos'),
                'promedio_ingresos' => collect($detalle)->avg('ingresos')
            ];

            return [
                'resumen' => $resumen,
                'detalle' => $detalle
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $reporte,
            'message' => 'Reporte generado exitosamente'
        ]);
    }

    /**
     * Estadísticas generales del sistema
     */
    public function estadisticasGenerales(): JsonResponse
    {
        $cacheKey = 'estadisticas_generales';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'estudiantes' => [
                    'total' => Estudiante::count(),
                    'activos' => Estudiante::activos()->count(),
                    'con_inscripciones' => Estudiante::conInscripciones()->count()
                ],
                'programas' => [
                    'total' => Programa::count(),
                    'activos' => Programa::activos()->count(),
                    'cursos' => Programa::where('duracion_meses', '<', 12)->count(),
                    'programas' => Programa::where('duracion_meses', '>=', 12)->count()
                ],
                'inscripciones' => [
                    'total' => Inscripcion::count(),
                    'recientes' => Inscripcion::recientes()->count(),
                    'con_plan_pagos' => Inscripcion::whereHas('planPagos')->count()
                ],
                'pagos' => [
                    'total' => Pago::count(),
                    'monto_total' => Pago::sum('monto'),
                    'recientes' => Pago::recientes()->count(),
                    'monto_reciente' => Pago::recientes()->sum('monto')
                ],
                'instituciones' => [
                    'total' => Institucion::count(),
                    'activas' => Institucion::activas()->count(),
                    'con_programas' => Institucion::whereHas('programas')->count()
                ],
                'tendencias' => [
                    'inscripciones_mes' => Inscripcion::selectRaw('DATE_TRUNC(\'month\', fecha) as mes, COUNT(*) as total')
                        ->where('fecha', '>=', now()->subMonths(12))
                        ->groupBy('mes')
                        ->orderBy('mes')
                        ->get(),
                    'pagos_mes' => Pago::selectRaw('DATE_TRUNC(\'month\', fecha) as mes, COUNT(*) as cantidad, SUM(monto) as total')
                        ->where('fecha', '>=', now()->subMonths(12))
                        ->groupBy('mes')
                        ->orderBy('mes')
                        ->get()
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }

    /**
     * Exportar reporte
     */
    public function exportar(Request $request, string $tipo): JsonResponse
    {
        $request->validate([
            'formato' => 'required|in:pdf,excel,csv',
            'filtros' => 'nullable|array'
        ]);

        $filtros = $request->get('filtros', []);
        $formato = $request->get('formato');

        // Aquí se implementaría la lógica de exportación
        // Por ahora retornamos un mensaje indicando que está en desarrollo

        return response()->json([
            'success' => true,
            'message' => "Exportación de {$tipo} en formato {$formato} programada. El archivo estará disponible en breve.",
            'data' => [
                'tipo' => $tipo,
                'formato' => $formato,
                'filtros' => $filtros,
                'status' => 'processing'
            ]
        ]);
    }
}
