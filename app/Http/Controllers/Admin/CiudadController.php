<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ciudad;
use App\Models\Provincia;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CiudadController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar ciudades con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $provinciaId = $request->get('provincia_id');

            $query = Ciudad::with(['provincia.pais']);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nombre_ciudad', 'ILIKE', "%{$search}%")
                      ->orWhere('codigo_postal', 'ILIKE', "%{$search}%");
                });
            }

            if ($provinciaId) {
                $query->where('provincia_id', $provinciaId);
            }

            $ciudades = $query->orderBy('nombre_ciudad', 'asc')
                             ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $ciudades,
                'message' => 'Ciudades obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ciudades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener ciudad por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $ciudad = Ciudad::with(['provincia.pais', 'instituciones'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $ciudad,
                'message' => 'Ciudad obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ciudad no encontrada'
            ], 404);
        }
    }

    /**
     * Crear nueva ciudad
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_ciudad' => 'required|string|max:100',
            'codigo_postal' => 'nullable|string|max:20',
            'provincia_id' => 'required|exists:provincia,id'
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

            $ciudad = Ciudad::create($validator->validated());

            Cache::forget('catalogos_ciudades_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $ciudad->load(['provincia.pais']),
                'message' => 'Ciudad creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear ciudad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar ciudad
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $ciudad = Ciudad::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre_ciudad' => 'required|string|max:100',
            'codigo_postal' => 'nullable|string|max:20',
            'provincia_id' => 'required|exists:provincia,id'
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

            $ciudad->update($validator->validated());

            Cache::forget('catalogos_ciudades_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $ciudad->load(['provincia.pais']),
                'message' => 'Ciudad actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar ciudad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar ciudad
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $ciudad = Ciudad::findOrFail($id);

            // Verificar si tiene instituciones
            if ($ciudad->instituciones()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la ciudad porque tiene instituciones asociadas'
                ], 422);
            }

            DB::beginTransaction();

            $ciudad->delete();

            Cache::forget('catalogos_ciudades_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ciudad eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar ciudad: ' . $e->getMessage()
            ], 500);
        }
    }
}

