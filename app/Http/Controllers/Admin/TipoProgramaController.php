<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoPrograma;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TipoProgramaController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar tipos de programa con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $sortBy = $request->get('sort_by', 'nombre');
            $sortDirection = $request->get('sort_direction', 'asc');

            // Validar dirección de ordenamiento
            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'asc';
            }

            // Validar columnas permitidas para ordenamiento
            $allowedSortColumns = ['nombre', 'id'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'nombre';
            }

            $query = TipoPrograma::withCount('programas');

            if ($search) {
                $query->where('nombre', 'ILIKE', "%{$search}%");
            }

            $tipos = $query->orderBy($sortBy, $sortDirection)
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $tipos->items(),
                    'current_page' => $tipos->currentPage(),
                    'per_page' => $tipos->perPage(),
                    'total' => $tipos->total(),
                    'last_page' => $tipos->lastPage(),
                    'from' => $tipos->firstItem(),
                    'to' => $tipos->lastItem()
                ],
                'message' => 'Tipos de programa obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipo de programa por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $tipo = TipoPrograma::with(['programas'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de programa obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de programa no encontrado'
            ], 404);
        }
    }

    /**
     * Crear nuevo tipo de programa
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100|unique:tipo_programa,nombre'
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

            $tipo = TipoPrograma::create($validator->validated());

            Cache::forget('tipos_programa_all');
            Cache::forget('catalogos_tipos_programa');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de programa creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear tipo de programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar tipo de programa
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $tipo = TipoPrograma::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100|unique:tipo_programa,nombre,' . $id . ',id'
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

            Cache::forget('tipos_programa_all');
            Cache::forget('catalogos_tipos_programa');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de programa actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar tipo de programa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar tipo de programa
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $tipo = TipoPrograma::findOrFail($id);

            // Verificar si tiene programas
            if ($tipo->programas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el tipo de programa porque tiene programas asociados'
                ], 422);
            }

            DB::beginTransaction();

            $tipo->delete();

            Cache::forget('tipos_programa_all');
            Cache::forget('catalogos_tipos_programa');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tipo de programa eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar tipo de programa: ' . $e->getMessage()
            ], 500);
        }
    }
}

