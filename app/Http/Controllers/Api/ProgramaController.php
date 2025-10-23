<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Http\Requests\ProgramaRequest;
use App\Services\ProgramaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgramaController extends Controller
{
    protected $programaService;

    public function __construct(ProgramaService $programaService)
    {
        $this->programaService = $programaService;
    }

    /**
     * Listar todos los programas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $programas = $this->programaService->getAllProgramas($request->all());

            return response()->json([
                'success' => true,
                'data' => $programas,
                'message' => 'Programas obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un programa específico
     */
    public function show($id): JsonResponse
    {
        try {
            $programa = $this->programaService->getProgramaById($id);

            if (!$programa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programa no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $programa,
                'message' => 'Programa obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo programa
     */
    public function store(ProgramaRequest $request): JsonResponse
    {
        try {
            $programa = $this->programaService->createPrograma($request->validated());

            return response()->json([
                'success' => true,
                'data' => $programa,
                'message' => 'Programa creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un programa
     */
    public function update(ProgramaRequest $request, $id): JsonResponse
    {
        try {
            $programa = $this->programaService->updatePrograma($id, $request->validated());

            if (!$programa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programa no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $programa,
                'message' => 'Programa actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un programa
     */
    public function destroy($id): JsonResponse
    {
        try {
            $deleted = $this->programaService->deletePrograma($id);

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Programa no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Programa eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener programas por rama académica
     */
    public function getByRamaAcademica($ramaId): JsonResponse
    {
        try {
            $programas = $this->programaService->getProgramasByRamaAcademica($ramaId);

            return response()->json([
                'success' => true,
                'data' => $programas,
                'message' => 'Programas obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener programas activos
     */
    public function getActive(): JsonResponse
    {
        try {
            $programas = $this->programaService->getActiveProgramas();

            return response()->json([
                'success' => true,
                'data' => $programas,
                'message' => 'Programas activos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programas activos: ' . $e->getMessage()
            ], 500);
        }
    }
}
