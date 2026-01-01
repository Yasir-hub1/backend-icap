<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Cuota;
use App\Models\Estudiante;
use App\Models\Notificacion;
use App\Models\Usuario;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

date_default_timezone_set('America/La_Paz');

class PaymentGatewayService
{
    private $client_guzzle;
    private $url_login;
    private $url_list_methods;
    private $url_qr;
    private $url_query;
    private $clientCode;
    private $callbackUrl;
    private $tokenService;
    private $tokenSecret;
    private const CACHE_KEY_ACCESS_TOKEN = 'pagofacil_access_token';
    private const CACHE_KEY_TOKEN_EXPIRES = 'pagofacil_token_expires';
    private const CACHE_KEY_PAYMENT_METHOD_ID = 'pagofacil_payment_method_id';

    public function __construct()
    {
        $this->client_guzzle = new Client();
        $this->url_login = "https://masterqr.pagofacil.com.bo/api/services/v2/login";
        $this->url_list_methods = "https://masterqr.pagofacil.com.bo/api/services/v2/list-enabled-services";
        $this->url_qr = "https://masterqr.pagofacil.com.bo/api/services/v2/generate-qr";
        $this->url_query = "https://masterqr.pagofacil.com.bo/api/services/v2/query-transaction";

        // Obtener credenciales del .env
        $this->tokenService = env('PAGO_FACIL_TCTOKEN_SERVICE',"51247fae280c20410824977b0781453df59fad5b23bf2a0d14e884482f91e09078dbe5966e0b970ba696ec4caf9aa5661802935f86717c481f1670e63f35d504a62547a9de71bfc76be2c2ae01039ebcb0f74a96f0f1f56542c8b51ef7a2a6da9ea16f23e52ecc4485b69640297a5ec6a701498d2f0e1b4e7f4b7803bf5c2eba");
        $this->tokenSecret = env('PAGO_FACIL_TCTOKEN_SECRET',"0C351C6679844041AA31AF9C");
        $this->clientCode = env('PAGO_FACIL_CLIENT_CODE', env('PAGO_FACIL_COMERCE_ID'));
        $this->callbackUrl = env('PAGO_FACIL_CALLBACK_URL', url('/api/payment/callback'));

        // Log para debugging (solo en desarrollo)
        if (config('app.debug')) {
            Log::debug('PaymentGatewayService inicializado', [
                'tokenService_exists' => !empty($this->tokenService),
                'tokenSecret_exists' => !empty($this->tokenSecret),
                'clientCode' => $this->clientCode,
                'callbackUrl' => $this->callbackUrl
            ]);
        }
    }

    /**
     * Método de prueba para verificar credenciales
     */
    public function verificarCredenciales()
    {
        return [
            'tokenService' => !empty($this->tokenService) ? 'Configurado' : 'No configurado',
            'tokenSecret' => !empty($this->tokenSecret) ? 'Configurado' : 'No configurado',
            'clientCode' => $this->clientCode ?? 'No configurado',
            'callbackUrl' => $this->callbackUrl ?? 'No configurado'
        ];
    }

    /**
     * Autenticarse en la API de PagoFacil y obtener accessToken
     */
    private function authenticate()
    {
        try {
            if (!$this->tokenService || !$this->tokenSecret) {
                throw new \Exception('Credenciales de PagoFacil no configuradas. Verifica PAGO_FACIL_TCTOKEN_SERVICE y PAGO_FACIL_TCTOKEN_SECRET en .env');
            }

            $headers = [
                'Content-Type' => 'application/json',
                'tcTokenService' => $this->tokenService,
                'tcTokenSecret' => $this->tokenSecret
            ];

            Log::info('Autenticando en PagoFacil API', ['url' => $this->url_login]);

            $response = $this->client_guzzle->post($this->url_login, [
                'headers' => $headers
            ]);

            $result = json_decode($response->getBody()->getContents());

            if (isset($result->error) && $result->error == 1) {
                throw new \Exception('Error en autenticación: ' . ($result->message ?? 'Error desconocido'));
            }

            if (!isset($result->values->accessToken)) {
                throw new \Exception('No se recibió accessToken en la respuesta de autenticación');
            }

            $accessToken = $result->values->accessToken;
            $expiresInMinutes = $result->values->expiresInMinutes ?? 200;

            // Guardar token en cache con tiempo de expiración (con margen de 5 minutos antes)
            $cacheTime = (int) ($expiresInMinutes - 5) * 60;

            Cache::put(self::CACHE_KEY_ACCESS_TOKEN, $accessToken, $cacheTime);
            Cache::put(self::CACHE_KEY_TOKEN_EXPIRES, now()->addMinutes($expiresInMinutes), $cacheTime);

            Log::info('Autenticación exitosa en PagoFacil', [
                'expiresInMinutes' => $expiresInMinutes,
                'cacheTime' => $cacheTime
            ]);

            return $accessToken;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error en autenticación PagoFacil', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            throw new \Exception('Error al autenticarse en PagoFacil: ' . $e->getMessage());
        }
    }

    /**
     * Obtener accessToken válido (desde cache o renovarlo si es necesario)
     */
    private function getAccessToken()
    {
        $accessToken = Cache::get(self::CACHE_KEY_ACCESS_TOKEN);
        $expiresAt = Cache::get(self::CACHE_KEY_TOKEN_EXPIRES);

        if (!$accessToken || !$expiresAt || now()->greaterThan($expiresAt)) {
            Log::info('Token expirado o no existe, renovando autenticación');
            return $this->authenticate();
        }

        return $accessToken;
    }

    /**
     * Listar métodos de pago habilitados y obtener paymentMethodId
     */
    private function getPaymentMethodId()
    {
        $paymentMethodId = Cache::get(self::CACHE_KEY_PAYMENT_METHOD_ID);

        if ($paymentMethodId) {
            return $paymentMethodId;
        }

        try {
            $accessToken = $this->getAccessToken();

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken
            ];

            Log::info('Listando métodos de pago habilitados', ['url' => $this->url_list_methods]);

            $response = $this->client_guzzle->get($this->url_list_methods, [
                'headers' => $headers
            ]);

            $result = json_decode($response->getBody()->getContents());

            if (isset($result->error) && $result->error == 1) {
                throw new \Exception('Error al listar métodos: ' . ($result->message ?? 'Error desconocido'));
            }

            $paymentMethodId = null;
            if (isset($result->values) && is_array($result->values)) {
                foreach ($result->values as $method) {
                    if (isset($method->paymentMethodName) &&
                        (stripos($method->paymentMethodName, 'QR') !== false ||
                         stripos($method->paymentMethodName, 'qr') !== false)) {
                        $paymentMethodId = $method->paymentMethodId;
                        Log::info('Método QR encontrado', [
                            'paymentMethodId' => $paymentMethodId,
                            'paymentMethodName' => $method->paymentMethodName ?? null
                        ]);
                        break;
                    }
                }

                if (!$paymentMethodId && isset($result->values[0]->paymentMethodId)) {
                    $paymentMethodId = $result->values[0]->paymentMethodId;
                    Log::info('Usando primer método disponible', ['paymentMethodId' => $paymentMethodId]);
                }
            }

            if (!$paymentMethodId) {
                $paymentMethodId = env('PAGO_FACIL_PAYMENT_METHOD_ID', 4);
                Log::warning('No se encontró paymentMethodId en respuesta, usando valor por defecto', [
                    'paymentMethodId' => $paymentMethodId
                ]);
            }

            Cache::put(self::CACHE_KEY_PAYMENT_METHOD_ID, $paymentMethodId, 86400);

            return $paymentMethodId;

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $paymentMethodId = env('PAGO_FACIL_PAYMENT_METHOD_ID', 4);
            Log::warning('Usando paymentMethodId por defecto debido a error', [
                'paymentMethodId' => $paymentMethodId
            ]);
            return $paymentMethodId;
        }
    }

    /**
     * Procesar pago con pasarela QR para una cuota
     */
    public function processQRPayment(Cuota $cuota, Estudiante $estudiante)
    {
        DB::beginTransaction();
        try {
            // Verificar que la cuota no esté pagada
            if ($cuota->esta_pagada) {
                throw new \Exception('Esta cuota ya ha sido pagada');
            }

            // Generar número de pago único
            $nroPago = $this->generateNroPago();

            // Cargar relación usuario si no está cargada
            if (!$estudiante->relationLoaded('usuario')) {
                $estudiante->load('usuario');
            }

            // Crear registro de pago
            $pago = Pago::create([
                'fecha' => now()->toDateString(),
                'monto' => $cuota->saldo_pendiente,
                'token' => $nroPago,
                'cuota_id' => $cuota->id,
                'nro_pago' => $nroPago,
                'metodo' => 'QR',
                'estado_pagofacil' => 'pendiente',
                'verificado' => false
            ]);

            // Preparar detalles del pago
            $detalles = $this->preparePaymentDetails($cuota, $estudiante);

            // Generar QR
            $result = $this->generateQR($pago, $detalles, $estudiante);

            // Verificar si hay error en la respuesta
            if (isset($result->error) && $result->error == 1) {
                DB::rollBack();
                throw new \Exception($result->message ?? $result->data->message ?? 'Error al generar QR');
            }

            if (isset($result->success) && $result->success === false) {
                DB::rollBack();
                throw new \Exception($result->message ?? $result->data->message ?? 'Error al generar QR');
            }

            // Procesar respuesta del QR
            Log::info('Procesando respuesta QR', [
                'error' => $result->error ?? null,
                'status' => $result->status ?? null,
                'message' => $result->message ?? null,
                'has_values' => isset($result->values)
            ]);

            $qrImage = null;
            $transactionId = null;

            // Obtener QR base64 de la respuesta
            if (isset($result->values->qrBase64)) {
                $qrImage = $result->values->qrBase64;
            } elseif (isset($result->qrBase64)) {
                $qrImage = $result->qrBase64;
            } elseif (isset($result->values->qrImage)) {
                $qrImage = $result->values->qrImage;
            } elseif (isset($result->data->qrBase64)) {
                $qrImage = $result->data->qrBase64;
            } elseif (isset($result->data->qrImage)) {
                $qrImage = $result->data->qrImage;
            } elseif (isset($result->qrImage)) {
                $qrImage = $result->qrImage;
            }

            if ($qrImage) {
                // Decodificar base64 y guardar
                $binaryData = base64_decode($qrImage);
                if ($binaryData !== false && strlen($binaryData) > 0) {
                    $fileName = time() . '_' . $nroPago . '.png';
                    Storage::disk('public')->put('pagos/qr/' . $fileName, $binaryData, 'public');
                    // Guardar ruta relativa en la base de datos
                    $pago->qr_image = Storage::url('pagos/qr/' . $fileName);
                    Log::info('QR guardado exitosamente', ['file' => $fileName, 'size' => strlen($binaryData)]);
                } else {
                    Log::warning('Error decodificando QR base64 o datos vacíos');
                }
            } else {
                Log::warning('No se encontró QR en la respuesta', [
                    'result_keys' => array_keys((array)$result),
                    'has_values' => isset($result->values),
                    'values_keys' => isset($result->values) ? array_keys((array)$result->values) : null
                ]);
            }

            // Guardar número de transacción
            if (isset($result->values->transactionId)) {
                $transactionId = $result->values->transactionId;
                $pago->nro_transaccion = $transactionId;
            } elseif (isset($result->transactionId)) {
                $transactionId = $result->transactionId;
                $pago->nro_transaccion = $transactionId;
            }

            // Guardar paymentMethodId
            $paymentMethodId = $this->getPaymentMethodId();
            $pago->payment_method_id = $paymentMethodId;

            // Guardar fecha de expiración del QR si viene
            if (isset($result->values->expirationDate)) {
                try {
                    $expirationDate = \Carbon\Carbon::parse($result->values->expirationDate);
                    $pago->qr_expires_at = $expirationDate;
                } catch (\Exception $e) {
                    Log::warning('Error parseando expirationDate', ['date' => $result->values->expirationDate, 'error' => $e->getMessage()]);
                }
            }

            // Guardar información completa de la respuesta
            $pago->payment_info = json_encode($result);
            $pago->save();

            DB::commit();

            return [
                'pago' => $pago,
                'result' => $result,
                'qr_image' => $pago->qr_image,
                'qr_base64' => $qrImage,
                'message' => 'QR generado exitosamente'
            ];

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error en PaymentGatewayService (QR): ' . $th->getMessage());

            // Notificar a todos los administradores sobre el error
            $this->notificarErrorAdmin($th->getMessage(), $cuota, $estudiante);

            throw $th;
        }
    }

    /**
     * Confirmar pago desde callback
     */
    public function confirmPayment($nroPago)
    {
        $pago = Pago::where('nro_pago', $nroPago)
                    ->orWhere('nro_pago', (string) $nroPago)
                    ->with(['cuota.planPago.inscripcion.estudiante'])
                    ->first();

        if (!$pago) {
            Log::warning('Pago no encontrado en callback', ['nro_pago' => $nroPago]);
            throw new \Exception('Pago no encontrado con número: ' . $nroPago);
        }

        DB::beginTransaction();
        try {
            // Solo actualizar si aún no está confirmado
            if ($pago->estado_pagofacil !== 'completado' || !$pago->verificado) {
                $pago->estado_pagofacil = 'completado';
                $pago->verificado = true;
                $pago->fecha_verificacion = now();
                $pago->save();

                // Verificar si todas las cuotas del plan están pagadas
                $cuota = $pago->cuota;
                if ($cuota) {
                    $plan = $cuota->planPago;
                    if ($plan) {
                        // Verificar estado del plan
                        $this->verificarEstadoPlan($plan);
                    }
                }

                // Notificar a todos los administradores sobre el pago confirmado
                $this->notificarPagoConfirmadoAdmin($pago);
            }

            DB::commit();

            return $pago;
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Error confirmando pago: ' . $th->getMessage());
            throw $th;
        }
    }

    /**
     * Consultar estado de pago manualmente
     */
    public function consultPaymentStatus(Pago $pago)
    {
        try {
            $accessToken = $this->getAccessToken();

            $headers = [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken
            ];

            $body = [];

            if ($pago->nro_transaccion) {
                $body['pagofacilTransactionId'] = $pago->nro_transaccion;
            } else {
                $body['companyTransactionId'] = $pago->nro_pago;
            }

            Log::info('Consultando estado de pago', [
                'url' => $this->url_query,
                'body' => $body
            ]);

            $response = $this->client_guzzle->post($this->url_query, [
                'headers' => $headers,
                'json' => $body
            ]);

            $result = json_decode($response->getBody()->getContents());

            Log::info('Respuesta de consulta de estado', ['result' => $result]);

            $paymentInfo = null;

            if (isset($result->values)) {
                $values = $result->values;
                $paymentStatus = $values->paymentStatus ?? null;

                // Si el pago está completado (status = 1) y aún no está confirmado
                if ($paymentStatus == 1 && $pago->estado_pagofacil !== 'completado') {
                    Log::info('Pago confirmado desde consulta manual', [
                        'nro_pago' => $pago->nro_pago,
                        'paymentStatus' => $paymentStatus
                    ]);
                    $this->confirmPayment($pago->nro_pago);
                }

                // Actualizar información adicional
                if (isset($values->amount)) {
                    $pago->monto = $values->amount;
                }
                if (isset($values->paymentDate) && isset($values->paymentTime)) {
                    try {
                        $pago->fecha_verificacion = \Carbon\Carbon::parse(
                            $values->paymentDate . ' ' . $values->paymentTime
                        );
                    } catch (\Exception $e) {
                        Log::warning('Error parseando fecha de pago', [
                            'date' => $values->paymentDate,
                            'time' => $values->paymentTime
                        ]);
                    }
                }
                $pago->save();

                $paymentInfo = [
                    'paymentStatus' => $paymentStatus,
                    'paymentStatusDescription' => $values->paymentStatusDescription ?? null,
                    'amount' => $values->amount ?? null,
                    'currencyCode' => $values->currencyCode ?? 'BOB',
                    'paymentMethodId' => $values->paymentMethodId ?? null,
                    'paymentMethodDetail' => $values->paymentMethodDetail ?? null,
                    'pagofacilTransactionId' => $values->pagofacilTransactionId ?? null,
                    'companyTransactionId' => $values->companyTransactionId ?? null,
                    'requestDate' => $values->requestDate ?? null,
                    'requestTime' => $values->requestTime ?? null,
                    'paymentDate' => $values->paymentDate ?? null,
                    'paymentTime' => $values->paymentTime ?? null,
                    'payerName' => $values->payerName ?? null,
                    'payerDocument' => $values->payerDocument ?? null,
                    'payerAccount' => $values->payerAccount ?? null,
                    'payerBank' => $values->payerBank ?? null,
                ];
            }

            return [
                'result' => $result,
                'paymentInfo' => $paymentInfo
            ];
        } catch (\Throwable $th) {
            Log::error('Error consultando estado de pago: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString()
            ]);
            return [
                'result' => null,
                'paymentInfo' => null
            ];
        }
    }

    /**
     * Generar QR en la pasarela usando la nueva API v2
     */
    private function generateQR(Pago $pago, $detalles, Estudiante $estudiante)
    {
        $orderDetail = [];
        $serial = 1;
        foreach ($detalles as $detalle) {
            $orderDetail[] = [
                "serial" => $serial++,
                "product" => $detalle['producto'],
                "quantity" => $detalle['cantidad'],
                "price" => $detalle['precio'],
                "discount" => 0,
                "total" => $detalle['total']
            ];
        }

        $accessToken = $this->getAccessToken();
        $paymentMethodId = $this->getPaymentMethodId();

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ];

        // Cargar relación usuario si no está cargada
        if (!$estudiante->relationLoaded('usuario')) {
            $estudiante->load('usuario');
        }

        $body = [
            "paymentMethod" => $paymentMethodId,
            "clientName" => $estudiante->nombre . ' ' . $estudiante->apellido,
            "documentType" => 1, // 1 = CI
            "documentId" => $estudiante->ci,
            "phoneNumber" => $estudiante->celular ?? '',
            "email" => $estudiante->usuario->email ?? '',
            "paymentNumber" => (string) $pago->nro_pago,
            "amount" => (float) $pago->monto,
            "currency" => 2, // 2 = Bs (Bolivianos)
            "clientCode" => $this->clientCode,
            "callbackUrl" => $this->callbackUrl,
            "orderDetail" => $orderDetail
        ];

        Log::info('Generando QR con nueva API', [
            'url' => $this->url_qr,
            'paymentNumber' => $pago->nro_pago,
            'amount' => $pago->monto,
            'callbackUrl' => $this->callbackUrl
        ]);

        try {
            $response = $this->client_guzzle->post($this->url_qr, [
                'headers' => $headers,
                'json' => $body
            ]);

            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody);

            Log::info('Respuesta de API QR', [
                'status' => $response->getStatusCode(),
                'response' => $result
            ]);

            return $result;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Error en petición a API QR', [
                'message' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            throw new \Exception('Error al comunicarse con la pasarela de pagos: ' . $e->getMessage());
        }
    }

    /**
     * Preparar detalles del pago desde la cuota
     */
    private function preparePaymentDetails(Cuota $cuota, Estudiante $estudiante)
    {
        $detalles = [];

        // Cargar relaciones necesarias
        $cuota->load('planPago.inscripcion.programa');

        $programa = $cuota->planPago->inscripcion->programa ?? null;
        $programaNombre = $programa ? $programa->nombre : 'Programa Académico';

        $detalles[] = [
            'producto' => "Cuota - {$programaNombre}",
            'cantidad' => 1,
            'precio' => $cuota->monto,
            'total' => $cuota->monto
        ];

        return $detalles;
    }

    /**
     * Generar número de pago único
     */
    private function generateNroPago()
    {
        do {
            $nroPago = (string) rand(188888889, 999999999);
        } while (Pago::where('nro_pago', $nroPago)->exists());

        return $nroPago;
    }

    /**
     * Verificar estado del plan de pagos
     */
    private function verificarEstadoPlan($plan)
    {
        // Verificar si todas las cuotas están pagadas
        $cuotasPendientes = $plan->cuotas()->whereDoesntHave('pagos')->count();

        if ($cuotasPendientes === 0) {
            // Plan completado - puedes agregar lógica adicional aquí
            Log::info('Plan de pagos completado', ['plan_id' => $plan->id]);
        }
    }

    /**
     * Notificar a todos los administradores sobre errores en pagos
     */
    private function notificarErrorAdmin(string $errorMessage, Cuota $cuota, Estudiante $estudiante): void
    {
        try {
            $usuarios = Usuario::whereHas('rol', function ($query) {
                $query->where('nombre_rol', 'ADMIN');
            })->get();

            $titulo = 'Error en Pago QR - Requiere Atención';
            $mensaje = "Se produjo un error al procesar el pago QR para el estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}). Cuota ID: {$cuota->id}. Error: {$errorMessage}";

            foreach ($usuarios as $usuario) {
                Notificacion::crearNotificacion(
                    $usuario->usuario_id,
                    'admin',
                    $titulo,
                    $mensaje,
                    'error',
                    [
                        'cuota_id' => $cuota->id,
                        'estudiante_id' => $estudiante->id,
                        'estudiante_ci' => $estudiante->ci,
                        'error' => $errorMessage,
                        'tipo_error' => 'pago_qr'
                    ]
                );
            }

            Log::info('Notificaciones de error enviadas a administradores', [
                'cuota_id' => $cuota->id,
                'estudiante_id' => $estudiante->id,
                'admins_notificados' => $usuarios->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al notificar administradores sobre error de pago: ' . $e->getMessage());
        }
    }

    /**
     * Notificar a todos los administradores sobre pago confirmado
     */
    private function notificarPagoConfirmadoAdmin(Pago $pago): void
    {
        try {
            $pago->load(['cuota.planPago.inscripcion.estudiante', 'cuota.planPago.inscripcion.programa']);

            $cuota = $pago->cuota;
            if (!$cuota) {
                return;
            }

            $inscripcion = $cuota->planPago->inscripcion ?? null;
            if (!$inscripcion) {
                return;
            }

            $estudiante = $inscripcion->estudiante ?? null;
            $programa = $inscripcion->programa ?? null;

            if (!$estudiante) {
                return;
            }

            $usuarios = Usuario::whereHas('rol', function ($query) {
                $query->where('nombre_rol', 'ADMIN');
            })->get();

            $programaNombre = $programa ? $programa->nombre : 'Programa';
            $titulo = 'Pago QR Confirmado - ' . $estudiante->nombre . ' ' . $estudiante->apellido;
            $mensaje = "El estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) ha realizado un pago de " .
                      number_format($pago->monto, 2, '.', ',') . " BOB mediante QR para el programa '{$programaNombre}'. " .
                      "Número de pago: {$pago->nro_pago}. Cuota ID: {$cuota->id}.";

            foreach ($usuarios as $usuario) {
                Notificacion::crearNotificacion(
                    $usuario->usuario_id,
                    'admin',
                    $titulo,
                    $mensaje,
                    'pago',
                    [
                        'pago_id' => $pago->id,
                        'nro_pago' => $pago->nro_pago,
                        'cuota_id' => $cuota->id,
                        'estudiante_id' => $estudiante->id,
                        'estudiante_ci' => $estudiante->ci,
                        'monto' => $pago->monto,
                        'programa' => $programaNombre,
                        'metodo' => 'QR',
                        'fecha_verificacion' => $pago->fecha_verificacion
                    ]
                );
            }

            Log::info('Notificaciones de pago confirmado enviadas a administradores', [
                'pago_id' => $pago->id,
                'nro_pago' => $pago->nro_pago,
                'cuota_id' => $cuota->id,
                'estudiante_id' => $estudiante->id,
                'admins_notificados' => $usuarios->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error al notificar administradores sobre pago confirmado: ' . $e->getMessage());
        }
    }
}

