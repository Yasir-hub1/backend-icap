<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InscripcionController extends Controller
{
    /**
     * Listar inscripciones con filtros
     */
    public function listar(Request $request)
    {
        try {
            $query = Inscripcion::with([
                'estudiante.usuario',
                'programa.ramaAcademica',
                'programa.tipoPrograma',
                'programa.institucion',
                'planPago.cuotas.pagos',
                'descuento'
            ]);

            // Filtros
            if ($request->has('search') && $request->search && trim($request->search) !== '') {
                $search = trim($request->search);
                $query->where(function($q) use ($search) {
                    $q->whereHas('estudiante', function($estQuery) use ($search) {
                        $estQuery->where('ci', 'ILIKE', "%{$search}%")
                                 ->orWhere('nombre', 'ILIKE', "%{$search}%")
                                 ->orWhere('apellido', 'ILIKE', "%{$search}%")
                                 ->orWhere('registro_estudiante', 'ILIKE', "%{$search}%");
                    })
                    ->orWhereHas('programa', function($progQuery) use ($search) {
                        $progQuery->where('nombre', 'ILIKE', "%{$search}%");
                    });
                });
            }

            // Filtro por programa
            if ($request->has('programa_id') && $request->programa_id) {
                $query->where('programa_id', $request->programa_id);
            }

            // Filtro por estudiante
            if ($request->has('estudiante_id') && $request->estudiante_id) {
                $query->where('estudiante_id', $request->estudiante_id);
            }

            // Filtro por fecha
            if ($request->has('fecha_inicio') && $request->fecha_inicio) {
                $query->where('fecha', '>=', $request->fecha_inicio);
            }

            if ($request->has('fecha_fin') && $request->fecha_fin) {
                $query->where('fecha', '<=', $request->fecha_fin);
            }

            // Ordenamiento
            $sortBy = $request->get('sort_by', 'fecha');
            $sortDirection = $request->get('sort_direction', 'desc');

            // Validar que sort_by sea una columna válida
            $allowedSortColumns = ['fecha', 'id', 'created_at'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'fecha';
            }

            // Validar dirección de ordenamiento
            $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

            $query->orderBy($sortBy, $sortDirection);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $perPage = max(1, min(100, (int)$perPage)); // Limitar entre 1 y 100

            $inscripciones = $query->paginate($perPage);

            // Transformar datos para el frontend
            $inscripciones->getCollection()->transform(function ($inscripcion) {
                $planPago = $inscripcion->planPago;
                $montoPagado = 0;
                $montoPendiente = 0;
                $totalCuotas = 0;
                $cuotasPagadas = 0;

                if ($planPago) {
                    $totalCuotas = $planPago->cuotas->count();
                    $cuotasPagadas = $planPago->cuotas->filter(function ($cuota) {
                        return $cuota->pagos && $cuota->pagos->count() > 0;
                    })->count();

                    foreach ($planPago->cuotas as $cuota) {
                        $montoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                        $montoPagado += $montoCuota;
                    }
                    $montoPendiente = $planPago->monto_total - $montoPagado;
                }

                return [
                    'id' => $inscripcion->id,
                    'fecha' => $inscripcion->fecha ? Carbon::parse($inscripcion->fecha)->format('Y-m-d') : null,
                    'fecha_formatted' => $inscripcion->fecha ? Carbon::parse($inscripcion->fecha)->format('d/m/Y') : 'N/A',
                    'estudiante' => [
                        'id' => $inscripcion->estudiante->id ?? null,
                        'registro_estudiante' => $inscripcion->estudiante->registro_estudiante ?? null,
                        'nombre' => $inscripcion->estudiante->nombre ?? 'N/A',
                        'apellido' => $inscripcion->estudiante->apellido ?? 'N/A',
                        'ci' => $inscripcion->estudiante->ci ?? 'N/A',
                        'celular' => $inscripcion->estudiante->celular ?? 'N/A',
                        'email' => $inscripcion->estudiante->usuario->email ?? 'N/A',
                    ],
                    'programa' => [
                        'id' => $inscripcion->programa->id ?? null,
                        'nombre' => $inscripcion->programa->nombre ?? 'N/A',
                        'rama_academica' => $inscripcion->programa->ramaAcademica->nombre ?? 'N/A',
                        'tipo_programa' => $inscripcion->programa->tipoPrograma->nombre ?? 'N/A',
                        'institucion' => $inscripcion->programa->institucion->nombre ?? 'N/A',
                    ],
                    'plan_pago' => $planPago ? [
                        'id' => $planPago->id,
                        'monto_total' => $planPago->monto_total,
                        'total_cuotas' => $totalCuotas,
                        'monto_pagado' => $montoPagado,
                        'monto_pendiente' => $montoPendiente,
                        'cuotas_pagadas' => $cuotasPagadas,
                        'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                    ] : null,
                    'descuento' => $inscripcion->descuento ? [
                        'id' => $inscripcion->descuento->id,
                        'nombre' => $inscripcion->descuento->nombre,
                        'descuento' => $inscripcion->descuento->descuento,
                    ] : null,
                    'estado_pagos' => $planPago ? [
                        'total_cuotas' => $totalCuotas,
                        'cuotas_pagadas' => $cuotasPagadas,
                        'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                        'monto_total' => $planPago->monto_total,
                        'monto_pagado' => $montoPagado,
                        'monto_pendiente' => $montoPendiente,
                        'porcentaje_pagado' => $planPago->monto_total > 0
                            ? round(($montoPagado / $planPago->monto_total) * 100, 2)
                            : 0,
                    ] : null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $inscripciones,
                'message' => 'Inscripciones obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar inscripciones: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener una inscripción por ID
     */
    public function obtener($id)
    {
        try {
            $inscripcion = Inscripcion::with([
                'estudiante.usuario',
                'programa.ramaAcademica',
                'programa.tipoPrograma',
                'programa.institucion',
                'programa.modulos',
                'planPago.cuotas.pagos',
                'descuento'
            ])->findOrFail($id);

            $planPago = $inscripcion->planPago;
            $montoPagado = 0;
            $montoPendiente = 0;
            $totalCuotas = 0;
            $cuotasPagadas = 0;

            if ($planPago) {
                $totalCuotas = $planPago->cuotas->count();
                $cuotasPagadas = $planPago->cuotas->filter(function ($cuota) {
                    return $cuota->pagos && $cuota->pagos->count() > 0;
                })->count();

                foreach ($planPago->cuotas as $cuota) {
                    $montoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                    $montoPagado += $montoCuota;
                }
                $montoPendiente = $planPago->monto_total - $montoPagado;
            }

            $data = [
                'id' => $inscripcion->id,
                'fecha' => $inscripcion->fecha ? Carbon::parse($inscripcion->fecha)->format('Y-m-d') : null,
                'fecha_formatted' => $inscripcion->fecha ? Carbon::parse($inscripcion->fecha)->format('d/m/Y') : 'N/A',
                'estudiante' => [
                    'id' => $inscripcion->estudiante->id ?? null,
                    'registro_estudiante' => $inscripcion->estudiante->registro_estudiante ?? null,
                    'nombre' => $inscripcion->estudiante->nombre ?? 'N/A',
                    'apellido' => $inscripcion->estudiante->apellido ?? 'N/A',
                    'ci' => $inscripcion->estudiante->ci ?? 'N/A',
                    'celular' => $inscripcion->estudiante->celular ?? 'N/A',
                    'email' => $inscripcion->estudiante->usuario->email ?? 'N/A',
                    'direccion' => $inscripcion->estudiante->direccion ?? 'N/A',
                    'provincia' => $inscripcion->estudiante->provincia ?? 'N/A',
                ],
                'programa' => [
                    'id' => $inscripcion->programa->id ?? null,
                    'nombre' => $inscripcion->programa->nombre ?? 'N/A',
                    'rama_academica' => $inscripcion->programa->ramaAcademica->nombre ?? 'N/A',
                    'tipo_programa' => $inscripcion->programa->tipoPrograma->nombre ?? 'N/A',
                    'institucion' => $inscripcion->programa->institucion->nombre ?? 'N/A',
                    'duracion_meses' => $inscripcion->programa->duracion_meses ?? null,
                    'costo' => $inscripcion->programa->costo ?? null,
                ],
                'plan_pago' => $planPago ? [
                    'id' => $planPago->id,
                    'monto_total' => $planPago->monto_total,
                    'total_cuotas' => $totalCuotas,
                    'monto_pagado' => $montoPagado,
                    'monto_pendiente' => $montoPendiente,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                    'cuotas' => $planPago->cuotas->map(function ($cuota) {
                        $montoPagadoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                        $fechaFin = Carbon::parse($cuota->fecha_fin);
                        $hoy = Carbon::now();

                        // Determinar estado: Pagado, Pendiente o Retrasado
                        $estado = 'Pendiente';
                        if ($montoPagadoCuota >= $cuota->monto) {
                            $estado = 'Pagado';
                        } elseif ($fechaFin->lt($hoy)) {
                            $estado = 'Retrasado';
                        }

                        return [
                            'id' => $cuota->id,
                            'fecha_ini' => $cuota->fecha_ini,
                            'fecha_fin' => $cuota->fecha_fin,
                            'monto' => $cuota->monto,
                            'monto_pagado' => $montoPagadoCuota,
                            'monto_pendiente' => $cuota->monto - $montoPagadoCuota,
                            'estado' => $estado,
                            'dias_retraso' => $estado === 'Retrasado' ? $hoy->diffInDays($fechaFin) : 0,
                            'pagos' => $cuota->pagos ? $cuota->pagos->map(function ($pago) {
                                return [
                                    'id' => $pago->id,
                                    'fecha' => $pago->fecha,
                                    'monto' => $pago->monto,
                                    'metodo' => $pago->metodo,
                                    'verificado' => $pago->verificado,
                                ];
                            }) : [],
                        ];
                    }),
                ] : null,
                'descuento' => $inscripcion->descuento ? [
                    'id' => $inscripcion->descuento->id,
                    'nombre' => $inscripcion->descuento->nombre,
                    'descuento' => $inscripcion->descuento->descuento,
                ] : null,
                'estado_pagos' => $planPago ? [
                    'total_cuotas' => $totalCuotas,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                    'monto_total' => $planPago->monto_total,
                    'monto_pagado' => $montoPagado,
                    'monto_pendiente' => $montoPendiente,
                    'porcentaje_pagado' => $planPago->monto_total > 0
                        ? round(($montoPagado / $planPago->monto_total) * 100, 2)
                        : 0,
                ] : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Inscripción obtenida exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener inscripción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Estadísticas de inscripciones
     */
    public function estadisticas()
    {
        try {
            $total = Inscripcion::count();
            $esteMes = Inscripcion::whereMonth('fecha', now()->month)
                                 ->whereYear('fecha', now()->year)
                                 ->count();
            $esteAnio = Inscripcion::whereYear('fecha', now()->year)->count();

            $porPrograma = DB::table('inscripcion')
                ->join('programa', 'inscripcion.programa_id', '=', 'programa.id')
                ->select('programa.nombre', DB::raw('count(*) as total'))
                ->groupBy('programa.id', 'programa.nombre')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'este_mes' => $esteMes,
                    'este_anio' => $esteAnio,
                    'por_programa' => $porPrograma
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

