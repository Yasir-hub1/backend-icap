<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoController extends Controller
{
    /**
     * Listado de cuotas del estudiante (pendientes y pagadas)
     */
    public function index(Request $request)
    {
        try {
            $estudiante = $request->auth_user;

            // Obtener todas las cuotas del estudiante a través de inscripciones
            $cuotas = Cuota::whereHas('planPagos.inscripcion', function ($query) use ($estudiante) {
                    $query->where('Estudiante_id', $estudiante->id);
                })
                ->with([
                    'planPagos.inscripcion.programa',
                    'pagos' => function ($query) {
                        $query->orderBy('fecha', 'desc');
                    }
                ])
                ->orderBy('fecha_ini', 'asc')
                ->get()
                ->map(function ($cuota) {
                    return [
                        'id' => $cuota->id,
                        'programa' => $cuota->planPagos->inscripcion->programa->nombre,
                        'monto' => $cuota->monto,
                        'fecha_ini' => $cuota->fecha_ini,
                        'fecha_fin' => $cuota->fecha_fin,
                        'estado' => $cuota->esta_pagada ? 'PAGADA' : ($cuota->esta_vencida ? 'VENCIDA' : 'PENDIENTE'),
                        'esta_pagada' => $cuota->esta_pagada,
                        'esta_vencida' => $cuota->esta_vencida,
                        'esta_pendiente' => $cuota->esta_pendiente,
                        'monto_pagado' => $cuota->monto_pagado,
                        'saldo_pendiente' => $cuota->saldo_pendiente,
                        'pagos' => $cuota->pagos->map(function ($pago) {
                            return [
                                'id' => $pago->id,
                                'fecha' => $pago->fecha,
                                'monto' => $pago->monto,
                                'metodo' => $pago->metodo,
                                'verificado' => $pago->verificado,
                                'fecha_verificacion' => $pago->fecha_verificacion,
                                'observaciones' => $pago->observaciones
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Cuotas obtenidas exitosamente',
                'data' => [
                    'cuotas' => $cuotas,
                    'total_cuotas' => $cuotas->count(),
                    'cuotas_pagadas' => $cuotas->where('esta_pagada', true)->count(),
                    'cuotas_pendientes' => $cuotas->where('esta_pendiente', true)->count(),
                    'cuotas_vencidas' => $cuotas->where('esta_vencida', true)->count()
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
     * Detalle de una cuota específica
     */
    public function show(Request $request, $cuotaId)
    {
        try {
            $estudiante = $request->auth_user;

            $cuota = Cuota::whereHas('planPagos.inscripcion', function ($query) use ($estudiante) {
                    $query->where('Estudiante_id', $estudiante->id);
                })
                ->with([
                    'planPagos.inscripcion.programa',
                    'pagos.verificador'
                ])
                ->findOrFail($cuotaId);

            return response()->json([
                'success' => true,
                'message' => 'Detalle de cuota obtenido exitosamente',
                'data' => [
                    'cuota' => [
                        'id' => $cuota->id,
                        'programa' => $cuota->planPagos->inscripcion->programa->nombre,
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
                            'metodo' => $pago->metodo,
                            'verificado' => $pago->verificado,
                            'fecha_verificacion' => $pago->fecha_verificacion,
                            'verificado_por' => $pago->verificador ? $pago->verificador->nombre : null,
                            'comprobante_url' => $pago->comprobante_path ? Storage::url($pago->comprobante_path) : null,
                            'observaciones' => $pago->observaciones
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
     * Registrar pago con comprobante
     */
    public function store(Request $request)
    {
        $request->validate([
            'cuota_id' => 'required|exists:cuotas,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|in:QR,TRANSFERENCIA,EFECTIVO',
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120' // 5MB max
        ]);

        DB::beginTransaction();
        try {
            $estudiante = $request->auth_user;

            // Verificar que la cuota pertenezca al estudiante
            $cuota = Cuota::whereHas('planPagos.inscripcion', function ($query) use ($estudiante) {
                    $query->where('Estudiante_id', $estudiante->id);
                })
                ->findOrFail($request->cuota_id);

            // Verificar que la cuota no esté ya pagada
            if ($cuota->esta_pagada) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuota ya ha sido pagada'
                ], 400);
            }

            // Subir comprobante
            $comprobante = $request->file('comprobante');
            $filename = 'comprobante_' . $estudiante->registro_estudiante . '_' . time() . '.' . $comprobante->getClientOriginalExtension();
            $comprobantePath = $comprobante->storeAs('comprobantes', $filename, 'public');

            // Crear registro de pago
            $pago = Pago::create([
                'fecha' => now(),
                'monto' => $request->monto,
                'token' => Str::random(32),
                'metodo' => $request->metodo,
                'comprobante_path' => $comprobantePath,
                'observaciones' => $request->observaciones ?? 'Pago registrado por estudiante',
                'verificado' => false,
                'cuotas_id' => $cuota->id
            ]);

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'pagos',
                'codTable' => $pago->id,
                'transaccion' => "Estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) registró pago de {$request->monto} Bs por método {$request->metodo}. Pendiente de verificación.",
                'Usuario_id' => $estudiante->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente. Está pendiente de verificación por el administrador.',
                'data' => [
                    'pago' => $pago,
                    'comprobante_url' => Storage::url($comprobantePath)
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
     * Obtener información para generar QR de pago
     */
    public function getQRInfo(Request $request, $cuotaId)
    {
        try {
            $estudiante = $request->auth_user;

            $cuota = Cuota::whereHas('planPagos.inscripcion', function ($query) use ($estudiante) {
                    $query->where('Estudiante_id', $estudiante->id);
                })
                ->with('planPagos.inscripcion.programa')
                ->findOrFail($cuotaId);

            if ($cuota->esta_pagada) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuota ya ha sido pagada'
                ], 400);
            }

            // Información para generar QR (ajustar según banco/pasarela)
            return response()->json([
                'success' => true,
                'data' => [
                    'monto' => $cuota->saldo_pendiente,
                    'concepto' => "Cuota {$cuota->planPagos->inscripcion->programa->nombre}",
                    'referencia' => "EST-{$estudiante->registro_estudiante}-C{$cuota->id}",
                    'estudiante' => $estudiante->nombre . ' ' . $estudiante->apellido,
                    'ci' => $estudiante->ci
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
}
