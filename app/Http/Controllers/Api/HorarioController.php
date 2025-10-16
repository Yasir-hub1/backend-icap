<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HorarioController extends Controller
{
    /**
     * Listar horarios
     */
    public function index(Request $request): JsonResponse
    {
        $query = Horario::query();

        if ($request->filled('dias')) {
            $query->porDias($request->get('dias'));
        }

        if ($request->filled('turno')) {
            $turno = $request->get('turno');
            switch ($turno) {
                case 'matutino':
                    $query->matutinos();
                    break;
                case 'vespertino':
                    $query->vespertinos();
                    break;
                case 'nocturno':
                    $query->nocturnos();
                    break;
            }
        }

        if ($request->filled('hora_inicio') && $request->filled('hora_fin')) {
            $query->enRangoHoras($request->get('hora_inicio'), $request->get('hora_fin'));
        }

        $horarios = $query->orderBy('hora_ini')->get();

        return response()->json([
            'success' => true,
            'data' => $horarios,
            'message' => 'Horarios obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener horario específico
     */
    public function show(int $id): JsonResponse
    {
        $horario = Horario::with(['grupos.programa', 'gruposAdicionales.programa'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $horario,
            'message' => 'Horario obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo horario
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'dias' => 'required|string|max:100',
            'hora_ini' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_ini'
        ]);

        $horario = Horario::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $horario,
            'message' => 'Horario creado exitosamente'
        ], 201);
    }

    /**
     * Actualizar horario
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $horario = Horario::findOrFail($id);

        $request->validate([
            'dias' => 'required|string|max:100',
            'hora_ini' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_ini'
        ]);

        $horario->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $horario,
            'message' => 'Horario actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar horario
     */
    public function destroy(int $id): JsonResponse
    {
        $horario = Horario::findOrFail($id);

        // Verificar si está en uso
        if ($horario->grupos()->exists() || $horario->gruposAdicionales()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el horario porque está en uso'
            ], 422);
        }

        $horario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Horario eliminado exitosamente'
        ]);
    }
}
