<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoPrograma;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TipoProgramaController extends Controller
{
    /**
     * Listar tipos de programa
     */
    public function index(): JsonResponse
    {
        $tipos = Cache::remember('tipos_programa_all', 3600, function() {
            return TipoPrograma::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos,
            'message' => 'Tipos de programa obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener tipo de programa específico
     */
    public function show(int $id): JsonResponse
    {
        $tipo = TipoPrograma::with(['programas.institucion'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de programa obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo tipo de programa
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100|unique:tipo_programa,nombre'
        ]);

        $tipo = TipoPrograma::create($request->validated());

        Cache::forget('tipos_programa_all');

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de programa creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar tipo de programa
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoPrograma::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100|unique:tipo_programa,nombre,' . $id
        ]);

        $tipo->update($request->validated());

        Cache::forget('tipos_programa_all');

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de programa actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar tipo de programa
     */
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoPrograma::findOrFail($id);

        // Verificar si tiene programas
        if ($tipo->programas()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el tipo de programa porque tiene programas asociados'
            ], 422);
        }

        $tipo->delete();

        Cache::forget('tipos_programa_all');

        return response()->json([
            'success' => true,
            'message' => 'Tipo de programa eliminado exitosamente'
        ]);
    }
}
