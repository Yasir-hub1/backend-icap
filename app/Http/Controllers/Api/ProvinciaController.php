<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provincia;
use App\Models\Pais;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProvinciaController extends Controller
{
    /**
     * Listar provincias
     */
    public function index(Request $request): JsonResponse
    {
        $query = Provincia::with(['pais:id,nombre_pais']);

        if ($request->filled('pais_id')) {
            $query->where('Pais_id', $request->get('pais_id'));
        }

        $provincias = $query->orderBy('nombre_provincia')->get();

        return response()->json([
            'success' => true,
            'data' => $provincias,
            'message' => 'Provincias obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener provincia específica
     */
    public function show(int $id): JsonResponse
    {
        $provincia = Provincia::with([
            'pais:id,nombre_pais',
            'ciudades:id,nombre_ciudad,codigo_postal'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $provincia,
            'message' => 'Provincia obtenida exitosamente'
        ]);
    }

    /**
     * Crear nueva provincia
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_provincia' => 'required|string|max:100',
            'codigo_provincia' => 'nullable|string|max:10',
            'Pais_id' => 'required|exists:pais,id'
        ]);

        $provincia = Provincia::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $provincia->load('pais'),
            'message' => 'Provincia creada exitosamente'
        ], 201);
    }

    /**
     * Actualizar provincia
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $provincia = Provincia::findOrFail($id);

        $request->validate([
            'nombre_provincia' => 'required|string|max:100',
            'codigo_provincia' => 'nullable|string|max:10',
            'Pais_id' => 'required|exists:pais,id'
        ]);

        $provincia->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $provincia->load('pais'),
            'message' => 'Provincia actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar provincia
     */
    public function destroy(int $id): JsonResponse
    {
        $provincia = Provincia::findOrFail($id);

        // Verificar si tiene ciudades
        if ($provincia->ciudades()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la provincia porque tiene ciudades asociadas'
            ], 422);
        }

        $provincia->delete();

        return response()->json([
            'success' => true,
            'message' => 'Provincia eliminada exitosamente'
        ]);
    }
}
