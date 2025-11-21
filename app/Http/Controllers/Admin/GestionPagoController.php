<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Cuota;
use App\Models\PlanPagos;
use App\Models\Inscripcion;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class GestionPagoController extends Controller
{
    /**
     * Listar todos los pagos con filtros
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $estado = $request->get('estado', ''); // 'completo', 'pendiente', 'vencido'
            $fechaDesde = $request->get('fecha_desde', '');
            $fechaHasta = $request->get('fecha_hasta', '');

            $query = PlanPagos::with([
                'inscripcion.estudiante',
                'inscripcion.programa',
                'cuotas.pagos'
            ]);

            if ($search) {
                $query->whereHas('inscripcion.estudiante', function ($q) use ($search) {
                    $q->where('nombre', 'ILIKE', "%{$search}%")
                      ->orWhere('apellido', 'ILIKE', "%{$search}%")
                      ->orWhere('ci', 'ILIKE', "%{$search}%");
                });
            }

            if ($estado === 'completo') {
                $query->whereDoesntHave('cuotas', function ($q) {
                    $q->whereDoesntHave('pagos');
                });
            } elseif ($estado === 'pendiente') {
                $query->whereHas('cuotas', function ($q) {
                    $q->whereDoesntHave('pagos');
                });
            }

            if ($fechaDesde) {
                $query->whereHas('inscripcion', function ($q) use ($fechaDesde) {
                    $q->where('fecha', '>=', $fechaDesde);
                });
            }

            if ($fechaHasta) {
                $query->whereHas('inscripcion', function ($q) use ($fechaHasta) {
                    $q->where('fecha', '<=', $fechaHasta);
                });
            }

            $planes = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            // Enriquecer con información de estado
            $planes->getCollection()->transform(function ($plan) {
                $cuotas = $plan->cuotas;
                $totalCuotas = $cuotas->count();
                $cuotasPagadas = $cuotas->filter(function ($cuota) {
                    return $cuota->pagos()->exists() && $cuota->monto_pagado >= $cuota->monto;
                })->count();
                $cuotasPendientes = $totalCuotas - $cuotasPagadas;
                $cuotasVencidas = $cuotas->filter(function ($cuota) {
                    return !$cuota->esta_pagada && $cuota->esta_vencida;
                })->count();

                $plan->estado_plan = [
                    'esta_completo' => $plan->esta_completo,
                    'total_cuotas' => $totalCuotas,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'cuotas_pendientes' => $cuotasPendientes,
                    'cuotas_vencidas' => $cuotasVencidas,
                    'monto_total' => $plan->monto_total,
                    'monto_pagado' => $plan->monto_pagado,
                    'monto_pendiente' => $plan->monto_pendiente,
                    'porcentaje_pagado' => $plan->monto_total > 0 ? ($plan->monto_pagado / $plan->monto_total) * 100 : 0
                ];

                return $plan;
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
     * Obtener detalle de un plan de pago con todas sus cuotas y pagos
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $plan = PlanPagos::with([
                'inscripcion.estudiante',
                'inscripcion.programa',
                'cuotas' => function ($query) {
                    $query->with('pagos')->orderBy('fecha_ini');
                }
            ])->findOrFail($id);

            // Calcular estadísticas
            $cuotas = $plan->cuotas;
            $totalCuotas = $cuotas->count();
            $cuotasPagadas = $cuotas->filter(function ($cuota) {
                return $cuota->pagos()->exists() && $cuota->monto_pagado >= $cuota->monto;
            })->count();

            $plan->estado_plan = [
                'esta_completo' => $plan->esta_completo,
                'total_cuotas' => $totalCuotas,
                'cuotas_pagadas' => $cuotasPagadas,
                'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                'monto_total' => $plan->monto_total,
                'monto_pagado' => $plan->monto_pagado,
                'monto_pendiente' => $plan->monto_pendiente,
                'porcentaje_pagado' => $plan->monto_total > 0 ? ($plan->monto_pagado / $plan->monto_total) * 100 : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $plan,
                'message' => 'Plan de pago obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener plan de pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar pago manualmente (Admin)
     */
    public function registrarPago(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cuota_id' => 'required|exists:cuotas,id',
            'monto' => 'required|numeric|min:0.01',
            'token' => 'nullable|string|max:255',
            'fecha' => 'nullable|date',
            'observaciones' => 'nullable|string|max:500'
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
            $admin = $request->auth_user;
            $cuota = Cuota::with('planPago.inscripcion.estudiante')->findOrFail($request->cuota_id);

            // Validar que el monto no exceda el saldo pendiente
            if ($request->monto > $cuota->saldo_pendiente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto excede el saldo pendiente de la cuota'
                ], 422);
            }

            // Crear registro de pago
            $pago = Pago::create([
                'fecha' => $request->fecha ? Carbon::parse($request->fecha)->toDateString() : now()->toDateString(),
                'monto' => $request->monto,
                'token' => $request->token ?? null,
                'cuota_id' => $cuota->id
            ]);

            // Verificar si todas las cuotas del plan están pagadas
            $plan = $cuota->planPago;
            $this->verificarEstadoPlan($plan);

            // Registrar en bitácora
            $usuario = $admin->usuario ?? null;
            if ($usuario) {
                $estudiante = $plan->inscripcion->estudiante;
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Pagos',
                    'codTabla' => $pago->id,
                    'transaccion' => "Administrador registró pago manual de {$request->monto} Bs para el estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}). Token: " . ($request->token ?? 'N/A'),
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pago->load('cuota.planPago'),
                'message' => 'Pago registrado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aplicar penalidad a una cuota
     */
    public function aplicarPenalidad(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cuota_id' => 'required|exists:cuotas,id',
            'monto_penalidad' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|min:10|max:500'
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
            $admin = $request->auth_user;
            $cuota = Cuota::with('planPago.inscripcion.estudiante')->findOrFail($request->cuota_id);

            // Aumentar el monto de la cuota con la penalidad
            $montoAnterior = $cuota->monto;
            $cuota->monto += $request->monto_penalidad;
            $cuota->save();

            // Actualizar monto total del plan
            $plan = $cuota->planPago;
            $plan->monto_total += $request->monto_penalidad;
            $plan->save();

            // Registrar en bitácora
            $usuario = $admin->usuario ?? null;
            if ($usuario) {
                $estudiante = $plan->inscripcion->estudiante;
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Cuotas',
                    'codTabla' => $cuota->id,
                    'transaccion' => "Administrador aplicó penalidad de {$request->monto_penalidad} Bs a la cuota del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}). Motivo: {$request->motivo}. Monto anterior: {$montoAnterior} Bs, Monto nuevo: {$cuota->monto} Bs",
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $cuota->load('planPago'),
                'message' => 'Penalidad aplicada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aplicar penalidad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estado de pago manualmente
     */
    public function actualizarPago(Request $request, int $pagoId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'monto' => 'nullable|numeric|min:0.01',
            'fecha' => 'nullable|date',
            'token' => 'nullable|string|max:255',
            'observaciones' => 'nullable|string|max:500'
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
            $admin = $request->auth_user;
            $pago = Pago::with('cuota.planPago')->findOrFail($pagoId);

            $datosActualizacion = [];
            if ($request->has('monto')) {
                $datosActualizacion['monto'] = $request->monto;
            }
            if ($request->has('fecha')) {
                $datosActualizacion['fecha'] = Carbon::parse($request->fecha)->toDateString();
            }
            if ($request->has('token')) {
                $datosActualizacion['token'] = $request->token;
            }

            $pago->update($datosActualizacion);

            // Verificar si todas las cuotas del plan están pagadas
            $plan = $pago->cuota->planPago;
            $this->verificarEstadoPlan($plan);

            // Registrar en bitácora
            $usuario = $admin->usuario ?? null;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Pagos',
                    'codTabla' => $pago->id,
                    'transaccion' => "Administrador actualizó pago ID {$pago->id}. Cambios: " . json_encode($datosActualizacion),
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pago->load('cuota.planPago'),
                'message' => 'Pago actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar pago (solo si no está verificado)
     */
    public function eliminarPago(int $pagoId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $pago = Pago::with('cuota.planPago')->findOrFail($pagoId);
            $plan = $pago->cuota->planPago;

            $pago->delete();

            // Verificar estado del plan después de eliminar
            $this->verificarEstadoPlan($plan);

            // Registrar en bitácora
            $admin = request()->auth_user;
            $usuario = $admin->usuario ?? null;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Pagos',
                    'codTabla' => $pagoId,
                    'transaccion' => "Administrador eliminó pago ID {$pagoId}",
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar y actualizar estado del plan cuando todas las cuotas están pagadas
     */
    private function verificarEstadoPlan(PlanPagos $plan)
    {
        $cuotas = $plan->cuotas;
        $todasPagadas = true;

        foreach ($cuotas as $cuota) {
            if ($cuota->monto_pagado < $cuota->monto) {
                $todasPagadas = false;
                break;
            }
        }

        // Si todas las cuotas están pagadas, el plan está completo
        // Esto se verifica automáticamente con el accessor esta_completo
        // No necesitamos guardar un estado adicional, se calcula dinámicamente
    }
}

