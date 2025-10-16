<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConvenio;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TipoConvenioController extends Controller
{
    /**
     * Listar tipos de convenio
     */
    public function index(): JsonResponse
    {
        $tipos = Cache::remember('tipos_convenio_all', 3600, function() {
            return TipoConvenio::select('id', 'nombre_tipo', 'descripcion')
                ->orderBy('nombre_tipo')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos,
            'message' => 'Tipos de convenio obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener tipo de convenio específico
     */
    public function show(int $id): JsonResponse
    {
        $tipo = TipoConvenio::with(['convenios.instituciones'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de convenio obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo tipo de convenio
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_tipo' => 'required|string|max:100|unique:Tipo_convenio,nombre_tipo',
            'descripcion' => 'nullable|string'
        ]);

        $tipo = TipoConvenio::create($request->validated());

        Cache::forget('tipos_convenio_all');

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de convenio creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar tipo de convenio
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoConvenio::findOrFail($id);

        $request->validate([
            'nombre_tipo' => 'required|string|max:100|unique:Tipo_convenio,nombre_tipo,' . $id,
            'descripcion' => 'nullable|string'
        ]);

        $tipo->update($request->validated());

        Cache::forget('tipos_convenio_all');

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de convenio actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar tipo de convenio
     */
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoConvenio::findOrFail($id);

        // Verificar si tiene convenios
        if ($tipo->convenios()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el tipo de convenio porque tiene convenios asociados'
            ], 422);
        }

        $tipo->delete();

        Cache::forget('tipos_convenio_all');

        return response()->json([
            'success' => true,
            'message' => 'Tipo de convenio eliminado exitosamente'
        ]);
    }
}
