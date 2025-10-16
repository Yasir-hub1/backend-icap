<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RamaAcademica;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RamaAcademicaController extends Controller
{
    /**
     * Listar ramas académicas
     */
    public function index(): JsonResponse
    {
        $ramas = Cache::remember('ramas_academicas_all', 3600, function() {
            return RamaAcademica::select('id', 'nombre')
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $ramas,
            'message' => 'Ramas académicas obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener rama académica específica
     */
    public function show(int $id): JsonResponse
    {
        $rama = RamaAcademica::with(['programas.tipoPrograma'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $rama,
            'message' => 'Rama académica obtenida exitosamente'
        ]);
    }

    /**
     * Crear nueva rama académica
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:150|unique:Rama_academica,nombre'
        ]);

        $rama = RamaAcademica::create($request->validated());

        Cache::forget('ramas_academicas_all');

        return response()->json([
            'success' => true,
            'data' => $rama,
            'message' => 'Rama académica creada exitosamente'
        ], 201);
    }

    /**
     * Actualizar rama académica
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $rama = RamaAcademica::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:150|unique:Rama_academica,nombre,' . $id
        ]);

        $rama->update($request->validated());

        Cache::forget('ramas_academicas_all');

        return response()->json([
            'success' => true,
            'data' => $rama,
            'message' => 'Rama académica actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar rama académica
     */
    public function destroy(int $id): JsonResponse
    {
        $rama = RamaAcademica::findOrFail($id);

        // Verificar si tiene programas
        if ($rama->programas()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la rama académica porque tiene programas asociados'
            ], 422);
        }

        $rama->delete();

        Cache::forget('ramas_academicas_all');

        return response()->json([
            'success' => true,
            'message' => 'Rama académica eliminada exitosamente'
        ]);
    }
}
