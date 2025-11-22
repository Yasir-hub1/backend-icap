<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TipoDocumento;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TipoDocumentoController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar tipos de documento con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $query = TipoDocumento::withCount('documentos');

            if ($search) {
                $query->where('nombre_entidad', 'ILIKE', "%{$search}%");
            }

            $tipos = $query->orderBy('nombre_entidad', 'asc')
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $tipos,
                'message' => 'Tipos de documento obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipo de documento por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $tipo = TipoDocumento::with(['documentos'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de documento obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo de documento no encontrado'
            ], 404);
        }
    }

    /**
     * Crear nuevo tipo de documento
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_entidad' => 'required|string|max:100|unique:tipo_documento,nombre_entidad'
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

            $tipo = TipoDocumento::create($validator->validated());

            Cache::forget('tipos_documento_all');
            Cache::forget('catalogos_tipos_documento');

            DB::commit();

            // Registrar en bitácora
            $this->registrarCreacion('tipo_documento', $tipo->tipo_documento_id, "Tipo: {$tipo->nombre_entidad}");

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de documento creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear tipo de documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar tipo de documento
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $tipo = TipoDocumento::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre_entidad' => 'required|string|max:100|unique:tipo_documento,nombre_entidad,' . $id . ',tipo_documento_id'
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

            Cache::forget('tipos_documento_all');
            Cache::forget('catalogos_tipos_documento');

            DB::commit();

            // Registrar en bitácora
            $this->registrarEdicion('tipo_documento', $tipo->tipo_documento_id, "Tipo: {$tipo->nombre_entidad}");

            return response()->json([
                'success' => true,
                'data' => $tipo,
                'message' => 'Tipo de documento actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar tipo de documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar tipo de documento
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $tipo = TipoDocumento::findOrFail($id);

            // Verificar si tiene documentos
            if ($tipo->documentos()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el tipo de documento porque tiene documentos asociados'
                ], 422);
            }

            DB::beginTransaction();

            // Guardar datos para bitácora antes de eliminar
            $nombreTipo = $tipo->nombre_entidad;
            $tipoId = $tipo->tipo_documento_id;

            $tipo->delete();

            Cache::forget('tipos_documento_all');
            Cache::forget('catalogos_tipos_documento');

            DB::commit();

            // Registrar en bitácora
            $this->registrarEliminacion('tipo_documento', $tipoId, "Tipo: {$nombreTipo}");

            return response()->json([
                'success' => true,
                'message' => 'Tipo de documento eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar tipo de documento: ' . $e->getMessage()
            ], 500);
        }
    }
}

