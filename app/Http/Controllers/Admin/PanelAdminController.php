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
use Illuminate\Support\Facades\Log;

class PanelAdminController extends Controller
{
    /**
     * Dashboard principal del administrador con estadísticas generales (alias para obtenerDashboard)
     */
    public function obtenerDashboard(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Dashboard principal del administrador con estadísticas generales
     */
    public function index(Request $request)
    {
        try {
            // Estadísticas de estudiantes
            $totalEstudiantes = Estudiante::count();
            // PostgreSQL es case-sensitive, usar el nombre correcto de la tabla en minúsculas
            $estudiantesPorEstado = DB::table('estudiante')
                ->select('estado_id', DB::raw('count(*) as total'))
                ->groupBy('estado_id')
                ->get()
                ->map(function ($item) {
                    $estado = DB::table('estado_estudiante')
                        ->where('id', $item->estado_id)
                        ->first();
                    return [
                        'estado_id' => $item->estado_id,
                        'estado_nombre' => $estado->nombre_estado ?? 'Sin estado',
                        'total' => $item->total
                    ];
                });

            // Estadísticas de docentes
            $totalDocentes = \App\Models\Docente::count();

            // Estadísticas de inscripciones
            $totalInscripciones = Inscripcion::count();
            $inscripcionesRecientes = Inscripcion::where('fecha', '>=', now()->subDays(30))->count();
            // PostgreSQL es case-sensitive, usar el nombre correcto de la tabla en minúsculas
            $inscripcionesPorPrograma = DB::table('inscripcion')
                ->select('programa_id', DB::raw('count(*) as total'))
                ->groupBy('programa_id')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    $programa = DB::table('programa')
                        ->where('id', $item->programa_id)
                        ->first();
                    return [
                        'programa' => $programa->nombre ?? 'Sin programa',
                        'total' => $item->total
                    ];
                });

            // Estadísticas de pagos
            $pagosPendientesVerificacion = Pago::where('verificado', false)->count();
            $montoPendienteVerificacion = Pago::where('verificado', false)->sum('monto') ?? 0;
            $pagosVerificadosMes = Pago::where('verificado', true)
                ->whereBetween('fecha_verificacion', [now()->startOfMonth(), now()->endOfMonth()])
                ->count();
            $montoRecaudadoMes = Pago::where('verificado', true)
                ->whereBetween('fecha_verificacion', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('monto') ?? 0;

            // Estadísticas de documentos
            $documentosPendientesValidacion = Documento::where('estado', 0)->count();
            // PostgreSQL es case-sensitive, usar el nombre correcto de la tabla en minúsculas
            $estudiantesConDocumentosPendientes = DB::table('estudiante')
                ->where('estado_id', 3)
                ->count();

            // Estadísticas de grupos
            $totalGrupos = Grupo::count();
            $gruposActivos = Grupo::where('fecha_fin', '>=', now())->count();

            // Estadísticas de programas
            $totalProgramas = Programa::count();

            // Actividad reciente (últimas 10 inscripciones) con manejo de nulls
            $actividadReciente = Inscripcion::with(['estudiante', 'programa'])
                ->orderBy('fecha', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($inscripcion) {
                    $estudianteNombre = 'Estudiante desconocido';
                    $programaNombre = 'Programa desconocido';

                    if ($inscripcion->estudiante) {
                        $estudianteNombre = ($inscripcion->estudiante->nombre ?? '') . ' ' . ($inscripcion->estudiante->apellido ?? '');
                        $estudianteNombre = trim($estudianteNombre) ?: 'Estudiante sin nombre';
                    }

                    if ($inscripcion->programa) {
                        $programaNombre = $inscripcion->programa->nombre ?? 'Programa sin nombre';
                    }

                    return [
                        'id' => $inscripcion->id,
                        'fecha' => $inscripcion->fecha ? $inscripcion->fecha->format('Y-m-d H:i:s') : null,
                        'mensaje' => "Nueva inscripción: {$estudianteNombre} en {$programaNombre}",
                        'titulo' => "Inscripción en {$programaNombre}",
                        'estudiante' => $estudianteNombre,
                        'programa' => $programaNombre,
                        'docente' => null
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Dashboard obtenido exitosamente',
                'data' => [
                    // Estructura para compatibilidad con frontend
                    'totales' => [
                        'estudiantes' => $totalEstudiantes,
                        'docentes' => $totalDocentes,
                        'programas' => $totalProgramas,
                        'grupos' => $totalGrupos
                    ],
                    'estudiantes' => [
                        'total' => $totalEstudiantes,
                        'por_estado' => $estudiantesPorEstado
                    ],
                    'docentes' => [
                        'total' => $totalDocentes
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
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        } catch (\Exception $e) {
            Log::error('Error en PanelAdminController::index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dashboard: ' . $e->getMessage(),
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Estadísticas de crecimiento mensual (alias para obtenerEstadisticasCrecimiento)
     */
    public function obtenerEstadisticasCrecimiento(Request $request)
    {
        return $this->getGrowthStats($request);
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
