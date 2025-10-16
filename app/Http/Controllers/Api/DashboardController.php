<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Institucion;
use App\Models\Convenio;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Resumen general del dashboard
     */
    public function resumen(): JsonResponse
    {
        $cacheKey = 'dashboard_resumen';

        $resumen = Cache::remember($cacheKey, 300, function() {
            return [
                'estudiantes' => [
                    'total' => Estudiante::count(),
                    'activos' => Estudiante::activos()->count(),
                    'nuevos_mes' => Estudiante::whereHas('inscripciones', function($q) {
                        $q->where('fecha', '>=', now()->subMonth());
                    })->count()
                ],
                'programas' => [
                    'total' => Programa::count(),
                    'activos' => Programa::activos()->count(),
                    'cursos' => Programa::where('duracion_meses', '<', 12)->count(),
                    'programas' => Programa::where('duracion_meses', '>=', 12)->count()
                ],
                'inscripciones' => [
                    'total' => Inscripcion::count(),
                    'mes_actual' => Inscripcion::whereMonth('fecha', now()->month)
                        ->whereYear('fecha', now()->year)
                        ->count(),
                    'mes_anterior' => Inscripcion::whereMonth('fecha', now()->subMonth()->month)
                        ->whereYear('fecha', now()->subMonth()->year)
                        ->count()
                ],
                'pagos' => [
                    'total_mes' => Pago::whereMonth('fecha', now()->month)
                        ->whereYear('fecha', now()->year)
                        ->sum('monto'),
                    'total_anterior' => Pago::whereMonth('fecha', now()->subMonth()->month)
                        ->whereYear('fecha', now()->subMonth()->year)
                        ->sum('monto'),
                    'cantidad_mes' => Pago::whereMonth('fecha', now()->month)
                        ->whereYear('fecha', now()->year)
                        ->count()
                ],
                'instituciones' => [
                    'total' => Institucion::count(),
                    'activas' => Institucion::activas()->count(),
                    'con_convenios' => Institucion::conConveniosActivos()->count()
                ],
                'grupos' => [
                    'activos' => Grupo::activos()->count(),
                    'finalizados_mes' => Grupo::whereMonth('fecha_fin', now()->month)
                        ->whereYear('fecha_fin', now()->year)
                        ->count()
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $resumen,
            'message' => 'Resumen obtenido exitosamente'
        ]);
    }

    /**
     * Datos para gráficos
     */
    public function graficos(): JsonResponse
    {
        $cacheKey = 'dashboard_graficos';

        $graficos = Cache::remember($cacheKey, 600, function() {
            return [
                'inscripciones_por_mes' => Inscripcion::selectRaw('DATE_TRUNC(\'month\', fecha) as mes, COUNT(*) as total')
                    ->where('fecha', '>=', now()->subMonths(12))
                    ->groupBy('mes')
                    ->orderBy('mes')
                    ->get(),
                'pagos_por_mes' => Pago::selectRaw('DATE_TRUNC(\'month\', fecha) as mes, SUM(monto) as total')
                    ->where('fecha', '>=', now()->subMonths(12))
                    ->groupBy('mes')
                    ->orderBy('mes')
                    ->get(),
                'estudiantes_por_programa' => Programa::withCount('inscripciones')
                    ->activos()
                    ->orderBy('inscripciones_count', 'desc')
                    ->limit(10)
                    ->get(),
                'ingresos_por_institucion' => Institucion::withCount('programas')
                    ->activas()
                    ->orderBy('programas_count', 'desc')
                    ->limit(10)
                    ->get(),
                'estudiantes_por_estado' => \App\Models\EstadoEstudiante::withCount('estudiantes')
                    ->get(),
                'programas_por_tipo' => \App\Models\TipoPrograma::withCount('programas')
                    ->get(),
                'convenios_por_estado' => [
                    'activos' => Convenio::activos()->count(),
                    'vencidos' => Convenio::vencidos()->count(),
                    'inactivos' => Convenio::where('estado', 0)->count()
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $graficos,
            'message' => 'Datos de gráficos obtenidos exitosamente'
        ]);
    }

    /**
     * Alertas del sistema
     */
    public function alertas(): JsonResponse
    {
        $cacheKey = 'dashboard_alertas';

        $alertas = Cache::remember($cacheKey, 300, function() {
            $alertas = [];

            // Cuotas vencidas
            $cuotasVencidas = \App\Models\Cuota::vencidas()->count();
            if ($cuotasVencidas > 0) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'titulo' => 'Cuotas Vencidas',
                    'mensaje' => "Hay {$cuotasVencidas} cuotas vencidas sin pagar",
                    'accion' => 'revisar_cuotas_vencidas'
                ];
            }

            // Convenios por vencer
            $conveniosPorVencer = Convenio::where('fecha_fin', '<=', now()->addDays(30))
                ->where('fecha_fin', '>', now())
                ->where('estado', 1)
                ->count();

            if ($conveniosPorVencer > 0) {
                $alertas[] = [
                    'tipo' => 'info',
                    'titulo' => 'Convenios por Vencer',
                    'mensaje' => "Hay {$conveniosPorVencer} convenios que vencen en los próximos 30 días",
                    'accion' => 'revisar_convenios'
                ];
            }

            // Grupos sin estudiantes
            $gruposSinEstudiantes = Grupo::activos()
                ->whereDoesntHave('estudiantes')
                ->count();

            if ($gruposSinEstudiantes > 0) {
                $alertas[] = [
                    'tipo' => 'warning',
                    'titulo' => 'Grupos sin Estudiantes',
                    'mensaje' => "Hay {$gruposSinEstudiantes} grupos activos sin estudiantes inscritos",
                    'accion' => 'revisar_grupos'
                ];
            }

            // Inscripciones sin plan de pagos
            $inscripcionesSinPlan = Inscripcion::whereDoesntHave('planPagos')
                ->where('fecha', '>=', now()->subDays(7))
                ->count();

            if ($inscripcionesSinPlan > 0) {
                $alertas[] = [
                    'tipo' => 'info',
                    'titulo' => 'Inscripciones sin Plan de Pagos',
                    'mensaje' => "Hay {$inscripcionesSinPlan} inscripciones recientes sin plan de pagos",
                    'accion' => 'revisar_inscripciones'
                ];
            }

            return $alertas;
        });

        return response()->json([
            'success' => true,
            'data' => $alertas,
            'message' => 'Alertas obtenidas exitosamente'
        ]);
    }

    /**
     * Actividad reciente del sistema
     */
    public function actividadReciente(): JsonResponse
    {
        $cacheKey = 'dashboard_actividad_reciente';

        $actividad = Cache::remember($cacheKey, 300, function() {
            $actividad = [];

            // Inscripciones recientes
            $inscripcionesRecientes = Inscripcion::with([
                'estudiante:id,ci,nombre,apellido',
                'programa:id,nombre'
            ])
            ->latest()
            ->limit(5)
            ->get();

            foreach ($inscripcionesRecientes as $inscripcion) {
                $actividad[] = [
                    'tipo' => 'inscripcion',
                    'fecha' => $inscripcion->fecha,
                    'descripcion' => "Nueva inscripción: {$inscripcion->estudiante->nombre} {$inscripcion->estudiante->apellido} en {$inscripcion->programa->nombre}",
                    'usuario' => $inscripcion->estudiante->nombre_completo
                ];
            }

            // Pagos recientes
            $pagosRecientes = Pago::with([
                'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
                'cuota.planPagos.inscripcion.programa:id,nombre'
            ])
            ->latest()
            ->limit(5)
            ->get();

            foreach ($pagosRecientes as $pago) {
                $actividad[] = [
                    'tipo' => 'pago',
                    'fecha' => $pago->fecha,
                    'descripcion' => "Pago registrado: Bs. {$pago->monto} de {$pago->cuota->planPagos->inscripcion->estudiante->nombre} {$pago->cuota->planPagos->inscripcion->estudiante->apellido}",
                    'monto' => $pago->monto
                ];
            }

            // Ordenar por fecha descendente
            usort($actividad, function($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });

            return array_slice($actividad, 0, 10);
        });

        return response()->json([
            'success' => true,
            'data' => $actividad,
            'message' => 'Actividad reciente obtenida exitosamente'
        ]);
    }
}
