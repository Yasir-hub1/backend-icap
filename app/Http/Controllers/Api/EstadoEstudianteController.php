<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EstadoEstudiante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EstadoEstudianteController extends Controller
{
    /**
     * Listar estados de estudiante
     */
    public function index(): JsonResponse
    {
        $estados = Cache::remember('estados_estudiante_all', 3600, function() {
            return EstadoEstudiante::select('id', 'nombre_estado')
                ->orderBy('nombre_estado')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $estados,
            'message' => 'Estados de estudiante obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener estado de estudiante específico
     */
    public function show(int $id): JsonResponse
    {
        $estado = EstadoEstudiante::with(['estudiantes:id,ci,nombre,apellido'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $estado,
            'message' => 'Estado de estudiante obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo estado de estudiante
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_estado' => 'required|string|max:50|unique:estado_estudiante,nombre_estado'
        ]);

        $estado = EstadoEstudiante::create($request->validated());

        Cache::forget('estados_estudiante_all');

        return response()->json([
            'success' => true,
            'data' => $estado,
            'message' => 'Estado de estudiante creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar estado de estudiante
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $estado = EstadoEstudiante::findOrFail($id);

        $request->validate([
            'nombre_estado' => 'required|string|max:50|unique:estado_estudiante,nombre_estado,' . $id
        ]);

        $estado->update($request->validated());

        Cache::forget('estados_estudiante_all');

        return response()->json([
            'success' => true,
            'data' => $estado,
            'message' => 'Estado de estudiante actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar estado de estudiante
     */
    public function destroy(int $id): JsonResponse
    {
        $estado = EstadoEstudiante::findOrFail($id);

        // Verificar si tiene estudiantes
        if ($estado->estudiantes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el estado porque tiene estudiantes asociados'
            ], 422);
        }

        $estado->delete();

        Cache::forget('estados_estudiante_all');

        return response()->json([
            'success' => true,
            'message' => 'Estado de estudiante eliminado exitosamente'
        ]);
    }
}
