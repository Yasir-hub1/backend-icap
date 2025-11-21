<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RamaAcademica;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RamaAcademicaController extends Controller
{
    /**
     * Listar ramas académicas con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $query = RamaAcademica::withCount('programas');

            if ($search) {
                $query->where('nombre', 'ILIKE', "%{$search}%");
            }

            $ramas = $query->orderBy('nombre', 'asc')
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $ramas,
                'message' => 'Ramas académicas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ramas académicas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener rama académica por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $rama = RamaAcademica::with(['programas'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $rama,
                'message' => 'Rama académica obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Rama académica no encontrada'
            ], 404);
        }
    }

    /**
     * Crear nueva rama académica
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:150|unique:rama_academica,nombre'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $rama = RamaAcademica::create($validator->validated());

            Cache::forget('ramas_academicas_all');
            Cache::forget('catalogos_ramas_academicas');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $rama,
                'message' => 'Rama académica creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear rama académica: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar rama académica
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $rama = RamaAcademica::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:150|unique:rama_academica,nombre,' . $id . ',id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $rama->update($validator->validated());

            Cache::forget('ramas_academicas_all');
            Cache::forget('catalogos_ramas_academicas');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $rama,
                'message' => 'Rama académica actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar rama académica: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar rama académica
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $rama = RamaAcademica::findOrFail($id);

            // Verificar si tiene programas
            if ($rama->programas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la rama académica porque tiene programas asociados'
                ], 422);
            }

            DB::beginTransaction();

            $rama->delete();

            Cache::forget('ramas_academicas_all');
            Cache::forget('catalogos_ramas_academicas');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Rama académica eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar rama académica: ' . $e->getMessage()
            ], 500);
        }
    }
}

