<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

date_default_timezone_set('America/La_Paz');

class PaymentController extends Controller
{
    /**
     * Callback desde PagoFacil (MÉTODO RECOMENDADO)
     * PagoFácil envía un POST a esta URL cuando el usuario paga
     */
    public function callback(Request $request)
    {
        try {
            Log::info('Callback recibido de PagoFacil', [
                'data' => $request->all(),
                'headers' => $request->headers->all(),
                'method' => $request->method()
            ]);

            $pedidoID = $request->input("PedidoID")
                     ?? $request->input("paymentNumber")
                     ?? $request->input("payment_number")
                     ?? $request->input("nro_pago");

            $estado = $request->input("Estado")
                   ?? $request->input("status")
                   ?? $request->input("estado");

            $fecha = $request->input("Fecha");
            $hora = $request->input("Hora");
            $metodoPago = $request->input("MetodoPago");

            Log::info('Datos extraídos del callback según documentación', [
                'PedidoID' => $pedidoID,
                'Estado' => $estado,
                'Fecha' => $fecha,
                'Hora' => $hora,
                'MetodoPago' => $metodoPago
            ]);

            if (!$pedidoID) {
                Log::warning('Callback sin PedidoID', ['request' => $request->all()]);
                return response()->json([
                    'error' => 1,
                    'status' => 0,
                    'message' => "PedidoID no encontrado en el callback",
                    'values' => false
                ], 400);
            }

            // Estado = "APROBADO" significa que está pagado
            $estadoAprobado = in_array(strtoupper($estado ?? ''), [
                'APROBADO', 'APPROVED', 'COMPLETED', 'PAID', 'SUCCESS',
                '1', 1, true, 'PAGADO'
            ]);

            if ($estadoAprobado) {
                $paymentGatewayService = app(PaymentGatewayService::class);

                // Buscar el pago por nuestro ID interno
                $pago = Pago::where('nro_pago', $pedidoID)->first();

                if ($pago) {
                    // Guardar datos del callback
                    $pago->callback_data = json_encode($request->all());
                    
                    // Actualizar información adicional si viene en el callback
                    if ($fecha && $hora) {
                        try {
                            $pago->fecha_verificacion = \Carbon\Carbon::parse($fecha . ' ' . $hora);
                        } catch (\Exception $e) {
                            Log::warning('Error parseando fecha del callback', [
                                'fecha' => $fecha,
                                'hora' => $hora
                            ]);
                        }
                    }

                    if ($metodoPago) {
                        Log::info('Método de pago recibido en callback', ['metodo' => $metodoPago]);
                    }

                    $pago->save();
                }

                // Confirmar el pago (actualiza estado a completado)
                $paymentGatewayService->confirmPayment($pedidoID);

                Log::info('Pago confirmado desde callback', [
                    'PedidoID' => $pedidoID,
                    'Estado' => $estado,
                    'Fecha' => $fecha,
                    'Hora' => $hora
                ]);
            } else {
                Log::info('Callback recibido pero estado no es APROBADO', [
                    'PedidoID' => $pedidoID,
                    'Estado' => $estado
                ]);
            }

            // IMPORTANTE: Responder con este formato exacto
            return response()->json([
                'error' => 0,
                'status' => 1,
                'message' => 'Notificación recibida',
                'values' => true
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Error en callback de pago: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
                'request' => $request->all()
            ]);

            // Aún así responder OK para evitar reenvíos de PagoFácil
            return response()->json([
                'error' => 1,
                'status' => 0,
                'message' => "Error al procesar el callback: " . $th->getMessage(),
                'values' => false
            ], 200);
        }
    }

    /**
     * Consultar estado de pago
     */
    public function checkStatus($id)
    {
        $pago = Pago::with([
            'cuota.planPago.inscripcion.programa',
            'cuota.planPago.inscripcion.estudiante'
        ])->findOrFail($id);

        $paymentGatewayService = app(PaymentGatewayService::class);
        $result = $paymentGatewayService->consultPaymentStatus($pago);

        $pago->refresh();

        $paymentInfo = null;
        if (is_array($result) && isset($result['paymentInfo'])) {
            $paymentInfo = $result['paymentInfo'];
        }

        return response()->json([
            'success' => true,
            'pago' => $pago,
            'result' => $result,
            'paymentInfo' => $paymentInfo
        ]);
    }
}

