<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Programa;
use App\Models\Grupo;
use App\Models\Docente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard para estudiantes
     */
    public function estudiante(Request $request): JsonResponse
    {
        try {
            $estudianteId = $request->user()->id;

            $estadisticas = [
                'inscripciones' => [
                    'total' => Inscripcion::where('estudiante_id', $estudianteId)->count(),
                    'pendientes' => Inscripcion::where('estudiante_id', $estudianteId)
                                               ->where('estado', 'pendiente')->count(),
                    'aprobadas' => Inscripcion::where('estudiante_id', $estudianteId)
                                             ->where('estado', 'aprobada')->count()
                ],
                'pagos' => [
                    'total' => Pago::whereHas('inscripcion', function($q) use ($estudianteId) {
                        $q->where('estudiante_id', $estudianteId);
                    })->count(),
                    'pendientes' => Pago::whereHas('inscripcion', function($q) use ($estudianteId) {
                        $q->where('estudiante_id', $estudianteId);
                    })->where('estado', 'pendiente')->count(),
                    'verificados' => Pago::whereHas('inscripcion', function($q) use ($estudianteId) {
                        $q->where('estudiante_id', $estudianteId);
                    })->where('estado', 'verificado')->count()
                ],
                'documentos' => [
                    'total' => 0, // Implementar cuando se cree el modelo Documento
                    'pendientes' => 0,
                    'aprobados' => 0
                ]
            ];

            // Inscripciones recientes
            $inscripcionesRecientes = Inscripcion::with(['programa'])
                ->where('estudiante_id', $estudianteId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            // Pagos recientes
            $pagosRecientes = Pago::with(['inscripcion.programa'])
                ->whereHas('inscripcion', function($q) use ($estudianteId) {
                    $q->where('estudiante_id', $estudianteId);
                })
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'estadisticas' => $estadisticas,
                    'inscripciones_recientes' => $inscripcionesRecientes,
                    'pagos_recientes' => $pagosRecientes
                ],
                'message' => 'Dashboard del estudiante obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dashboard del estudiante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard para administradores
     */
    public function admin(Request $request): JsonResponse
    {
        try {
            $estadisticas = [
                'estudiantes' => [
                    'total' => Estudiante::count(),
                    'nuevos_este_mes' => Estudiante::where('created_at', '>=', now()->startOfMonth())->count(),
                    'activos' => Estudiante::whereHas('estado', function($q) {
                        $q->where('nombre', 'Activo');
                    })->count()
                ],
                'inscripciones' => [
                    'total' => Inscripcion::count(),
                    'pendientes' => Inscripcion::where('estado', 'pendiente')->count(),
                    'aprobadas' => Inscripcion::where('estado', 'aprobada')->count(),
                    'rechazadas' => Inscripcion::where('estado', 'rechazada')->count(),
                    'este_mes' => Inscripcion::where('created_at', '>=', now()->startOfMonth())->count()
                ],
                'pagos' => [
                    'total' => Pago::count(),
                    'monto_total' => Pago::sum('monto'),
                    'pendientes' => Pago::where('estado', 'pendiente')->count(),
                    'verificados' => Pago::where('estado', 'verificado')->count(),
                    'monto_este_mes' => Pago::where('created_at', '>=', now()->startOfMonth())->sum('monto')
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
                ],
                'docentes' => [
                    'total' => Docente::count(),
                    'activos' => Docente::where('estado', 'activo')->count()
                ]
            ];

            // Inscripciones pendientes de aprobación
            $inscripcionesPendientes = Inscripcion::with(['estudiante', 'programa'])
                ->where('estado', 'pendiente')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Pagos pendientes de verificación
            $pagosPendientes = Pago::with(['inscripcion.estudiante', 'inscripcion.programa'])
                ->where('estado', 'pendiente')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            // Programas más populares
            $programasPopulares = Programa::withCount('inscripciones')
                ->orderBy('inscripciones_count', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'estadisticas' => $estadisticas,
                    'inscripciones_pendientes' => $inscripcionesPendientes,
                    'pagos_pendientes' => $pagosPendientes,
                    'programas_populares' => $programasPopulares
                ],
                'message' => 'Dashboard del administrador obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dashboard del administrador: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dashboard para docentes
     */
    public function docente(Request $request): JsonResponse
    {
        try {
            $docenteId = $request->user()->id;

            $estadisticas = [
                'grupos' => [
                    'total' => Grupo::where('docente_id', $docenteId)->count(),
                    'activos' => Grupo::where('docente_id', $docenteId)
                                      ->where('estado', 'activo')->count(),
                    'completados' => Grupo::where('docente_id', $docenteId)
                                          ->where('estado', 'completado')->count()
                ],
                'estudiantes' => [
                    'total' => DB::table('grupo_estudiante')
                        ->join('grupos', 'grupo_estudiante.grupo_id', '=', 'grupos.id')
                        ->where('grupos.docente_id', $docenteId)
                        ->count(),
                    'por_grupo' => Grupo::where('docente_id', $docenteId)
                        ->withCount('estudiantes')
                        ->get()
                        ->pluck('estudiantes_count')
                        ->sum()
                ],
                'clases' => [
                    'esta_semana' => 0, // Implementar cuando se cree el modelo Horario
                    'proximas' => 0
                ]
            ];

            // Grupos asignados
            $gruposAsignados = Grupo::with(['programa', 'estudiantes'])
                ->where('docente_id', $docenteId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Estudiantes por grupo
            $estudiantesPorGrupo = [];
            foreach ($gruposAsignados as $grupo) {
                $estudiantesPorGrupo[] = [
                    'grupo' => $grupo->nombre,
                    'programa' => $grupo->programa->nombre,
                    'estudiantes' => $grupo->estudiantes->count(),
                    'capacidad' => $grupo->capacidad_maxima
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'estadisticas' => $estadisticas,
                    'grupos_asignados' => $gruposAsignados,
                    'estudiantes_por_grupo' => $estudiantesPorGrupo
                ],
                'message' => 'Dashboard del docente obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dashboard del docente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas generales del sistema
     */
    public function estadisticasGenerales(): JsonResponse
    {
        try {
            $estadisticas = [
                'estudiantes' => Estudiante::count(),
                'inscripciones' => Inscripcion::count(),
                'pagos' => Pago::count(),
                'programas' => Programa::count(),
                'grupos' => Grupo::count(),
                'docentes' => Docente::count(),
                'monto_total_pagos' => Pago::sum('monto'),
                'inscripciones_este_mes' => Inscripcion::where('created_at', '>=', now()->startOfMonth())->count(),
                'pagos_este_mes' => Pago::where('created_at', '>=', now()->startOfMonth())->count(),
                'monto_este_mes' => Pago::where('created_at', '>=', now()->startOfMonth())->sum('monto')
            ];

            return response()->json([
                'success' => true,
                'data' => $estadisticas,
                'message' => 'Estadísticas generales obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas generales: ' . $e->getMessage()
            ], 500);
        }
    }
}
