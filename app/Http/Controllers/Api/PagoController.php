<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Http\Requests\PagoRequest;
use App\Services\PagoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PagoController extends Controller
{
    protected $pagoService;

    public function __construct(PagoService $pagoService)
    {
        $this->pagoService = $pagoService;
    }

    /**
     * Listar todos los pagos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $pagos = $this->pagoService->getAllPagos($request->all());

            return response()->json([
                'success' => true,
                'data' => $pagos,
                'message' => 'Pagos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un pago específico
     */
    public function show($id): JsonResponse
    {
        try {
            $pago = $this->pagoService->getPagoById($id);

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pago,
                'message' => 'Pago obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo pago
     */
    public function store(PagoRequest $request): JsonResponse
    {
        try {
            $pago = $this->pagoService->createPago($request->validated());

            return response()->json([
                'success' => true,
                'data' => $pago,
                'message' => 'Pago creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un pago
     */
    public function update(PagoRequest $request, $id): JsonResponse
    {
        try {
            $pago = $this->pagoService->updatePago($id, $request->validated());

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pago,
                'message' => 'Pago actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un pago
     */
    public function destroy($id): JsonResponse
    {
        try {
            $deleted = $this->pagoService->deletePago($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pago eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar un pago
     */
    public function verify($id): JsonResponse
    {
        try {
            $pago = $this->pagoService->verifyPago($id);

            if (!$pago) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pago no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $pago,
                'message' => 'Pago verificado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pagos por estudiante
     */
    public function getByEstudiante($estudianteId): JsonResponse
    {
        try {
            $pagos = $this->pagoService->getPagosByEstudiante($estudianteId);

            return response()->json([
                'success' => true,
                'data' => $pagos,
                'message' => 'Pagos del estudiante obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos del estudiante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pagos por inscripción
     */
    public function getByInscripcion($inscripcionId): JsonResponse
    {
        try {
            $pagos = $this->pagoService->getPagosByInscripcion($inscripcionId);

            return response()->json([
                'success' => true,
                'data' => $pagos,
                'message' => 'Pagos de la inscripción obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos de la inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pagos pendientes
     */
    public function getPending(): JsonResponse
    {
        try {
            $pagos = $this->pagoService->getPagosPendientes();

            return response()->json([
                'success' => true,
                'data' => $pagos,
                'message' => 'Pagos pendientes obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener pagos pendientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar reporte de pagos
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $reporte = $this->pagoService->generatePagosReport($request->all());

            return response()->json([
                'success' => true,
                'data' => $reporte,
                'message' => 'Reporte de pagos generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte de pagos: ' . $e->getMessage()
            ], 500);
        }
    }
}
