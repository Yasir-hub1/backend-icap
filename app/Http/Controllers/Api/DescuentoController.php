<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Descuento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DescuentoController extends Controller
{
    /**
     * Listar descuentos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Descuento::query();

        if ($request->filled('activos')) {
            $query->activos();
        }

        if ($request->filled('porcentaje_minimo')) {
            $query->porcentajeMinimo($request->get('porcentaje_minimo'));
        }

        $descuentos = $query->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $descuentos,
            'message' => 'Descuentos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener descuento específico
     */
    public function show(int $id): JsonResponse
    {
        $descuento = Descuento::with(['inscripciones.estudiante'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $descuento,
            'message' => 'Descuento obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo descuento
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descuento' => 'required|numeric|min:0|max:100'
        ]);

        $descuento = Descuento::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $descuento,
            'message' => 'Descuento creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar descuento
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $descuento = Descuento::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'descuento' => 'required|numeric|min:0|max:100'
        ]);

        $descuento->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $descuento,
            'message' => 'Descuento actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar descuento
     */
    public function destroy(int $id): JsonResponse
    {
        $descuento = Descuento::findOrFail($id);

        // Verificar si tiene inscripciones
        if ($descuento->inscripciones()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el descuento porque tiene inscripciones asociadas'
            ], 422);
        }

        $descuento->delete();

        return response()->json([
            'success' => true,
            'message' => 'Descuento eliminado exitosamente'
        ]);
    }
}
