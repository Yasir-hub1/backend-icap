<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ciudad;
use App\Models\Provincia;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CiudadController extends Controller
{
    /**
     * Listar ciudades
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ciudad::with(['provincia.pais']);

        if ($request->filled('provincia_id')) {
            $query->where('Provincia_id', $request->get('provincia_id'));
        }

        if ($request->filled('pais_id')) {
            $query->whereHas('provincia', function($q) use ($request) {
                $q->where('Pais_id', $request->get('pais_id'));
            });
        }

        $ciudades = $query->orderBy('nombre_ciudad')->get();

        return response()->json([
            'success' => true,
            'data' => $ciudades,
            'message' => 'Ciudades obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener ciudad específica
     */
    public function show(int $id): JsonResponse
    {
        $ciudad = Ciudad::with([
            'provincia:id,nombre_provincia,Pais_id',
            'provincia.pais:id,nombre_pais',
            'instituciones:id,nombre'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ciudad,
            'message' => 'Ciudad obtenida exitosamente'
        ]);
    }

    /**
     * Crear nueva ciudad
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_ciudad' => 'required|string|max:100',
            'codigo_postal' => 'nullable|string|max:20',
            'Provincia_id' => 'required|exists:Provincia,id'
        ]);

        $ciudad = Ciudad::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $ciudad->load(['provincia.pais']),
            'message' => 'Ciudad creada exitosamente'
        ], 201);
    }

    /**
     * Actualizar ciudad
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $ciudad = Ciudad::findOrFail($id);

        $request->validate([
            'nombre_ciudad' => 'required|string|max:100',
            'codigo_postal' => 'nullable|string|max:20',
            'Provincia_id' => 'required|exists:Provincia,id'
        ]);

        $ciudad->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $ciudad->load(['provincia.pais']),
            'message' => 'Ciudad actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar ciudad
     */
    public function destroy(int $id): JsonResponse
    {
        $ciudad = Ciudad::findOrFail($id);

        // Verificar si tiene instituciones
        if ($ciudad->instituciones()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la ciudad porque tiene instituciones asociadas'
            ], 422);
        }

        $ciudad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ciudad eliminada exitosamente'
        ]);
    }
}
