<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Programa;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    /**
     * Generar reporte de estudiantes
     */
    public function estudiantes(Request $request): JsonResponse
    {
        try {
            $query = Estudiante::with(['estado', 'provincia']);

            // Filtros
            if ($request->has('estado_id')) {
                $query->where('estado_id', $request->estado_id);
            }

            if ($request->has('provincia_id')) {
                $query->where('provincia_id', $request->provincia_id);
            }

            if ($request->has('fecha_desde')) {
                $query->where('created_at', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->where('created_at', '<=', $request->fecha_hasta);
            }

            $estudiantes = $query->get();

            // Estadísticas
            $estadisticas = [
                'total' => $estudiantes->count(),
                'por_estado' => $estudiantes->groupBy('estado.nombre')->map->count(),
                'por_provincia' => $estudiantes->groupBy('provincia.nombre')->map->count(),
                'nuevos_este_mes' => $estudiantes->where('created_at', '>=', now()->startOfMonth())->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'estudiantes' => $estudiantes,
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de estudiantes generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de estudiantes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de inscripciones
     */
    public function inscripciones(Request $request): JsonResponse
    {
        try {
            $query = Inscripcion::with(['estudiante', 'programa']);

            // Filtros
            if ($request->has('programa_id')) {
                $query->where('programa_id', $request->programa_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('fecha_desde')) {
                $query->where('fecha_inscripcion', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->where('fecha_inscripcion', '<=', $request->fecha_hasta);
            }

            $inscripciones = $query->get();

            // Estadísticas
            $estadisticas = [
                'total' => $inscripciones->count(),
                'por_programa' => $inscripciones->groupBy('programa.nombre')->map->count(),
                'por_estado' => $inscripciones->groupBy('estado')->map->count(),
                'monto_total' => $inscripciones->sum('monto'),
                'promedio_monto' => $inscripciones->avg('monto')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'inscripciones' => $inscripciones,
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de inscripciones generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de inscripciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de pagos
     */
    public function pagos(Request $request): JsonResponse
    {
        try {
            $query = Pago::with(['inscripcion.estudiante', 'inscripcion.programa']);

            // Filtros
            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('metodo_pago')) {
                $query->where('metodo_pago', $request->metodo_pago);
            }

            if ($request->has('fecha_desde')) {
                $query->where('fecha_pago', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->where('fecha_pago', '<=', $request->fecha_hasta);
            }

            $pagos = $query->get();

            // Estadísticas
            $estadisticas = [
                'total' => $pagos->count(),
                'monto_total' => $pagos->sum('monto'),
                'por_estado' => $pagos->groupBy('estado')->map->count(),
                'por_metodo' => $pagos->groupBy('metodo_pago')->map->count(),
                'promedio_monto' => $pagos->avg('monto')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'pagos' => $pagos,
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de pagos generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de pagos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de programas
     */
    public function programas(Request $request): JsonResponse
    {
        try {
            $query = Programa::with(['ramaAcademica', 'tipoPrograma']);

            // Filtros
            if ($request->has('rama_academica_id')) {
                $query->where('rama_academica_id', $request->rama_academica_id);
            }

            if ($request->has('tipo_programa_id')) {
                $query->where('tipo_programa_id', $request->tipo_programa_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            $programas = $query->get();

            // Estadísticas
            $estadisticas = [
                'total' => $programas->count(),
                'por_rama' => $programas->groupBy('ramaAcademica.nombre')->map->count(),
                'por_tipo' => $programas->groupBy('tipoPrograma.nombre')->map->count(),
                'por_estado' => $programas->groupBy('estado')->map->count(),
                'precio_promedio' => $programas->avg('precio')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'programas' => $programas,
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de programas generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de programas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de grupos
     */
    public function grupos(Request $request): JsonResponse
    {
        try {
            $query = Grupo::with(['programa', 'docente', 'estudiantes']);

            // Filtros
            if ($request->has('programa_id')) {
                $query->where('programa_id', $request->programa_id);
            }

            if ($request->has('docente_id')) {
                $query->where('docente_id', $request->docente_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            $grupos = $query->get();

            // Estadísticas
            $estadisticas = [
                'total' => $grupos->count(),
                'por_programa' => $grupos->groupBy('programa.nombre')->map->count(),
                'por_docente' => $grupos->groupBy('docente.nombre')->map->count(),
                'por_estado' => $grupos->groupBy('estado')->map->count(),
                'capacidad_promedio' => $grupos->avg('capacidad_maxima'),
                'ocupacion_promedio' => $grupos->avg(function($grupo) {
                    return $grupo->estudiantes->count();
                })
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'grupos' => $grupos,
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de grupos generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte general del sistema
     */
    public function general(Request $request): JsonResponse
    {
        try {
            $estadisticas = [
                'estudiantes' => [
                    'total' => Estudiante::count(),
                    'activos' => Estudiante::whereHas('estado', function($q) {
                        $q->where('nombre', 'Activo');
                    })->count(),
                    'nuevos_este_mes' => Estudiante::where('created_at', '>=', now()->startOfMonth())->count()
                ],
                'inscripciones' => [
                    'total' => Inscripcion::count(),
                    'pendientes' => Inscripcion::where('estado', 'pendiente')->count(),
                    'aprobadas' => Inscripcion::where('estado', 'aprobada')->count(),
                    'rechazadas' => Inscripcion::where('estado', 'rechazada')->count()
                ],
                'pagos' => [
                    'total' => Pago::count(),
                    'monto_total' => Pago::sum('monto'),
                    'pendientes' => Pago::where('estado', 'pendiente')->count(),
                    'verificados' => Pago::where('estado', 'verificado')->count()
                ],
                'programas' => [
                    'total' => Programa::count(),
                    'activos' => Programa::where('estado', 'activo')->count(),
                    'inactivos' => Programa::where('estado', 'inactivo')->count()
                ],
                'grupos' => [
                    'total' => Grupo::count(),
                    'activos' => Grupo::where('estado', 'activo')->count(),
                    'completados' => Grupo::where('estado', 'completado')->count()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $estadisticas,
                'message' => 'Reporte general generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte general: ' . $e->getMessage()
            ], 500);
        }
    }
}
