<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VerificacionPagoController extends Controller
{
    /**
     * Lista pagos pendientes de verificación
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $metodo = $request->input('metodo', '');

            $pagos = Pago::with([
                    'cuota.planPagos.inscripcion.estudiante',
                    'cuota.planPagos.inscripcion.programa'
                ])
                ->where('verificado', false)
                ->when($search, function ($query) use ($search) {
                    $query->whereHas('cuota.planPagos.inscripcion.estudiante', function ($q) use ($search) {
                        $q->where('nombre', 'ILIKE', "%{$search}%")
                          ->orWhere('apellido', 'ILIKE', "%{$search}%")
                          ->orWhere('ci', 'ILIKE', "%{$search}%")
                          ->orWhere('registro_estudiante', 'ILIKE', "%{$search}%");
                    });
                })
                ->when($metodo, function ($query) use ($metodo) {
                    $query->where('metodo', $metodo);
                })
                ->orderBy('fecha', 'asc')
                ->paginate($perPage);

            $pagos->getCollection()->transform(function ($pago) {
                $estudiante = $pago->cuota->planPagos->inscripcion->estudiante;
                $programa = $pago->cuota->planPagos->inscripcion->programa;

                return [
                    'id' => $pago->id,
                    'fecha' => $pago->fecha,
                    'monto' => $pago->monto,
                    'metodo' => $pago->metodo,
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'registro' => $estudiante->registro_estudiante
                    ],
                    'programa' => $programa->nombre,
                    'cuota_id' => $pago->cuota->id,
                    'comprobante_url' => $pago->comprobante_path ? Storage::url($pago->comprobante_path) : null,
                    'observaciones' => $pago->observaciones,
                    'token' => $pago->token
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Pagos pendientes de verificación obtenidos exitosamente',
                'data' => $pagos,
                'resumen' => [
                    'total_pendientes' => Pago::where('verificado', false)->count(),
                    'total_monto_pendiente' => Pago::where('verificado', false)->sum('monto')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos pendientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de un pago
     */
    public function show($pagoId)
    {
        try {
            $pago = Pago::with([
                    'cuota.planPagos.inscripcion.estudiante',
                    'cuota.planPagos.inscripcion.programa',
                    'verificador'
                ])
                ->findOrFail($pagoId);

            $estudiante = $pago->cuota->planPagos->inscripcion->estudiante;

            return response()->json([
                'success' => true,
                'message' => 'Detalle de pago obtenido exitosamente',
                'data' => [
                    'pago' => [
                        'id' => $pago->id,
                        'fecha' => $pago->fecha,
                        'monto' => $pago->monto,
                        'metodo' => $pago->metodo,
                        'token' => $pago->token,
                        'verificado' => $pago->verificado,
                        'fecha_verificacion' => $pago->fecha_verificacion,
                        'verificado_por_nombre' => $pago->verificador ? $pago->verificador->nombre : null,
                        'comprobante_url' => $pago->comprobante_path ? Storage::url($pago->comprobante_path) : null,
                        'observaciones' => $pago->observaciones
                    ],
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'registro' => $estudiante->registro_estudiante,
                        'celular' => $estudiante->celular
                    ],
                    'programa' => $pago->cuota->planPagos->inscripcion->programa,
                    'cuota' => [
                        'id' => $pago->cuota->id,
                        'monto' => $pago->cuota->monto,
                        'fecha_ini' => $pago->cuota->fecha_ini,
                        'fecha_fin' => $pago->cuota->fecha_fin
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar/verificar un pago
     */
    public function approve(Request $request, $pagoId)
    {
        DB::beginTransaction();
        try {
            $admin = $request->auth_user;

            $pago = Pago::with([
                    'cuota.planPagos.inscripcion.estudiante',
                    'cuota.planPagos.inscripcion.programa'
                ])
                ->findOrFail($pagoId);

            if ($pago->verificado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya ha sido verificado'
                ], 400);
            }

            // Verificar pago
            $pago->verificado = true;
            $pago->fecha_verificacion = now();
            $pago->verificado_por = $admin->id;
            $pago->observaciones = $request->input('observaciones', $pago->observaciones);
            $pago->save();

            $estudiante = $pago->cuota->planPagos->inscripcion->estudiante;
            $programa = $pago->cuota->planPagos->inscripcion->programa;

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'pagos',
                'codTable' => $pago->id,
                'transaccion' => "Pago de {$pago->monto} Bs del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) para el programa '{$programa->nombre}' fue VERIFICADO Y APROBADO por {$admin->nombre} {$admin->apellido}",
                'Usuario_id' => $admin->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago verificado y aprobado exitosamente',
                'data' => $pago
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar un pago
     */
    public function reject(Request $request, $pagoId)
    {
        $request->validate([
            'motivo' => 'required|string|min:10'
        ]);

        DB::beginTransaction();
        try {
            $admin = $request->auth_user;

            $pago = Pago::with([
                    'cuota.planPagos.inscripcion.estudiante',
                    'cuota.planPagos.inscripcion.programa'
                ])
                ->findOrFail($pagoId);

            if ($pago->verificado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya ha sido verificado y no puede ser rechazado'
                ], 400);
            }

            $estudiante = $pago->cuota->planPagos->inscripcion->estudiante;
            $programa = $pago->cuota->planPagos->inscripcion->programa;

            // Actualizar observaciones con motivo de rechazo
            $pago->observaciones = "RECHAZADO: " . $request->motivo;
            $pago->save();

            // Eliminar el pago rechazado
            $pagoData = $pago->toArray();
            $pago->delete();

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'pagos',
                'codTable' => $pagoData['id'],
                'transaccion' => "Pago de {$pagoData['monto']} Bs del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) para el programa '{$programa->nombre}' fue RECHAZADO por {$admin->nombre} {$admin->apellido}. Motivo: {$request->motivo}",
                'Usuario_id' => $admin->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago rechazado exitosamente. El estudiante deberá registrar un nuevo pago.',
                'data' => [
                    'motivo' => $request->motivo
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen de pagos verificados
     */
    public function getVerifiedSummary(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth());
            $fechaFin = $request->input('fecha_fin', now()->endOfMonth());

            $pagosVerificados = Pago::where('verificado', true)
                ->whereBetween('fecha_verificacion', [$fechaInicio, $fechaFin])
                ->with(['cuota.planPagos.inscripcion.programa'])
                ->get();

            $resumen = [
                'total_pagos' => $pagosVerificados->count(),
                'monto_total' => $pagosVerificados->sum('monto'),
                'por_metodo' => [
                    'QR' => $pagosVerificados->where('metodo', 'QR')->sum('monto'),
                    'TRANSFERENCIA' => $pagosVerificados->where('metodo', 'TRANSFERENCIA')->sum('monto'),
                    'EFECTIVO' => $pagosVerificados->where('metodo', 'EFECTIVO')->sum('monto')
                ],
                'por_programa' => $pagosVerificados->groupBy(function ($pago) {
                    return $pago->cuota->planPagos->inscripcion->programa->nombre;
                })->map(function ($pagos, $programa) {
                    return [
                        'programa' => $programa,
                        'total_pagos' => $pagos->count(),
                        'monto_total' => $pagos->sum('monto')
                    ];
                })->values()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Resumen de pagos verificados obtenido exitosamente',
                'data' => $resumen
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de pagos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
