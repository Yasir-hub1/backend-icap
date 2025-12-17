<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pais;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaisController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar países con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $sortBy = $request->get('sort_by', 'nombre_pais');
            $sortDirection = $request->get('sort_direction', 'asc');

            // Validar dirección de ordenamiento
            $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) 
                ? strtolower($sortDirection) 
                : 'asc';

            // Validar columna de ordenamiento
            $allowedSortColumns = ['nombre_pais', 'codigo_iso', 'codigo_telefono', 'created_at'];
            $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'nombre_pais';

            $query = Pais::query();

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('nombre_pais', 'ILIKE', "%{$search}%")
                      ->orWhere('codigo_iso', 'ILIKE', "%{$search}%")
                      ->orWhere('codigo_telefono', 'ILIKE', "%{$search}%");
                });
            }

            // Contar total antes de paginar
            $total = $query->count();

            $paises = $query->orderBy($sortBy, $sortDirection)
                           ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $paises->items(),
                    'current_page' => $paises->currentPage(),
                    'last_page' => $paises->lastPage(),
                    'per_page' => $paises->perPage(),
                    'total' => $paises->total(),
                    'from' => $paises->firstItem(),
                    'to' => $paises->lastItem(),
                ],
                'meta' => [
                    'total_registros' => $total,
                    'registros_pagina_actual' => $paises->count(),
                    'tiene_mas_paginas' => $paises->hasMorePages(),
                ],
                'message' => 'Países obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            Log::error('Error en PaisController::listar', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener países. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener país por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $pais = Pais::with('provincias.ciudades')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $pais,
                'message' => 'País obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'País no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo país
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre_pais' => 'required|string|max:100|unique:pais,nombre_pais',
            'codigo_iso' => 'nullable|string|max:3|unique:pais,codigo_iso',
            'codigo_telefono' => 'nullable|string|max:10'
        ], [
            'nombre_pais.required' => 'El nombre del país es obligatorio',
            'nombre_pais.string' => 'El nombre del país debe ser texto',
            'nombre_pais.max' => 'El nombre del país no puede tener más de 100 caracteres',
            'nombre_pais.unique' => 'Este nombre de país ya está registrado',
            'codigo_iso.string' => 'El código ISO debe ser texto',
            'codigo_iso.max' => 'El código ISO no puede tener más de 3 caracteres',
            'codigo_iso.unique' => 'Este código ISO ya está registrado',
            'codigo_telefono.string' => 'El código telefónico debe ser texto',
            'codigo_telefono.max' => 'El código telefónico no puede tener más de 10 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación. Por favor, revisa los campos marcados',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            $pais = Pais::create($validator->validated());

            Cache::forget('catalogos_paises');
            Cache::forget('paises_all');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pais,
                'message' => 'País creado exitosamente'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en PaisController::crear', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear país. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar país
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        try {
            $pais = Pais::findOrFail($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'País no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $validator = Validator::make($request->all(), [
            'nombre_pais' => 'required|string|max:100|unique:pais,nombre_pais,' . $id,
            'codigo_iso' => 'nullable|string|max:3|unique:pais,codigo_iso,' . $id,
            'codigo_telefono' => 'nullable|string|max:10'
        ], [
            'nombre_pais.required' => 'El nombre del país es obligatorio',
            'nombre_pais.string' => 'El nombre del país debe ser texto',
            'nombre_pais.max' => 'El nombre del país no puede tener más de 100 caracteres',
            'nombre_pais.unique' => 'Este nombre de país ya está registrado',
            'codigo_iso.string' => 'El código ISO debe ser texto',
            'codigo_iso.max' => 'El código ISO no puede tener más de 3 caracteres',
            'codigo_iso.unique' => 'Este código ISO ya está registrado',
            'codigo_telefono.string' => 'El código telefónico debe ser texto',
            'codigo_telefono.max' => 'El código telefónico no puede tener más de 10 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación. Por favor, revisa los campos marcados',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            $pais->update($validator->validated());

            Cache::forget('catalogos_paises');
            Cache::forget('paises_all');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pais,
                'message' => 'País actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en PaisController::actualizar', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar país. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar país
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $pais = Pais::findOrFail($id);

            // Verificar si tiene provincias
            if ($pais->provincias()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el país porque tiene provincias asociadas'
                ], 422)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            $pais->delete();

            Cache::forget('catalogos_paises');
            Cache::forget('paises_all');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'País eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en PaisController::eliminar', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar país. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}

