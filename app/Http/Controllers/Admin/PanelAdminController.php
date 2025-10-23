<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Documento;
use App\Models\Grupo;
use App\Models\Programa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelAdminController extends Controller
{
    /**
     * Dashboard principal del administrador con estadísticas generales
     */
    public function index(Request $request)
    {
        try {
            // Estadísticas de estudiantes
            $totalEstudiantes = Estudiante::count();
            $estudiantesPorEstado = Estudiante::select('Estado_id', DB::raw('count(*) as total'))
                ->groupBy('Estado_id')
                ->with('estado')
                ->get()
                ->map(function ($item) {
                    return [
                        'estado_id' => $item->Estado_id,
                        'estado_nombre' => $item->estado->nombre_estado ?? 'Sin estado',
                        'total' => $item->total
                    ];
                });

            // Estadísticas de inscripciones
            $totalInscripciones = Inscripcion::count();
            $inscripcionesRecientes = Inscripcion::where('fecha', '>=', now()->subDays(30))->count();
            $inscripcionesPorPrograma = Inscripcion::select('Programa_id', DB::raw('count(*) as total'))
                ->groupBy('Programa_id')
                ->with('programa')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'programa' => $item->programa->nombre ?? 'Sin programa',
                        'total' => $item->total
                    ];
                });

            // Estadísticas de pagos
            $pagosPendientesVerificacion = Pago::where('verificado', false)->count();
            $montoPendienteVerificacion = Pago::where('verificado', false)->sum('monto');
            $pagosVerificadosMes = Pago::where('verificado', true)
                ->whereBetween('fecha_verificacion', [now()->startOfMonth(), now()->endOfMonth()])
                ->count();
            $montoRecaudadoMes = Pago::where('verificado', true)
                ->whereBetween('fecha_verificacion', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('monto');

            // Estadísticas de documentos
            $documentosPendientesValidacion = Documento::where('estado', 0)->count();
            $estudiantesConDocumentosPendientes = Estudiante::where('Estado_id', 3)->count();

            // Estadísticas de grupos
            $totalGrupos = Grupo::count();
            $gruposActivos = Grupo::where('fecha_fin', '>=', now())->count();

            // Estadísticas de programas
            $totalProgramas = Programa::count();

            // Actividad reciente (últimas 10 inscripciones)
            $actividadReciente = Inscripcion::with(['estudiante', 'programa'])
                ->orderBy('fecha', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($inscripcion) {
                    return [
                        'id' => $inscripcion->id,
                        'fecha' => $inscripcion->fecha,
                        'estudiante' => $inscripcion->estudiante->nombre . ' ' . $inscripcion->estudiante->apellido,
                        'programa' => $inscripcion->programa->nombre
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Dashboard obtenido exitosamente',
                'data' => [
                    'estudiantes' => [
                        'total' => $totalEstudiantes,
                        'por_estado' => $estudiantesPorEstado
                    ],
                    'inscripciones' => [
                        'total' => $totalInscripciones,
                        'recientes_30_dias' => $inscripcionesRecientes,
                        'top_programas' => $inscripcionesPorPrograma
                    ],
                    'pagos' => [
                        'pendientes_verificacion' => $pagosPendientesVerificacion,
                        'monto_pendiente_verificacion' => $montoPendienteVerificacion,
                        'verificados_mes_actual' => $pagosVerificadosMes,
                        'monto_recaudado_mes_actual' => $montoRecaudadoMes
                    ],
                    'documentos' => [
                        'pendientes_validacion' => $documentosPendientesValidacion,
                        'estudiantes_con_documentos_pendientes' => $estudiantesConDocumentosPendientes
                    ],
                    'grupos' => [
                        'total' => $totalGrupos,
                        'activos' => $gruposActivos
                    ],
                    'programas' => [
                        'total' => $totalProgramas
                    ],
                    'actividad_reciente' => $actividadReciente
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estadísticas de crecimiento mensual
     */
    public function getGrowthStats(Request $request)
    {
        try {
            $meses = $request->input('meses', 6);

            $estadisticasMensuales = [];

            for ($i = $meses - 1; $i >= 0; $i--) {
                $fechaInicio = now()->subMonths($i)->startOfMonth();
                $fechaFin = now()->subMonths($i)->endOfMonth();

                $estadisticasMensuales[] = [
                    'mes' => $fechaInicio->format('Y-m'),
                    'mes_nombre' => $fechaInicio->translatedFormat('F Y'),
                    'nuevos_estudiantes' => Estudiante::whereBetween('created_at', [$fechaInicio, $fechaFin])->count(),
                    'nuevas_inscripciones' => Inscripcion::whereBetween('fecha', [$fechaInicio, $fechaFin])->count(),
                    'pagos_verificados' => Pago::where('verificado', true)
                        ->whereBetween('fecha_verificacion', [$fechaInicio, $fechaFin])
                        ->count(),
                    'monto_recaudado' => Pago::where('verificado', true)
                        ->whereBetween('fecha_verificacion', [$fechaInicio, $fechaFin])
                        ->sum('monto')
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas de crecimiento obtenidas exitosamente',
                'data' => $estadisticasMensuales
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de crecimiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
