<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class TipoDocumentoController extends Controller
{
    /**
     * Listar tipos de documento
     */
    public function index(): JsonResponse
    {
        $tipos = Cache::remember('tipos_documento_all', 3600, function() {
            return TipoDocumento::select('id', 'nombre_entidad', 'descripcion')
                ->orderBy('nombre_entidad')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos,
            'message' => 'Tipos de documento obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener tipo de documento específico
     */
    public function show(int $id): JsonResponse
    {
        $tipo = TipoDocumento::with(['documentos.estudiante'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de documento obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo tipo de documento
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_entidad' => 'required|string|max:100',
            'descripcion' => 'nullable|string'
        ]);

        $tipo = TipoDocumento::create($request->validated());

        Cache::forget('tipos_documento_all');

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de documento creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar tipo de documento
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoDocumento::findOrFail($id);

        $request->validate([
            'nombre_entidad' => 'required|string|max:100',
            'descripcion' => 'nullable|string'
        ]);

        $tipo->update($request->validated());

        Cache::forget('tipos_documento_all');

        return response()->json([
            'success' => true,
            'data' => $tipo,
            'message' => 'Tipo de documento actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar tipo de documento
     */
    public function destroy(int $id): JsonResponse
    {
        $tipo = TipoDocumento::findOrFail($id);

        // Verificar si tiene documentos
        if ($tipo->documentos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el tipo de documento porque tiene documentos asociados'
            ], 422);
        }

        $tipo->delete();

        Cache::forget('tipos_documento_all');

        return response()->json([
            'success' => true,
            'message' => 'Tipo de documento eliminado exitosamente'
        ]);
    }
}
