<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ModuloController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar módulos con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $query = Modulo::withCount('programas');

            if ($search) {
                $query->where('nombre', 'ILIKE', "%{$search}%");
            }

            $modulos = $query->orderBy('nombre', 'asc')
                            ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $modulos,
                'message' => 'Módulos obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulos: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener módulo por ID
     */
    public function obtener($id): JsonResponse
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de módulo inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Buscar por la clave primaria (modulo_id)
            $modulo = Modulo::with(['programas'])->find($id);

            if (!$modulo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Módulo no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            return response()->json([
                'success' => true,
                'data' => $modulo,
                'message' => 'Módulo obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener módulo: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo módulo
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:200',
            'credito' => 'required|integer|min:1|max:20',
            'horas_academicas' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            $modulo = Modulo::create($validator->validated());

            Cache::forget('modulos_all');
            Cache::forget('catalogos_modulos');

            DB::commit();

            // Registrar en bitácora
            $this->registrarCreacion('modulo', $modulo->modulo_id, "Módulo: {$modulo->nombre}");

            return response()->json([
                'success' => true,
                'data' => $modulo,
                'message' => 'Módulo creado exitosamente'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear módulo: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar módulo
     */
    public function actualizar(Request $request, $id): JsonResponse
    {
        $id = (int) $id;

        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ID de módulo inválido'
            ], 400)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $modulo = Modulo::find($id);

        if (!$modulo) {
            return response()->json([
                'success' => false,
                'message' => 'Módulo no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:200',
            'credito' => 'required|integer|min:1|max:20',
            'horas_academicas' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            $modulo->update($validator->validated());

            Cache::forget('modulos_all');
            Cache::forget('catalogos_modulos');

            DB::commit();

            // Registrar en bitácora
            $moduloActualizado = $modulo->fresh();
            $this->registrarEdicion('modulo', $moduloActualizado->modulo_id, "Módulo: {$moduloActualizado->nombre}");

            return response()->json([
                'success' => true,
                'data' => $moduloActualizado,
                'message' => 'Módulo actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar módulo: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar módulo
     */
    public function eliminar($id): JsonResponse
    {
        try {
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de módulo inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $modulo = Modulo::find($id);

            if (!$modulo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Módulo no encontrado'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Verificar si tiene programas asociados
            if ($modulo->programas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el módulo porque está asociado a programas'
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            $modulo->delete();

            Cache::forget('modulos_all');
            Cache::forget('catalogos_modulos');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Módulo eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar módulo: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}

