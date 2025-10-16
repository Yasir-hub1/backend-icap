<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ModuloController extends Controller
{
    /**
     * Listar módulos
     */
    public function index(Request $request): JsonResponse
    {
        $query = Modulo::query();

        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where('nombre', 'ILIKE', "%{$buscar}%");
        }

        if ($request->filled('con_creditos')) {
            $query->conCreditos();
        }

        if ($request->filled('horas_minimas')) {
            $query->porHoras($request->get('horas_minimas'));
        }

        $modulos = $query->orderBy('nombre')->get();

        return response()->json([
            'success' => true,
            'data' => $modulos,
            'message' => 'Módulos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener módulo específico
     */
    public function show(int $id): JsonResponse
    {
        $modulo = Modulo::with(['programas.tipoPrograma'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $modulo,
            'message' => 'Módulo obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo módulo
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre' => 'required|string|max:200',
            'credito' => 'nullable|integer|min:0',
            'horas_academicas' => 'nullable|integer|min:0'
        ]);

        $modulo = Modulo::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $modulo,
            'message' => 'Módulo creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar módulo
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $modulo = Modulo::findOrFail($id);

        $request->validate([
            'nombre' => 'required|string|max:200',
            'credito' => 'nullable|integer|min:0',
            'horas_academicas' => 'nullable|integer|min:0'
        ]);

        $modulo->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $modulo,
            'message' => 'Módulo actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar módulo
     */
    public function destroy(int $id): JsonResponse
    {
        $modulo = Modulo::findOrFail($id);

        // Verificar si está asociado a programas
        if ($modulo->programas()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el módulo porque está asociado a programas'
            ], 422);
        }

        $modulo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Módulo eliminado exitosamente'
        ]);
    }
}
