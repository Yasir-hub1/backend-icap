<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Version;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VersionController extends Controller
{
    /**
     * Listar versiones con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $año = $request->get('año');

            $query = Version::withCount('programas');

            if ($search) {
                $query->where('nombre', 'ILIKE', "%{$search}%");
            }

            if ($año) {
                $query->where('año', $año);
            }

            $versiones = $query->orderBy('año', 'desc')
                              ->orderBy('nombre', 'asc')
                              ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $versiones,
                'message' => 'Versiones obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener versiones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener versión por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $version = Version::with(['programas'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $version,
                'message' => 'Versión obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Versión no encontrada'
            ], 404);
        }
    }

    /**
     * Crear nueva versión
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'año' => 'required|integer|min:2000|max:' . (date('Y') + 10)
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

            $version = Version::create($validator->validated());

            Cache::forget('versiones_all');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $version,
                'message' => 'Versión creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear versión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar versión
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $version = Version::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'año' => 'required|integer|min:2000|max:' . (date('Y') + 10)
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

            $version->update($validator->validated());

            Cache::forget('versiones_all');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $version,
                'message' => 'Versión actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar versión: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar versión
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $version = Version::findOrFail($id);

            // Verificar si tiene programas
            if ($version->programas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la versión porque tiene programas asociados'
                ], 422);
            }

            DB::beginTransaction();

            $version->delete();

            Cache::forget('versiones_all');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Versión eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar versión: ' . $e->getMessage()
            ], 500);
        }
    }
}

