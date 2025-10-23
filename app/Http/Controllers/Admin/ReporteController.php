<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    /**
     * Reporte de estudiantes con filtros
     */
    public function students(Request $request)
    {
        try {
            $estadoId = $request->input('estado_id');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            $query = Estudiante::with('estado');

            if ($estadoId) {
                $query->where('Estado_id', $estadoId);
            }

            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('created_at', [$fechaInicio, $fechaFin]);
            }

            $estudiantes = $query->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($estudiante) {
                    return [
                        'id' => $estudiante->id,
                        'registro' => $estudiante->registro_estudiante,
                        'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'celular' => $estudiante->celular,
                        'estado' => $estudiante->estado->nombre_estado ?? '',
                        'fecha_registro' => $estudiante->created_at,
                        'total_inscripciones' => $estudiante->inscripciones()->count()
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Reporte de estudiantes generado exitosamente',
                'data' => [
                    'estudiantes' => $estudiantes,
                    'total' => $estudiantes->count(),
                    'resumen' => [
                        'total_registros' => $estudiantes->count()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de estudiantes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de pagos con totales
     */
    public function payments(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth());
            $fechaFin = $request->input('fecha_fin', now()->endOfMonth());
            $metodo = $request->input('metodo');
            $verificado = $request->input('verificado');

            $query = Pago::with([
                    'cuota.planPagos.inscripcion.estudiante',
                    'cuota.planPagos.inscripcion.programa'
                ])
                ->whereBetween('fecha', [$fechaInicio, $fechaFin]);

            if ($metodo) {
                $query->where('metodo', $metodo);
            }

            if ($verificado !== null) {
                $query->where('verificado', $verificado);
            }

            $pagos = $query->orderBy('fecha', 'desc')
                ->get()
                ->map(function ($pago) {
                    $estudiante = $pago->cuota->planPagos->inscripcion->estudiante;
                    $programa = $pago->cuota->planPagos->inscripcion->programa;

                    return [
                        'id' => $pago->id,
                        'fecha' => $pago->fecha,
                        'estudiante' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'programa' => $programa->nombre,
                        'monto' => $pago->monto,
                        'metodo' => $pago->metodo,
                        'verificado' => $pago->verificado,
                        'fecha_verificacion' => $pago->fecha_verificacion
                    ];
                });

            $totalMonto = $pagos->sum('monto');
            $porMetodo = [
                'QR' => $pagos->where('metodo', 'QR')->sum('monto'),
                'TRANSFERENCIA' => $pagos->where('metodo', 'TRANSFERENCIA')->sum('monto'),
                'EFECTIVO' => $pagos->where('metodo', 'EFECTIVO')->sum('monto')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reporte de pagos generado exitosamente',
                'data' => [
                    'pagos' => $pagos,
                    'total_pagos' => $pagos->count(),
                    'resumen' => [
                        'monto_total' => $totalMonto,
                        'monto_verificado' => $pagos->where('verificado', true)->sum('monto'),
                        'monto_pendiente' => $pagos->where('verificado', false)->sum('monto'),
                        'por_metodo' => $porMetodo
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de inscripciones por programa
     */
    public function enrollments(Request $request)
    {
        try {
            $programaId = $request->input('programa_id');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            $query = Inscripcion::with(['estudiante', 'programa', 'descuento']);

            if ($programaId) {
                $query->where('Programa_id', $programaId);
            }

            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
            }

            $inscripciones = $query->orderBy('fecha', 'desc')
                ->get()
                ->map(function ($inscripcion) {
                    return [
                        'id' => $inscripcion->id,
                        'fecha' => $inscripcion->fecha,
                        'estudiante' => $inscripcion->estudiante->nombre . ' ' . $inscripcion->estudiante->apellido,
                        'ci' => $inscripcion->estudiante->ci,
                        'registro' => $inscripcion->estudiante->registro_estudiante,
                        'programa' => $inscripcion->programa->nombre,
                        'costo_base' => $inscripcion->programa->costo,
                        'descuento' => $inscripcion->descuento ? $inscripcion->descuento->descuento . '%' : '0%',
                        'costo_final' => $inscripcion->costo_final
                    ];
                });

            $porPrograma = $inscripciones->groupBy('programa')->map(function ($items, $programa) {
                return [
                    'programa' => $programa,
                    'total_inscripciones' => $items->count(),
                    'monto_total' => $items->sum('costo_final')
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Reporte de inscripciones generado exitosamente',
                'data' => [
                    'inscripciones' => $inscripciones,
                    'total_inscripciones' => $inscripciones->count(),
                    'resumen' => [
                        'monto_total' => $inscripciones->sum('costo_final'),
                        'por_programa' => $porPrograma
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de inscripciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de rendimiento académico
     */
    public function academicPerformance(Request $request)
    {
        try {
            $programaId = $request->input('programa_id');
            $grupoId = $request->input('grupo_id');

            $query = Grupo::with(['programa', 'estudiantes']);

            if ($programaId) {
                $query->where('Programa_id', $programaId);
            }

            if ($grupoId) {
                $query->where('id', $grupoId);
            }

            $grupos = $query->get();

            $reporteGrupos = $grupos->map(function ($grupo) {
                $estudiantes = $grupo->estudiantes;
                $conNotas = $estudiantes->filter(fn($e) => $e->pivot->nota !== null);
                $notas = $conNotas->pluck('pivot.nota');

                return [
                    'grupo_id' => $grupo->id,
                    'programa' => $grupo->programa->nombre,
                    'fecha_ini' => $grupo->fecha_ini,
                    'fecha_fin' => $grupo->fecha_fin,
                    'total_estudiantes' => $estudiantes->count(),
                    'con_notas' => $conNotas->count(),
                    'sin_notas' => $estudiantes->count() - $conNotas->count(),
                    'aprobados' => $conNotas->filter(fn($e) => $e->pivot->nota >= 51)->count(),
                    'reprobados' => $conNotas->filter(fn($e) => $e->pivot->nota < 51)->count(),
                    'promedio' => $notas->count() > 0 ? round($notas->avg(), 2) : 0,
                    'nota_maxima' => $notas->count() > 0 ? $notas->max() : 0,
                    'nota_minima' => $notas->count() > 0 ? $notas->min() : 0,
                    'tasa_aprobacion' => $conNotas->count() > 0 
                        ? round(($conNotas->filter(fn($e) => $e->pivot->nota >= 51)->count() / $conNotas->count()) * 100, 2)
                        : 0
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Reporte de rendimiento académico generado exitosamente',
                'data' => [
                    'grupos' => $reporteGrupos,
                    'resumen_general' => [
                        'total_grupos' => $reporteGrupos->count(),
                        'total_estudiantes' => $reporteGrupos->sum('total_estudiantes'),
                        'total_aprobados' => $reporteGrupos->sum('aprobados'),
                        'total_reprobados' => $reporteGrupos->sum('reprobados'),
                        'promedio_general' => $reporteGrupos->avg('promedio')
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de rendimiento académico',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de documentos por estado
     */
    public function documents(Request $request)
    {
        try {
            $estado = $request->input('estado'); // 0: pendiente, 1: aprobado, 2: rechazado

            $query = Estudiante::with([
                'documentos.tipoDocumento',
                'estado'
            ]);

            if ($estado !== null) {
                $query->whereHas('documentos', function ($q) use ($estado) {
                    $q->where('estado', $estado);
                });
            }

            $estudiantes = $query->get()->map(function ($estudiante) {
                $documentos = $estudiante->documentos;
                
                return [
                    'estudiante' => $estudiante->nombre . ' ' . $estudiante->apellido,
                    'ci' => $estudiante->ci,
                    'registro' => $estudiante->registro_estudiante,
                    'estado_estudiante' => $estudiante->estado->nombre_estado ?? '',
                    'total_documentos' => $documentos->count(),
                    'aprobados' => $documentos->where('estado', 1)->count(),
                    'pendientes' => $documentos->where('estado', 0)->count(),
                    'rechazados' => $documentos->where('estado', 2)->count()
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Reporte de documentos generado exitosamente',
                'data' => [
                    'estudiantes' => $estudiantes,
                    'resumen' => [
                        'total_estudiantes' => $estudiantes->count(),
                        'total_documentos_aprobados' => $estudiantes->sum('aprobados'),
                        'total_documentos_pendientes' => $estudiantes->sum('pendientes'),
                        'total_documentos_rechazados' => $estudiantes->sum('rechazados')
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de documentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
