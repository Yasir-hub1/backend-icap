<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provincia;
use App\Models\Pais;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProvinciaController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar provincias con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $paisId = $request->get('pais_id');

            $query = Provincia::with('pais:id,nombre_pais');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nombre_provincia', 'ILIKE', "%{$search}%")
                      ->orWhere('codigo_provincia', 'ILIKE', "%{$search}%");
                });
            }

            if ($paisId) {
                $query->where('pais_id', $paisId);
            }

            $provincias = $query->orderBy('nombre_provincia', 'asc')
                               ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $provincias,
                'message' => 'Provincias obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener provincias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener provincia por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $provincia = Provincia::with(['pais', 'ciudades'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $provincia,
                'message' => 'Provincia obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Provincia no encontrada'
            ], 404);
        }
    }

    /**
     * Crear nueva provincia
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_provincia' => 'required|string|max:100',
            'codigo_provincia' => 'nullable|string|max:10',
            'pais_id' => 'required|exists:pais,id'
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

            $provincia = Provincia::create($validator->validated());

            Cache::forget('catalogos_provincias_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $provincia->load('pais'),
                'message' => 'Provincia creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear provincia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar provincia
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $provincia = Provincia::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre_provincia' => 'required|string|max:100',
            'codigo_provincia' => 'nullable|string|max:10',
            'pais_id' => 'required|exists:pais,id'
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

            $provincia->update($validator->validated());

            Cache::forget('catalogos_provincias_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $provincia->load('pais'),
                'message' => 'Provincia actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar provincia: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar provincia
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $provincia = Provincia::findOrFail($id);

            // Verificar si tiene ciudades
            if ($provincia->ciudades()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la provincia porque tiene ciudades asociadas'
                ], 422);
            }

            DB::beginTransaction();

            $provincia->delete();

            Cache::forget('catalogos_provincias_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Provincia eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar provincia: ' . $e->getMessage()
            ], 500);
        }
    }
}

