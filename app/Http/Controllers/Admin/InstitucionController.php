<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institucion;
use App\Models\Ciudad;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InstitucionController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar instituciones con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $ciudadId = $request->get('ciudad_id');
            $estado = $request->get('estado');

            $query = Institucion::with(['ciudad.provincia.pais']);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nombre', 'ILIKE', "%{$search}%")
                      ->orWhere('email', 'ILIKE', "%{$search}%")
                      ->orWhere('telefono', 'ILIKE', "%{$search}%");
                });
            }

            if ($ciudadId) {
                $query->where('ciudad_id', $ciudadId);
            }

            if ($estado !== null) {
                $query->where('estado', $estado);
            }

            $instituciones = $query->orderBy('nombre', 'asc')
                                  ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $instituciones,
                'message' => 'Instituciones obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener instituciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener institución por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $institucion = Institucion::with([
                'ciudad.provincia.pais',
                'programas'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $institucion,
                'message' => 'Institución obtenida exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Institución no encontrada'
            ], 404);
        }
    }

    /**
     * Crear nueva institución
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:200',
            'direccion' => 'nullable|string|max:300',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'sitio_web' => 'nullable|url|max:200',
            'fecha_fundacion' => 'nullable|date',
            'estado' => 'required|integer|in:0,1',
            'ciudad_id' => 'required|exists:ciudad,id'
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

            $institucion = Institucion::create($validator->validated());

            Cache::forget('instituciones_*');

            DB::commit();

            // Registrar en bitácora
            $this->registrarCreacion('institucion', $institucion->id, "Institución: {$institucion->nombre}");

            return response()->json([
                'success' => true,
                'data' => $institucion->load(['ciudad.provincia.pais']),
                'message' => 'Institución creada exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear institución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar institución
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $institucion = Institucion::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:200',
            'direccion' => 'nullable|string|max:300',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'sitio_web' => 'nullable|url|max:200',
            'fecha_fundacion' => 'nullable|date',
            'estado' => 'required|integer|in:0,1',
            'ciudad_id' => 'required|exists:ciudad,id'
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

            $institucion->update($validator->validated());

            Cache::forget('instituciones_*');

            DB::commit();

            // Registrar en bitácora
            $institucionActualizada = $institucion->fresh();
            $this->registrarEdicion('institucion', $institucionActualizada->id, "Institución: {$institucionActualizada->nombre}");

            return response()->json([
                'success' => true,
                'data' => $institucionActualizada->load(['ciudad.provincia.pais']),
                'message' => 'Institución actualizada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar institución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar institución (desactivar)
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $institucion = Institucion::findOrFail($id);

            // Verificar si tiene programas activos
            if ($institucion->programas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la institución porque tiene programas asociados'
                ], 422);
            }

            DB::beginTransaction();

            // Desactivar en lugar de eliminar
            $institucion->update(['estado' => 0]);

            Cache::forget('instituciones_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Institución desactivada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar institución: ' . $e->getMessage()
            ], 500);
        }
    }
}

