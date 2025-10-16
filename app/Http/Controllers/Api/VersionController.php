<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Version;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class VersionController extends Controller
{
    /**
     * Listar versiones
     */
    public function index(Request $request): JsonResponse
    {
        $query = Version::query();

        if ($request->filled('anio')) {
            $query->where('anio', $request->get('anio'));
        }

        if ($request->filled('recientes')) {
            $query->recientes();
        }

        $versiones = $query->orderBy('anio', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $versiones,
            'message' => 'Versiones obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener versión específica
     */
    public function show(int $id): JsonResponse
    {
        $version = Version::with(['programas.institucion'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $version,
            'message' => 'Versión obtenida exitosamente'
        ]);
    }

    /**
     * Crear nueva versión
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'anio' => 'required|integer|min:2000|max:2100'
        ]);

        $version = Version::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $version,
            'message' => 'Versión creada exitosamente'
        ], 201);
    }

    /**
     * Actualizar versión
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $version = Version::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:100',
            'anio' => 'required|integer|min:2000|max:2100'
        ]);

        $version->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $version,
            'message' => 'Versión actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar versión
     */
    public function destroy(int $id): JsonResponse
    {
        $version = Version::findOrFail($id);

        // Verificar si tiene programas
        if ($version->programas()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la versión porque tiene programas asociados'
            ], 422);
        }

        $version->delete();

        return response()->json([
            'success' => true,
            'message' => 'Versión eliminada exitosamente'
        ]);
    }
}
