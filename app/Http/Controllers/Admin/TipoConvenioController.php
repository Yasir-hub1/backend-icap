<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoConvenio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TipoConvenioController extends Controller
{
    /**
     * Listar tipos de convenio con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $query = TipoConvenio::withCount('convenios');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nombre_tipo', 'ILIKE', "%{$search}%")
                      ->orWhere('descripcion', 'ILIKE', "%{$search}%");
                });
            }

            $tipos = $query->orderBy('nombre_tipo', 'asc')
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $tipos,
                'message' => 'Tipos de convenio obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de convenio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipo de convenio por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $tipo = TipoConvenio::with(['convenios'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de convenio obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de convenio no encontrado'
            ], 404);
        }
    }

    /**
     * Crear nuevo tipo de convenio
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_tipo' => 'required|string|max:100|unique:tipo_convenio,nombre_tipo',
            'descripcion' => 'nullable|string|max:500'
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

            $tipo = TipoConvenio::create($validator->validated());

            Cache::forget('tipos_convenio_all');
            Cache::forget('catalogos_tipos_convenio');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de convenio creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear tipo de convenio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar tipo de convenio
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $tipo = TipoConvenio::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre_tipo' => 'required|string|max:100|unique:tipo_convenio,nombre_tipo,' . $id . ',tipo_convenio_id',
            'descripcion' => 'nullable|string|max:500'
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

            $tipo->update($validator->validated());

            Cache::forget('tipos_convenio_all');
            Cache::forget('catalogos_tipos_convenio');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de convenio actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar tipo de convenio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar tipo de convenio
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $tipo = TipoConvenio::findOrFail($id);

            // Verificar si tiene convenios
            if ($tipo->convenios()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el tipo de convenio porque tiene convenios asociados'
                ], 422);
            }

            DB::beginTransaction();

            $tipo->delete();

            Cache::forget('tipos_convenio_all');
            Cache::forget('catalogos_tipos_convenio');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de convenio eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar tipo de convenio: ' . $e->getMessage()
            ], 500);
        }
    }
}

