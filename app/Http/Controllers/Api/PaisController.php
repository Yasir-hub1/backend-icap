<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pais;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PaisController extends Controller
{
    /**
     * Listar países
     */
    public function index(): JsonResponse
    {
        $paises = Cache::remember('paises_all', 3600, function() {
            return Pais::select('id', 'nombre_pais', 'codigo_iso', 'codigo_telefono')
                ->orderBy('nombre_pais')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $paises,
            'message' => 'Países obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener país específico
     */
    public function show(int $id): JsonResponse
    {
        $pais = Pais::with(['provincias.ciudades'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $pais,
            'message' => 'País obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo país
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_pais' => 'required|string|max:100|unique:pais,nombre_pais',
            'codigo_iso' => 'nullable|string|max:3',
            'codigo_telefono' => 'nullable|string|max:10'
        ]);

        $pais = Pais::create($request->validated());

        Cache::forget('paises_all');

        return response()->json([
            'success' => true,
            'data' => $pais,
            'message' => 'País creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar país
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pais = Pais::findOrFail($id);

        $request->validate([
            'nombre_pais' => 'required|string|max:100|unique:pais,nombre_pais,' . $id,
            'codigo_iso' => 'nullable|string|max:3',
            'codigo_telefono' => 'nullable|string|max:10'
        ]);

        $pais->update($request->validated());

        Cache::forget('paises_all');

        return response()->json([
            'success' => true,
            'data' => $pais,
            'message' => 'País actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar país
     */
    public function destroy(int $id): JsonResponse
    {
        $pais = Pais::findOrFail($id);

        // Verificar si tiene provincias
        if ($pais->provincias()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el país porque tiene provincias asociadas'
            ], 422);
        }

        $pais->delete();

        Cache::forget('paises_all');

        return response()->json([
            'success' => true,
            'message' => 'País eliminado exitosamente'
        ]);
    }
}
