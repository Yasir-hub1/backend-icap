<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Bitacora;
use App\Models\Inscripcion;
use App\Models\Estudiante;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoController extends Controller
{
    /**
     * Listado de cuotas del estudiante (pendientes y pagadas) - alias para listar
     */
    public function listar(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Listado de cuotas del estudiante (pendientes y pagadas)
     */
    public function index(Request $request)
    {
        try {
            $estudiante = $request->auth_user;

            // Obtener el registro_estudiante correctamente
            $registroEstudiante = $estudiante instanceof \App\Models\Estudiante
                ? $estudiante->registro_estudiante
                : $estudiante->id;

            // Obtener planes de pago del estudiante agrupados por inscripción
            $estudiante = Estudiante::where('registro_estudiante', $registroEstudiante)->first();
            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }
            $inscripciones = Inscripcion::where('estudiante_id', $estudiante->id)
                ->with([
                    'programa',
                    'planPago.cuotas' => function ($query) {
                        $query->with('pagos')->orderBy('fecha_ini', 'asc');
                    }
                ])
                ->get();

            // Transformar datos para mostrar planes completos
            $planesData = $inscripciones->map(function ($inscripcion) {
                $descuento = $inscripcion->descuento;
                $plan = $inscripcion->planPago;
                if (!$plan) {
                    return null;
                }

                $cuotas = $plan->cuotas;
                $totalCuotas = $cuotas->count();
                $cuotasPagadas = $cuotas->filter(function ($cuota) {
                    return $cuota->monto_pagado >= $cuota->monto;
                })->count();

                return [
                    'inscripcion_id' => $inscripcion->id,
                    'fecha_inscripcion' => $inscripcion->fecha,
                    'programa' => [
                        'id' => $inscripcion->programa->id ?? null,
                        'nombre' => $inscripcion->programa ? $inscripcion->programa->nombre : '',
                    ],
                    'plan_id' => $plan->id,
                    'costo_base' => $inscripcion->costo_base,
                    'costo_final' => $inscripcion->costo_final,
                    'descuento' => $descuento ? [
                        'id' => $descuento->id,
                        'nombre' => $descuento->nombre,
                        'descuento' => $descuento->descuento,
                        'fecha_inicio' => $descuento->fecha_inicio,
                        'fecha_fin' => $descuento->fecha_fin,
                    ] : null,
                    'monto_total' => $plan->monto_total,
                    'monto_pagado' => $plan->monto_pagado,
                    'monto_pendiente' => $plan->monto_pendiente,
                    'esta_completo' => $plan->esta_completo,
                    'total_cuotas' => $totalCuotas,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                    'porcentaje_pagado' => $plan->monto_total > 0 ? ($plan->monto_pagado / $plan->monto_total) * 100 : 0,
                    'cuotas' => $cuotas->map(function ($cuota) use ($inscripcion) {
                        return [
                            'id' => $cuota->id,
                            'monto' => $cuota->monto,
                            'fecha_ini' => $cuota->fecha_ini,
                            'fecha_fin' => $cuota->fecha_fin,
                            'fecha_inscripcion' => $inscripcion->fecha, // Agregar fecha de inscripción
                            'estado' => $cuota->monto_pagado >= $cuota->monto ? 'PAGADA' : ($cuota->esta_vencida ? 'VENCIDA' : 'PENDIENTE'),
                            'esta_pagada' => $cuota->monto_pagado >= $cuota->monto,
                            'esta_vencida' => $cuota->esta_vencida,
                            'esta_pendiente' => $cuota->monto_pagado < $cuota->monto && !$cuota->esta_vencida,
                            'monto_pagado' => $cuota->monto_pagado,
                            'saldo_pendiente' => $cuota->saldo_pendiente,
                            'pagos' => $cuota->pagos->map(function ($pago) {
                                return [
                                    'id' => $pago->id,
                                    'fecha' => $pago->fecha,
                                    'monto' => $pago->monto,
                                    'token' => $pago->token
                                ];
                            })
                        ];
                    })->toArray()
                ];
            })->filter()->values();

            // Aplanar cuotas para compatibilidad con el frontend existente
            $cuotas = $planesData->flatMap(function ($plan) {
                $cuotasPlan = $plan['cuotas'] ?? [];
                // Si es una colección, convertir a array
                if (is_object($cuotasPlan) && method_exists($cuotasPlan, 'toArray')) {
                    $cuotasPlan = $cuotasPlan->toArray();
                } elseif (!is_array($cuotasPlan)) {
                    $cuotasPlan = [];
                }

                return collect($cuotasPlan)->map(function ($cuota) use ($plan) {
                    // Obtener nombre del programa de forma segura
                    $programaNombre = '';
                    if (isset($plan['programa'])) {
                        if (is_array($plan['programa']) && isset($plan['programa']['nombre'])) {
                            $programaNombre = $plan['programa']['nombre'];
                        } elseif (is_string($plan['programa'])) {
                            $programaNombre = $plan['programa'];
                        }
                    }

                    return array_merge($cuota, [
                        'programa' => $programaNombre,
                        'plan_id' => $plan['plan_id'],
                        'inscripcion_id' => $plan['inscripcion_id']
                    ]);
                });
            });

            return response()->json([
                'success' => true,
                'message' => 'Cuotas obtenidas exitosamente',
                'data' => [
                    'planes' => $planesData,
                    'cuotas' => $cuotas->values(),
                    'total_cuotas' => $cuotas->count(),
                    'cuotas_pagadas' => $cuotas->filter(function($c) { return $c['esta_pagada']; })->count(),
                    'cuotas_pendientes' => $cuotas->filter(function($c) { return $c['esta_pendiente']; })->count(),
                    'cuotas_vencidas' => $cuotas->filter(function($c) { return $c['esta_vencida']; })->count(),
                    'planes_completos' => $planesData->filter(function($p) { return $p['esta_completo']; })->count(),
                    'planes_pendientes' => $planesData->filter(function($p) { return !$p['esta_completo']; })->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener cuotas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalle de una cuota específica - alias para obtener
     */
    public function obtener(Request $request, $cuotaId)
    {
        return $this->show($request, $cuotaId);
    }

    /**
     * Detalle de una cuota específica
     */
    public function show(Request $request, $cuotaId)
    {
        try {
            $estudiante = $request->auth_user;

            // Obtener el registro_estudiante correctamente
            $registroEstudiante = $estudiante instanceof \App\Models\Estudiante
                ? $estudiante->registro_estudiante
                : $estudiante->id;

            $estudianteObj = Estudiante::where('registro_estudiante', $registroEstudiante)->first();
            if (!$estudianteObj) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }
            $cuota = Cuota::whereHas('planPago.inscripcion', function ($query) use ($estudianteObj) {
                    $query->where('estudiante_id', $estudianteObj->id);
                })
                ->with([
                    'planPago.inscripcion.programa',
                    'pagos'
                ])
                ->findOrFail($cuotaId);

            return response()->json([
                'success' => true,
                'message' => 'Detalle de cuota obtenido exitosamente',
                'data' => [
                    'cuota' => [
                        'id' => $cuota->id,
                        'programa' => $cuota->planPago->inscripcion->programa->nombre ?? '',
                        'monto' => $cuota->monto,
                        'fecha_ini' => $cuota->fecha_ini,
                        'fecha_fin' => $cuota->fecha_fin,
                        'estado' => $cuota->esta_pagada ? 'PAGADA' : ($cuota->esta_vencida ? 'VENCIDA' : 'PENDIENTE'),
                        'monto_pagado' => $cuota->monto_pagado,
                        'saldo_pendiente' => $cuota->saldo_pendiente
                    ],
                    'pagos' => $cuota->pagos->map(function ($pago) {
                        return [
                            'id' => $pago->id,
                            'fecha' => $pago->fecha,
                            'monto' => $pago->monto,
                            'token' => $pago->token
                        ];
                    })
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle de cuota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar pago con comprobante (alias para crear)
     */
    public function crear(Request $request)
    {
        return $this->store($request);
    }

    /**
     * Registrar pago con comprobante o generar QR
     */
    public function store(Request $request)
    {
        $request->validate([
            'cuota_id' => 'required|exists:cuotas,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|in:QR,TRANSFERENCIA,EFECTIVO',
            'comprobante' => 'required_if:metodo,TRANSFERENCIA,EFECTIVO|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'token' => 'nullable|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            $estudiante = $request->auth_user;

            // Obtener el registro_estudiante correctamente
            $registroEstudiante = $estudiante instanceof \App\Models\Estudiante
                ? $estudiante->registro_estudiante
                : $estudiante->id;

            // Verificar que la cuota pertenezca al estudiante
            $estudianteObj = Estudiante::where('registro_estudiante', $registroEstudiante)->first();
            if (!$estudianteObj) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }
            $cuota = Cuota::whereHas('planPago.inscripcion', function ($query) use ($estudianteObj) {
                    $query->where('estudiante_id', $estudianteObj->id);
                })
                ->findOrFail($request->cuota_id);

            // Verificar que la cuota no esté ya pagada
            if ($cuota->esta_pagada) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuota ya ha sido pagada'
                ], 400);
            }

            // Validar que el monto no exceda el saldo pendiente
            if ($request->monto > $cuota->saldo_pendiente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto excede el saldo pendiente de la cuota'
                ], 422);
            }

            // Si el método es QR, usar pasarela de pagos
            if ($request->metodo === 'QR') {
                $paymentGatewayService = app(PaymentGatewayService::class);
                $result = $paymentGatewayService->processQRPayment($cuota, $estudianteObj);

                $pago = $result['pago'];

                // Registrar en bitácora
                $usuario = $estudianteObj->usuario;
                if ($usuario) {
                    Bitacora::create([
                        'fecha' => now()->toDateString(),
                        'tabla' => 'Pagos',
                        'codTabla' => $pago->id,
                        'transaccion' => "Estudiante {$estudianteObj->nombre} {$estudianteObj->apellido} (CI: {$estudianteObj->ci}) generó QR de pago de {$pago->monto} Bs. Nro Pago: {$pago->nro_pago}",
                        'usuario_id' => $usuario->usuario_id
                    ]);
                }

                DB::commit();

                // Construir URL completa del QR si es una ruta relativa
                $qrImageUrl = $result['qr_image'];
                if ($qrImageUrl && !filter_var($qrImageUrl, FILTER_VALIDATE_URL)) {
                    // Es una ruta relativa, construir URL completa
                    $qrImageUrl = asset($qrImageUrl);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'QR generado exitosamente. Escanea el código para realizar el pago.',
                    'data' => [
                        'pago' => $pago->load('cuota'),
                        'qr_image' => $qrImageUrl,
                        'qr_base64' => $result['qr_base64'],
                        'nro_pago' => $pago->nro_pago,
                        'qr_expires_at' => $pago->qr_expires_at
                    ]
                ], 201);
            }

            // Para otros métodos (TRANSFERENCIA, EFECTIVO), requerir comprobante
            $comprobantePath = null;
            if ($request->hasFile('comprobante')) {
                $file = $request->file('comprobante');
                $nombreArchivo = time() . '_' . $file->getClientOriginalName();
                $comprobantePath = $file->storeAs(
                    "comprobantes/estudiante_{$estudianteObj->registro_estudiante}/cuota_{$cuota->id}",
                    $nombreArchivo,
                    'public'
                );
            }

            // Crear registro de pago
            $pago = Pago::create([
                'fecha' => now()->toDateString(),
                'monto' => $request->monto,
                'token' => $request->token ?? Str::random(32),
                'cuota_id' => $cuota->id,
                'comprobante' => $comprobantePath,
                'metodo' => $request->metodo
            ]);

            // Verificar si todas las cuotas del plan están pagadas
            $plan = $cuota->planPago;
            $this->verificarEstadoPlan($plan);

            // Registrar en bitácora
            $usuario = $estudianteObj->usuario;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Pagos',
                    'codTabla' => $pago->id,
                    'transaccion' => "Estudiante {$estudianteObj->nombre} {$estudianteObj->apellido} (CI: {$estudianteObj->ci}) registró pago de {$request->monto} Bs. Método: {$request->metodo}. Token: " . ($request->token ?? $pago->token),
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente. Pendiente de verificación.',
                'data' => [
                    'pago' => $pago->load('cuota'),
                    'comprobante_url' => $comprobantePath ? Storage::url($comprobantePath) : null
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información para generar QR de pago (alias para obtenerInfoQR)
     */
    public function obtenerInfoQR(Request $request, $cuotaId)
    {
        return $this->getQRInfo($request, $cuotaId);
    }

    /**
     * Obtener información para generar QR de pago
     */
    public function getQRInfo(Request $request, $cuotaId)
    {
        try {
            $estudiante = $request->auth_user;

            // Obtener el registro_estudiante correctamente
            $registroEstudiante = $estudiante instanceof \App\Models\Estudiante
                ? $estudiante->registro_estudiante
                : $estudiante->id;

            $estudianteObj = Estudiante::where('registro_estudiante', $registroEstudiante)->first();
            if (!$estudianteObj) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }
            $cuota = Cuota::whereHas('planPago.inscripcion', function ($query) use ($estudianteObj) {
                    $query->where('estudiante_id', $estudianteObj->id);
                })
                ->with('planPago.inscripcion.programa')
                ->findOrFail($cuotaId);

            if ($cuota->esta_pagada) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuota ya ha sido pagada'
                ], 400);
            }

            // Verificar si ya existe un pago QR pendiente para esta cuota
            $pagoQR = Pago::where('cuota_id', $cuota->id)
                ->where('metodo', 'QR')
                ->where('estado_pagofacil', 'pendiente')
                ->where(function($q) {
                    $q->whereNull('qr_expires_at')
                      ->orWhere('qr_expires_at', '>', now());
                })
                ->first();

            if ($pagoQR) {
                // Intentar obtener qr_base64 desde payment_info o leer el archivo
                $qrBase64 = null;
                if ($pagoQR->payment_info) {
                    $paymentInfo = json_decode($pagoQR->payment_info, true);
                    if (isset($paymentInfo['values']['qrBase64'])) {
                        $qrBase64 = $paymentInfo['values']['qrBase64'];
                    } elseif (isset($paymentInfo['qrBase64'])) {
                        $qrBase64 = $paymentInfo['qrBase64'];
                    }
                }

                // Si no está en payment_info, intentar leer el archivo
                if (!$qrBase64 && $pagoQR->qr_image) {
                    $qrPath = str_replace('/storage/', '', $pagoQR->qr_image);
                    if (Storage::disk('public')->exists($qrPath)) {
                        $qrContent = Storage::disk('public')->get($qrPath);
                        $qrBase64 = base64_encode($qrContent);
                    }
                }

                // Construir URL completa del QR si es una ruta relativa
                $qrImageUrl = $pagoQR->qr_image;
                if ($qrImageUrl && !filter_var($qrImageUrl, FILTER_VALIDATE_URL)) {
                    // Es una ruta relativa, construir URL completa
                    $qrImageUrl = asset($qrImageUrl);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'pago_id' => $pagoQR->id,
                        'monto' => $pagoQR->monto,
                        'qr_image' => $qrImageUrl,
                        'qr_base64' => $qrBase64,
                        'nro_pago' => $pagoQR->nro_pago,
                        'qr_expires_at' => $pagoQR->qr_expires_at,
                        'concepto' => "Cuota " . ($cuota->planPago->inscripcion->programa->nombre ?? ''),
                        'estudiante' => $estudianteObj->nombre . ' ' . $estudianteObj->apellido,
                        'ci' => $estudianteObj->ci
                    ]
                ], 200);
            }

            // Información para generar QR (ajustar según banco/pasarela)
            return response()->json([
                'success' => true,
                'data' => [
                    'monto' => $cuota->saldo_pendiente,
                    'concepto' => "Cuota " . ($cuota->planPago->inscripcion->programa->nombre ?? ''),
                    'referencia' => "EST-{$estudianteObj->registro_estudiante}-C{$cuota->id}",
                    'estudiante' => $estudianteObj->nombre . ' ' . $estudianteObj->apellido,
                    'ci' => $estudianteObj->ci
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de QR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir comprobante después del pago QR
     */
    public function subirComprobanteQR(Request $request, $pagoId)
    {
        $request->validate([
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        try {
            $estudiante = $request->auth_user;
            $registroEstudiante = $estudiante instanceof \App\Models\Estudiante
                ? $estudiante->registro_estudiante
                : $estudiante->id;

            $estudianteObj = Estudiante::where('registro_estudiante', $registroEstudiante)->first();
            if (!$estudianteObj) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            $pago = Pago::whereHas('cuota.planPago.inscripcion', function ($query) use ($estudianteObj) {
                    $query->where('estudiante_id', $estudianteObj->id);
                })
                ->where('id', $pagoId)
                ->where('metodo', 'QR')
                ->firstOrFail();

            if ($request->hasFile('comprobante')) {
                $file = $request->file('comprobante');
                $nombreArchivo = time() . '_' . $file->getClientOriginalName();
                $comprobantePath = $file->storeAs(
                    "comprobantes/estudiante_{$estudianteObj->registro_estudiante}/pago_{$pago->id}",
                    $nombreArchivo,
                    'public'
                );

                $pago->comprobante = $comprobantePath;
                $pago->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Comprobante subido exitosamente',
                    'data' => [
                        'pago' => $pago->load('cuota'),
                        'comprobante_url' => Storage::url($comprobantePath)
                    ]
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No se proporcionó archivo de comprobante'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir comprobante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consultar estado de pago QR
     */
    public function consultarEstadoQR(Request $request, $pagoId)
    {
        try {
            $estudiante = $request->auth_user;
            $registroEstudiante = $estudiante instanceof \App\Models\Estudiante
                ? $estudiante->registro_estudiante
                : $estudiante->id;

            $estudianteObj = Estudiante::where('registro_estudiante', $registroEstudiante)->first();
            if (!$estudianteObj) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            $pago = Pago::whereHas('cuota.planPago.inscripcion', function ($query) use ($estudianteObj) {
                    $query->where('estudiante_id', $estudianteObj->id);
                })
                ->where('id', $pagoId)
                ->where('metodo', 'QR')
                ->firstOrFail();

            $paymentGatewayService = app(PaymentGatewayService::class);
            $result = $paymentGatewayService->consultPaymentStatus($pago);

            $pago->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'pago' => $pago->load('cuota'),
                    'paymentInfo' => $result['paymentInfo'] ?? null,
                    'estado' => $pago->estado_pagofacil,
                    'verificado' => $pago->verificado
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al consultar estado del pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar y actualizar estado del plan cuando todas las cuotas están pagadas
     */
    private function verificarEstadoPlan($plan)
    {
        if (!$plan) {
            return;
        }

        $cuotas = $plan->cuotas;
        $todasPagadas = true;

        foreach ($cuotas as $cuota) {
            // Verificar si la cuota está completamente pagada
            if ($cuota->monto_pagado < $cuota->monto) {
                $todasPagadas = false;
                break;
            }
        }

        // El estado se verifica automáticamente con el accessor esta_completo del modelo
        // No necesitamos guardar un estado adicional, se calcula dinámicamente
        // Pero podemos registrar en bitácora cuando el plan se completa
        if ($todasPagadas) {
            $inscripcion = $plan->inscripcion;
            if ($inscripcion && $inscripcion->estudiante) {
                $estudiante = $inscripcion->estudiante;
                $usuario = $estudiante->usuario;
                if ($usuario) {
                    Bitacora::create([
                        'fecha' => now()->toDateString(),
                        'tabla' => 'Plan_pago',
                        'codTabla' => $plan->id,
                        'transaccion' => "Plan de pago completado para el estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}). Todas las cuotas han sido pagadas.",
                        'usuario_id' => $usuario->usuario_id
                    ]);
                }
            }
        }
    }
}
