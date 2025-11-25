<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanPagos;
use App\Models\Inscripcion;
use App\Models\Cuota;
use App\Models\Programa;
use App\Traits\RegistraBitacora;
use App\Traits\EnviaNotificaciones;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PlanPagoController extends Controller
{
    use RegistraBitacora, EnviaNotificaciones;
    /**
     * Listar planes de pago con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $programaId = $request->get('programa_id', '');

            $query = PlanPagos::with([
                'inscripcion.estudiante.usuario',
                'inscripcion.programa.ramaAcademica',
                'inscripcion.programa.tipoPrograma',
                'inscripcion.programa.institucion',
                'inscripcion.descuento',
                'cuotas.pagos'
            ]);

            if ($search) {
                $query->whereHas('inscripcion.estudiante', function ($q) use ($search) {
                    $q->where('nombre', 'ILIKE', "%{$search}%")
                      ->orWhere('apellido', 'ILIKE', "%{$search}%")
                      ->orWhere('ci', 'ILIKE', "%{$search}%");
                });
            }

            if ($programaId) {
                $query->whereHas('inscripcion', function ($q) use ($programaId) {
                    $q->where('programa_id', $programaId);
                });
            }

            $planes = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            // Transformar datos para el frontend
            $planes->getCollection()->transform(function ($plan) {
                $inscripcion = $plan->inscripcion;
                $montoPagado = 0;
                $montoPendiente = 0;
                $totalCuotas = $plan->cuotas->count();
                $cuotasPagadas = 0;

                // Calcular monto pagado y cuotas pagadas
                foreach ($plan->cuotas as $cuota) {
                    $montoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                    $montoPagado += $montoCuota;
                    if ($montoCuota > 0) {
                        $cuotasPagadas++;
                    }
                }
                $montoPendiente = $plan->monto_total - $montoPagado;
                $porcentajePagado = $plan->monto_total > 0
                    ? round(($montoPagado / $plan->monto_total) * 100, 2)
                    : 0;

                return [
                    'id' => $plan->id,
                    'inscripcion_id' => $plan->inscripcion_id,
                    'monto_total' => $plan->monto_total,
                    'total_cuotas' => $plan->total_cuotas,
                    'monto_pagado' => $montoPagado,
                    'monto_pendiente' => $montoPendiente,
                    'porcentaje_pagado' => $porcentajePagado,
                    'esta_completo' => $porcentajePagado >= 100,
                    'inscripcion' => $inscripcion ? [
                        'id' => $inscripcion->id,
                        'fecha' => $inscripcion->fecha ? \Carbon\Carbon::parse($inscripcion->fecha)->format('Y-m-d') : null,
                        'fecha_formatted' => $inscripcion->fecha ? \Carbon\Carbon::parse($inscripcion->fecha)->format('d/m/Y') : 'N/A',
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
                        'descuento' => $inscripcion->descuento ? [
                            'id' => $inscripcion->descuento->id,
                            'nombre' => $inscripcion->descuento->nombre,
                            'descuento' => $inscripcion->descuento->descuento,
                        ] : null,
                    ] : null,
                    'cuotas' => $plan->cuotas->map(function ($cuota) {
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
                    'estado_pagos' => [
                        'total_cuotas' => $totalCuotas,
                        'cuotas_pagadas' => $cuotasPagadas,
                        'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                        'monto_total' => $plan->monto_total,
                        'monto_pagado' => $montoPagado,
                        'monto_pendiente' => $montoPendiente,
                        'porcentaje_pagado' => $porcentajePagado,
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $planes,
                'message' => 'Planes de pago obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener planes de pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener plan de pago por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $plan = PlanPagos::with([
                'inscripcion.estudiante.usuario',
                'inscripcion.programa.ramaAcademica',
                'inscripcion.programa.tipoPrograma',
                'inscripcion.programa.institucion',
                'inscripcion.programa.modulos',
                'inscripcion.descuento',
                'cuotas.pagos'
            ])->findOrFail($id);

            $inscripcion = $plan->inscripcion;
            $montoPagado = 0;
            $montoPendiente = 0;
            $totalCuotas = $plan->cuotas->count();
            $cuotasPagadas = 0;

            // Calcular monto pagado y cuotas pagadas
            foreach ($plan->cuotas as $cuota) {
                $montoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                $montoPagado += $montoCuota;
                if ($montoCuota > 0) {
                    $cuotasPagadas++;
                }
            }
            $montoPendiente = $plan->monto_total - $montoPagado;
            $porcentajePagado = $plan->monto_total > 0
                ? round(($montoPagado / $plan->monto_total) * 100, 2)
                : 0;

            $data = [
                'id' => $plan->id,
                'inscripcion_id' => $plan->inscripcion_id,
                'monto_total' => $plan->monto_total,
                'total_cuotas' => $plan->total_cuotas,
                'monto_pagado' => $montoPagado,
                'monto_pendiente' => $montoPendiente,
                'porcentaje_pagado' => $porcentajePagado,
                'esta_completo' => $porcentajePagado >= 100,
                'inscripcion' => $inscripcion ? [
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
                    'descuento' => $inscripcion->descuento ? [
                        'id' => $inscripcion->descuento->id,
                        'nombre' => $inscripcion->descuento->nombre,
                        'descuento' => $inscripcion->descuento->descuento,
                    ] : null,
                ] : null,
                'cuotas' => $plan->cuotas->map(function ($cuota) {
                    $montoPagadoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                    return [
                        'id' => $cuota->id,
                        'fecha_ini' => $cuota->fecha_ini,
                        'fecha_fin' => $cuota->fecha_fin,
                        'monto' => $cuota->monto,
                        'monto_pagado' => $montoPagadoCuota,
                        'monto_pendiente' => $cuota->monto - $montoPagadoCuota,
                        'estado' => $montoPagadoCuota >= $cuota->monto ? 'Pagado' : 'Pendiente',
                        'pagos' => $cuota->pagos ? $cuota->pagos->map(function ($pago) {
                            return [
                                'id' => $pago->id,
                                'fecha' => $pago->fecha,
                                'monto' => $pago->monto,
                                'metodo' => $pago->metodo,
                                'verificado' => $pago->verificado,
                                'observaciones' => $pago->observaciones,
                            ];
                        }) : [],
                    ];
                }),
                'estado_pagos' => [
                    'total_cuotas' => $totalCuotas,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                    'monto_total' => $plan->monto_total,
                    'monto_pagado' => $montoPagado,
                    'monto_pendiente' => $montoPendiente,
                    'porcentaje_pagado' => $porcentajePagado,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Plan de pago obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan de pago no encontrado'
            ], 404);
        }
    }

    /**
     * Obtener datos para formulario (inscripciones sin plan, programas, etc.)
     */
    public function datosFormulario(): JsonResponse
    {
        try {
            // Obtener IDs de inscripciones que ya tienen plan de pago
            $inscripcionesConPlan = PlanPagos::whereNotNull('inscripcion_id')
                ->pluck('inscripcion_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // Inscripciones sin plan de pago (solo las que no tienen plan asociado)
            $query = Inscripcion::with([
                'estudiante.usuario',
                'programa.ramaAcademica',
                'programa.tipoPrograma',
                'programa.institucion'
            ]);

            // Si hay inscripciones con plan, excluirlas
            if (!empty($inscripcionesConPlan)) {
                $query->whereNotIn('id', $inscripcionesConPlan);
            }

            $inscripcionesSinPlan = $query->orderBy('fecha', 'desc')
                ->get()
                ->map(function ($inscripcion) {
                    return [
                        'id' => $inscripcion->id,
                        'fecha' => $inscripcion->fecha ? Carbon::parse($inscripcion->fecha)->format('Y-m-d') : null,
                        'fecha_formatted' => $inscripcion->fecha ? Carbon::parse($inscripcion->fecha)->format('d/m/Y') : 'N/A',
                        'estudiante' => [
                            'id' => $inscripcion->estudiante->id ?? null,
                            'nombre' => $inscripcion->estudiante->nombre ?? '',
                            'apellido' => $inscripcion->estudiante->apellido ?? '',
                            'ci' => $inscripcion->estudiante->ci ?? '',
                            'registro_estudiante' => $inscripcion->estudiante->registro_estudiante ?? '',
                            'email' => $inscripcion->estudiante->usuario->email ?? ''
                        ],
                        'programa' => [
                            'id' => $inscripcion->programa->id ?? null,
                            'nombre' => $inscripcion->programa->nombre ?? '',
                            'rama_academica' => $inscripcion->programa->ramaAcademica->nombre ?? '',
                            'tipo_programa' => $inscripcion->programa->tipoPrograma->nombre ?? '',
                            'institucion' => $inscripcion->programa->institucion->nombre ?? '',
                            'costo' => $inscripcion->programa->costo ?? 0
                        ]
                    ];
                });

            // Programas activos
            $programas = Programa::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'inscripciones' => $inscripcionesSinPlan,
                    'programas' => $programas
                ],
                'debug' => [
                    'total_inscripciones' => Inscripcion::count(),
                    'inscripciones_con_plan' => count($inscripcionesConPlan),
                    'inscripciones_sin_plan' => $inscripcionesSinPlan->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en datosFormulario PlanPagoController: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos del formulario: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo plan de pago
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'inscripcion_id' => 'required|exists:inscripcion,id',
            'monto_total' => 'required|numeric|min:0.01',
            'total_cuotas' => 'required|integer|min:1',
            'cuotas' => 'required|array|min:1',
            'cuotas.*.fecha_ini' => 'required|date',
            'cuotas.*.fecha_fin' => 'required|date|after:cuotas.*.fecha_ini',
            'cuotas.*.monto' => 'required|numeric|min:0.01'
        ], [
            'inscripcion_id.required' => 'Debe seleccionar una inscripción',
            'inscripcion_id.exists' => 'La inscripción seleccionada no existe',
            'monto_total.required' => 'El monto total es obligatorio',
            'monto_total.numeric' => 'El monto total debe ser un número válido',
            'monto_total.min' => 'El monto total debe ser mayor a 0',
            'total_cuotas.required' => 'El número de cuotas es obligatorio',
            'total_cuotas.integer' => 'El número de cuotas debe ser un número entero',
            'total_cuotas.min' => 'Debe haber al menos 1 cuota',
            'cuotas.required' => 'Debe agregar al menos una cuota',
            'cuotas.array' => 'Las cuotas deben ser un arreglo válido',
            'cuotas.min' => 'Debe agregar al menos una cuota',
            'cuotas.*.fecha_ini.required' => 'La fecha de inicio de la cuota es obligatoria',
            'cuotas.*.fecha_ini.date' => 'La fecha de inicio debe ser una fecha válida',
            'cuotas.*.fecha_fin.required' => 'La fecha de fin de la cuota es obligatoria',
            'cuotas.*.fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'cuotas.*.fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'cuotas.*.monto.required' => 'El monto de la cuota es obligatorio',
            'cuotas.*.monto.numeric' => 'El monto de la cuota debe ser un número válido',
            'cuotas.*.monto.min' => 'El monto de la cuota debe ser mayor a 0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Verificar que la inscripción no tenga ya un plan de pagos
            $inscripcion = Inscripcion::findOrFail($request->inscripcion_id);
            if ($inscripcion->planPago) {
                return response()->json([
                    'success' => false,
                    'message' => 'La inscripción ya tiene un plan de pagos'
                ], 422);
            }

            // Verificar que el monto total coincida con la suma de cuotas
            $montoCuotas = collect($request->cuotas)->sum('monto');
            if (abs($montoCuotas - $request->monto_total) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto total debe coincidir con la suma de las cuotas'
                ], 422);
            }

            // Verificar que el número de cuotas coincida
            if (count($request->cuotas) != $request->total_cuotas) {
                return response()->json([
                    'success' => false,
                    'message' => 'El número de cuotas debe coincidir con el total de cuotas'
                ], 422);
            }

            // Crear plan de pagos
            $plan = PlanPagos::create([
                'inscripcion_id' => $request->inscripcion_id,
                'monto_total' => $request->monto_total,
                'total_cuotas' => $request->total_cuotas
            ]);

            // Crear cuotas
            foreach ($request->cuotas as $cuotaData) {
                Cuota::create([
                    'fecha_ini' => $cuotaData['fecha_ini'],
                    'fecha_fin' => $cuotaData['fecha_fin'],
                    'monto' => $cuotaData['monto'],
                    'plan_pago_id' => $plan->id
                ]);
            }

            DB::commit();

            // Registrar en bitácora
            $plan->load('inscripcion.estudiante');
            $estudiante = $plan->inscripcion->estudiante ?? null;
            $descripcion = "Plan de pago creado - Inscripción ID: {$plan->inscripcion_id}" . 
                ($estudiante ? " - Estudiante: {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci})" : "") . 
                " - Monto: {$plan->monto_total} - Cuotas: {$plan->total_cuotas}";
            $this->registrarCreacion('plan_pago', $plan->id, $descripcion);

            // Enviar notificación al estudiante
            if ($estudiante) {
                $this->notificarPlanPagoCreado($estudiante, $plan->monto_total, $plan->total_cuotas, $plan->id);
            }

            // Recargar con relaciones
            $plan->load([
                'inscripcion.estudiante.usuario',
                'inscripcion.programa.ramaAcademica',
                'inscripcion.programa.tipoPrograma',
                'inscripcion.programa.institucion',
                'inscripcion.descuento',
                'cuotas.pagos'
            ]);

            // Transformar datos
            $inscripcion = $plan->inscripcion;
            $montoPagado = 0;
            $totalCuotas = $plan->cuotas->count();
            $cuotasPagadas = 0;

            foreach ($plan->cuotas as $cuota) {
                $montoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                $montoPagado += $montoCuota;
                if ($montoCuota > 0) {
                    $cuotasPagadas++;
                }
            }
            $montoPendiente = $plan->monto_total - $montoPagado;
            $porcentajePagado = $plan->monto_total > 0
                ? round(($montoPagado / $plan->monto_total) * 100, 2)
                : 0;

            $data = [
                'id' => $plan->id,
                'inscripcion_id' => $plan->inscripcion_id,
                'monto_total' => $plan->monto_total,
                'total_cuotas' => $plan->total_cuotas,
                'monto_pagado' => $montoPagado,
                'monto_pendiente' => $montoPendiente,
                'porcentaje_pagado' => $porcentajePagado,
                'esta_completo' => $porcentajePagado >= 100,
                'inscripcion' => $inscripcion ? [
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
                    'descuento' => $inscripcion->descuento ? [
                        'id' => $inscripcion->descuento->id,
                        'nombre' => $inscripcion->descuento->nombre,
                        'descuento' => $inscripcion->descuento->descuento,
                    ] : null,
                ] : null,
                'cuotas' => $plan->cuotas->map(function ($cuota) {
                    $montoPagadoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                    return [
                        'id' => $cuota->id,
                        'fecha_ini' => $cuota->fecha_ini,
                        'fecha_fin' => $cuota->fecha_fin,
                        'monto' => $cuota->monto,
                        'monto_pagado' => $montoPagadoCuota,
                        'monto_pendiente' => $cuota->monto - $montoPagadoCuota,
                        'estado' => $montoPagadoCuota >= $cuota->monto ? 'Pagado' : 'Pendiente',
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
                'estado_pagos' => [
                    'total_cuotas' => $totalCuotas,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                    'monto_total' => $plan->monto_total,
                    'monto_pagado' => $montoPagado,
                    'monto_pendiente' => $montoPendiente,
                    'porcentaje_pagado' => $porcentajePagado,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Plan de pago creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear plan de pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar plan de pago
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $plan = PlanPagos::findOrFail($id);

        // Verificar que no tenga pagos realizados
        if ($plan->cuotas()->whereHas('pagos')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar un plan de pago que ya tiene pagos realizados'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'monto_total' => 'required|numeric|min:0.01',
            'total_cuotas' => 'required|integer|min:1',
            'cuotas' => 'required|array|min:1',
            'cuotas.*.fecha_ini' => 'required|date',
            'cuotas.*.fecha_fin' => 'required|date|after:cuotas.*.fecha_ini',
            'cuotas.*.monto' => 'required|numeric|min:0.01'
        ], [
            'monto_total.required' => 'El monto total es obligatorio',
            'monto_total.numeric' => 'El monto total debe ser un número válido',
            'monto_total.min' => 'El monto total debe ser mayor a 0',
            'total_cuotas.required' => 'El número de cuotas es obligatorio',
            'total_cuotas.integer' => 'El número de cuotas debe ser un número entero',
            'total_cuotas.min' => 'Debe haber al menos 1 cuota',
            'cuotas.required' => 'Debe agregar al menos una cuota',
            'cuotas.array' => 'Las cuotas deben ser un arreglo válido',
            'cuotas.min' => 'Debe agregar al menos una cuota',
            'cuotas.*.fecha_ini.required' => 'La fecha de inicio de la cuota es obligatoria',
            'cuotas.*.fecha_ini.date' => 'La fecha de inicio debe ser una fecha válida',
            'cuotas.*.fecha_fin.required' => 'La fecha de fin de la cuota es obligatoria',
            'cuotas.*.fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'cuotas.*.fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'cuotas.*.monto.required' => 'El monto de la cuota es obligatorio',
            'cuotas.*.monto.numeric' => 'El monto de la cuota debe ser un número válido',
            'cuotas.*.monto.min' => 'El monto de la cuota debe ser mayor a 0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Verificar que el monto total coincida con la suma de cuotas
            $montoCuotas = collect($request->cuotas)->sum('monto');
            if (abs($montoCuotas - $request->monto_total) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto total debe coincidir con la suma de las cuotas'
                ], 422);
            }

            // Actualizar plan
            $plan->update([
                'monto_total' => $request->monto_total,
                'total_cuotas' => $request->total_cuotas
            ]);

            // Eliminar cuotas existentes
            $plan->cuotas()->delete();

            // Crear nuevas cuotas
            foreach ($request->cuotas as $cuotaData) {
                Cuota::create([
                    'fecha_ini' => $cuotaData['fecha_ini'],
                    'fecha_fin' => $cuotaData['fecha_fin'],
                    'monto' => $cuotaData['monto'],
                    'plan_pago_id' => $plan->id
                ]);
            }

            DB::commit();

            // Registrar en bitácora
            $plan->load('inscripcion.estudiante');
            $estudiante = $plan->inscripcion->estudiante ?? null;
            $descripcion = "Plan de pago actualizado - Inscripción ID: {$plan->inscripcion_id}" . 
                ($estudiante ? " - Estudiante: {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci})" : "") . 
                " - Monto: {$plan->monto_total} - Cuotas: {$plan->total_cuotas}";
            $this->registrarEdicion('plan_pago', $plan->id, $descripcion);

            // Recargar con relaciones
            $plan->load([
                'inscripcion.estudiante.usuario',
                'inscripcion.programa.ramaAcademica',
                'inscripcion.programa.tipoPrograma',
                'inscripcion.programa.institucion',
                'inscripcion.descuento',
                'cuotas.pagos'
            ]);

            // Transformar datos (mismo código que en crear)
            $inscripcion = $plan->inscripcion;
            $montoPagado = 0;
            $totalCuotas = $plan->cuotas->count();
            $cuotasPagadas = 0;

            foreach ($plan->cuotas as $cuota) {
                $montoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                $montoPagado += $montoCuota;
                if ($montoCuota > 0) {
                    $cuotasPagadas++;
                }
            }
            $montoPendiente = $plan->monto_total - $montoPagado;
            $porcentajePagado = $plan->monto_total > 0
                ? round(($montoPagado / $plan->monto_total) * 100, 2)
                : 0;

            $data = [
                'id' => $plan->id,
                'inscripcion_id' => $plan->inscripcion_id,
                'monto_total' => $plan->monto_total,
                'total_cuotas' => $plan->total_cuotas,
                'monto_pagado' => $montoPagado,
                'monto_pendiente' => $montoPendiente,
                'porcentaje_pagado' => $porcentajePagado,
                'esta_completo' => $porcentajePagado >= 100,
                'inscripcion' => $inscripcion ? [
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
                    'descuento' => $inscripcion->descuento ? [
                        'id' => $inscripcion->descuento->id,
                        'nombre' => $inscripcion->descuento->nombre,
                        'descuento' => $inscripcion->descuento->descuento,
                    ] : null,
                ] : null,
                'cuotas' => $plan->cuotas->map(function ($cuota) {
                    $montoPagadoCuota = $cuota->pagos ? $cuota->pagos->sum('monto') : 0;
                    return [
                        'id' => $cuota->id,
                        'fecha_ini' => $cuota->fecha_ini,
                        'fecha_fin' => $cuota->fecha_fin,
                        'monto' => $cuota->monto,
                        'monto_pagado' => $montoPagadoCuota,
                        'monto_pendiente' => $cuota->monto - $montoPagadoCuota,
                        'estado' => $montoPagadoCuota >= $cuota->monto ? 'Pagado' : 'Pendiente',
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
                'estado_pagos' => [
                    'total_cuotas' => $totalCuotas,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                    'monto_total' => $plan->monto_total,
                    'monto_pagado' => $montoPagado,
                    'monto_pendiente' => $montoPendiente,
                    'porcentaje_pagado' => $porcentajePagado,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Plan de pago actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar plan de pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar plan de pago
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $plan = PlanPagos::findOrFail($id);

            // Verificar que no tenga pagos realizados
            if ($plan->cuotas()->whereHas('pagos')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un plan de pago que ya tiene pagos realizados'
                ], 422);
            }

            DB::beginTransaction();

            // Guardar datos para bitácora antes de eliminar
            $planId = $plan->id;
            $inscripcionId = $plan->inscripcion_id;
            $estudiante = $plan->inscripcion->estudiante ?? null;
            $descripcion = "Plan de pago eliminado - Inscripción ID: {$inscripcionId}" . 
                ($estudiante ? " - Estudiante: {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci})" : "") . 
                " - Monto: {$plan->monto_total}";

            // Eliminar cuotas
            $plan->cuotas()->delete();

            // Eliminar plan
            $plan->delete();

            DB::commit();

            // Registrar en bitácora
            $this->registrarEliminacion('plan_pago', $planId, $descripcion);

            return response()->json([
                'success' => true,
                'message' => 'Plan de pago eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar plan de pago: ' . $e->getMessage()
            ], 500);
        }
    }
}

