<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Http\Requests\InscripcionRequest;
use App\Services\InscripcionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InscripcionController extends Controller
{
    protected $inscripcionService;

    public function __construct(InscripcionService $inscripcionService)
    {
        $this->inscripcionService = $inscripcionService;
    }

    /**
     * Listar todas las inscripciones
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $inscripciones = $this->inscripcionService->getAllInscripciones($request->all());

            return response()->json([
                'success' => true,
                'data' => $inscripciones,
                'message' => 'Inscripciones obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar una inscripción específica
     */
    public function show($id): JsonResponse
    {
        try {
            $inscripcion = $this->inscripcionService->getInscripcionById($id);

            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $inscripcion,
                'message' => 'Inscripción obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva inscripción
     */
    public function store(InscripcionRequest $request): JsonResponse
    {
        try {
            $inscripcion = $this->inscripcionService->createInscripcion($request->validated());

            return response()->json([
                'success' => true,
                'data' => $inscripcion,
                'message' => 'Inscripción creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una inscripción
     */
    public function update(InscripcionRequest $request, $id): JsonResponse
    {
        try {
            $inscripcion = $this->inscripcionService->updateInscripcion($id, $request->validated());

            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $inscripcion,
                'message' => 'Inscripción actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una inscripción
     */
    public function destroy($id): JsonResponse
    {
        try {
            $deleted = $this->inscripcionService->deleteInscripcion($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Inscripción eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar una inscripción
     */
    public function approve($id): JsonResponse
    {
        try {
            $inscripcion = $this->inscripcionService->approveInscripcion($id);

            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $inscripcion,
                'message' => 'Inscripción aprobada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar una inscripción
     */
    public function reject($id): JsonResponse
    {
        try {
            $inscripcion = $this->inscripcionService->rejectInscripcion($id);

            if (!$inscripcion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Inscripción no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $inscripcion,
                'message' => 'Inscripción rechazada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar inscripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener inscripciones por estudiante
     */
    public function getByEstudiante($estudianteId): JsonResponse
    {
        try {
            $inscripciones = $this->inscripcionService->getInscripcionesByEstudiante($estudianteId);

            return response()->json([
                'success' => true,
                'data' => $inscripciones,
                'message' => 'Inscripciones del estudiante obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripciones del estudiante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener inscripciones por programa
     */
    public function getByPrograma($programaId): JsonResponse
    {
        try {
            $inscripciones = $this->inscripcionService->getInscripcionesByPrograma($programaId);

            return response()->json([
                'success' => true,
                'data' => $inscripciones,
                'message' => 'Inscripciones del programa obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripciones del programa: ' . $e->getMessage()
            ], 500);
        }
    }
}
