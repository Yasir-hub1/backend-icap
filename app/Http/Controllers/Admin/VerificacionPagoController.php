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
    public function listar(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $metodo = $request->input('metodo', '');

            $pagos = Pago::with([
                    'cuota.planPago.inscripcion.estudiante.usuario',
                    'cuota.planPago.inscripcion.programa.ramaAcademica',
                    'cuota.planPago.inscripcion.programa.tipoPrograma',
                    'cuota.planPago.inscripcion.programa.institucion'
                ])
                ->when($search, function ($query) use ($search) {
                    $query->whereHas('cuota.planPago.inscripcion.estudiante', function ($q) use ($search) {
                        $q->where('nombre', 'ILIKE', "%{$search}%")
                          ->orWhere('apellido', 'ILIKE', "%{$search}%")
                          ->orWhere('ci', 'ILIKE', "%{$search}%")
                          ->orWhere('registro_estudiante', 'ILIKE', "%{$search}%");
                    });
                })
                ->when($metodo, function ($query) use ($metodo) {
                    $query->where('metodo', $metodo);
                })
                ->orderBy('fecha', 'desc')
                ->paginate($perPage);

            $pagos->getCollection()->transform(function ($pago) {
                $planPago = $pago->cuota->planPago;
                $inscripcion = $planPago ? $planPago->inscripcion : null;
                $estudiante = $inscripcion ? $inscripcion->estudiante : null;
                $programa = $inscripcion ? $inscripcion->programa : null;

                return [
                    'id' => $pago->id,
                    'fecha' => $pago->fecha,
                    'monto' => $pago->monto,
                    'metodo' => $pago->metodo,
                    'verificado' => $pago->verificado,
                    'fecha_verificacion' => $pago->fecha_verificacion,
                    'estudiante' => $estudiante ? [
                        'id' => $estudiante->id,
                        'nombre' => $estudiante->nombre,
                        'apellido' => $estudiante->apellido,
                        'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'registro_estudiante' => $estudiante->registro_estudiante,
                        'email' => $estudiante->usuario->email ?? 'N/A'
                    ] : null,
                    'programa' => $programa ? [
                        'id' => $programa->id,
                        'nombre' => $programa->nombre,
                        'rama_academica' => $programa->ramaAcademica->nombre ?? 'N/A',
                        'tipo_programa' => $programa->tipoPrograma->nombre ?? 'N/A',
                        'institucion' => $programa->institucion->nombre ?? 'N/A'
                    ] : null,
                    'cuota' => [
                        'id' => $pago->cuota->id,
                        'fecha_ini' => $pago->cuota->fecha_ini,
                        'fecha_fin' => $pago->cuota->fecha_fin,
                        'monto' => $pago->cuota->monto
                    ],
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
                    'total_verificados' => Pago::where('verificado', true)->count(),
                    'total_monto_pendiente' => Pago::where('verificado', false)->sum('monto'),
                    'total_monto_verificado' => Pago::where('verificado', true)->sum('monto')
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
    public function obtener($pagoId)
    {
        try {
            $pago = Pago::with([
                    'cuota.planPago.inscripcion.estudiante.usuario',
                    'cuota.planPago.inscripcion.programa.ramaAcademica',
                    'cuota.planPago.inscripcion.programa.tipoPrograma',
                    'cuota.planPago.inscripcion.programa.institucion',
                    'verificador.persona'
                ])
                ->findOrFail($pagoId);

            $planPago = $pago->cuota->planPago;
            $inscripcion = $planPago ? $planPago->inscripcion : null;
            $estudiante = $inscripcion ? $inscripcion->estudiante : null;
            $programa = $inscripcion ? $inscripcion->programa : null;

            return response()->json([
                'success' => true,
                'message' => 'Detalle de pago obtenido exitosamente',
                'data' => [
                    'id' => $pago->id,
                    'fecha' => $pago->fecha,
                    'monto' => $pago->monto,
                    'metodo' => $pago->metodo,
                    'token' => $pago->token,
                    'verificado' => $pago->verificado,
                    'fecha_verificacion' => $pago->fecha_verificacion,
                    'verificado_por' => $pago->verificador ? [
                        'id' => $pago->verificador->usuario_id,
                        'nombre' => $pago->verificador->persona->nombre ?? 'N/A',
                        'apellido' => $pago->verificador->persona->apellido ?? 'N/A'
                    ] : null,
                    'comprobante_url' => $pago->comprobante_path ? Storage::url($pago->comprobante_path) : null,
                    'observaciones' => $pago->observaciones,
                    'estudiante' => $estudiante ? [
                        'id' => $estudiante->id,
                        'nombre' => $estudiante->nombre,
                        'apellido' => $estudiante->apellido,
                        'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'registro_estudiante' => $estudiante->registro_estudiante,
                        'celular' => $estudiante->celular,
                        'email' => $estudiante->usuario->email ?? 'N/A'
                    ] : null,
                    'programa' => $programa ? [
                        'id' => $programa->id,
                        'nombre' => $programa->nombre,
                        'rama_academica' => $programa->ramaAcademica->nombre ?? 'N/A',
                        'tipo_programa' => $programa->tipoPrograma->nombre ?? 'N/A',
                        'institucion' => $programa->institucion->nombre ?? 'N/A'
                    ] : null,
                    'cuota' => [
                        'id' => $pago->cuota->id,
                        'monto' => $pago->cuota->monto,
                        'fecha_ini' => $pago->cuota->fecha_ini,
                        'fecha_fin' => $pago->cuota->fecha_fin
                    ],
                    'inscripcion' => $inscripcion ? [
                        'id' => $inscripcion->id,
                        'fecha' => $inscripcion->fecha,
                        'fecha_formatted' => $inscripcion->fecha ? \Carbon\Carbon::parse($inscripcion->fecha)->format('d/m/Y') : 'N/A'
                    ] : null
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
    public function aprobar(Request $request, $pagoId)
    {
        DB::beginTransaction();
        try {
            $admin = auth()->user();

            $pago = Pago::with([
                    'cuota.planPago.inscripcion.estudiante',
                    'cuota.planPago.inscripcion.programa'
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
            $pago->verificado_por = $admin->usuario_id ?? $admin->id ?? auth()->id();
            $pago->observaciones = $request->input('observaciones', $pago->observaciones);
            $pago->save();

            $planPago = $pago->cuota->planPago;
            $inscripcion = $planPago ? $planPago->inscripcion : null;
            $estudiante = $inscripcion ? $inscripcion->estudiante : null;
            $programa = $inscripcion ? $inscripcion->programa : null;

            // Registrar en bitácora
            if ($estudiante && $programa) {
                $adminNombre = $admin->nombre ?? $admin->persona->nombre ?? 'Admin';
                $adminApellido = $admin->apellido ?? $admin->persona->apellido ?? '';
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'pagos',
                    'codTabla' => $pago->id,
                    'transaccion' => "Pago de {$pago->monto} Bs del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) para el programa '{$programa->nombre}' fue VERIFICADO Y APROBADO por {$adminNombre} {$adminApellido}",
                    'usuario_id' => $admin->usuario_id ?? $admin->id ?? auth()->id()
                ]);
            }

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
    public function rechazar(Request $request, $pagoId)
    {
        $request->validate([
            'motivo' => 'required|string|min:10'
        ]);

        DB::beginTransaction();
        try {
            $admin = auth()->user();

            $pago = Pago::with([
                    'cuota.planPago.inscripcion.estudiante',
                    'cuota.planPago.inscripcion.programa'
                ])
                ->findOrFail($pagoId);

            if ($pago->verificado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pago ya ha sido verificado y no puede ser rechazado'
                ], 400);
            }

            $planPago = $pago->cuota->planPago;
            $inscripcion = $planPago ? $planPago->inscripcion : null;
            $estudiante = $inscripcion ? $inscripcion->estudiante : null;
            $programa = $inscripcion ? $inscripcion->programa : null;

            // Actualizar observaciones con motivo de rechazo
            $pagoData = $pago->toArray();
            $pago->observaciones = "RECHAZADO: " . $request->motivo;
            $pago->save();

            // Eliminar el pago rechazado
            $pago->delete();

            // Registrar en bitácora
            if ($estudiante && $programa) {
                $adminNombre = $admin->nombre ?? $admin->persona->nombre ?? 'Admin';
                $adminApellido = $admin->apellido ?? $admin->persona->apellido ?? '';
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'pagos',
                    'codTabla' => $pagoData['id'],
                    'transaccion' => "Pago de {$pagoData['monto']} Bs del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) para el programa '{$programa->nombre}' fue RECHAZADO por {$adminNombre} {$adminApellido}. Motivo: {$request->motivo}",
                    'usuario_id' => $admin->usuario_id ?? $admin->id ?? auth()->id()
                ]);
            }

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
    public function obtenerResumenVerificados(Request $request)
    {
        try {
            $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
            $fechaFin = $request->input('fecha_fin', now()->endOfMonth()->toDateString());

            $pagosVerificados = Pago::where('verificado', true)
                ->whereBetween('fecha_verificacion', [$fechaInicio, $fechaFin])
                ->with(['cuota.planPago.inscripcion.programa'])
                ->get();

            $resumen = [
                'total_pagos' => $pagosVerificados->count(),
                'monto_total' => $pagosVerificados->sum('monto'),
                'por_metodo' => [
                    'QR' => $pagosVerificados->where('metodo', 'QR')->sum('monto'),
                    'TRANSFERENCIA' => $pagosVerificados->where('metodo', 'TRANSFERENCIA')->sum('monto'),
                    'EFECTIVO' => $pagosVerificados->where('metodo', 'EFECTIVO')->sum('monto')
                ],
                'por_programa' => $pagosVerificados->filter(function ($pago) {
                    return $pago->cuota && $pago->cuota->planPago && $pago->cuota->planPago->inscripcion && $pago->cuota->planPago->inscripcion->programa;
                })->groupBy(function ($pago) {
                    return $pago->cuota->planPago->inscripcion->programa->nombre;
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
