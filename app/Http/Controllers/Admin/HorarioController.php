<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HorarioController extends Controller
{
    /**
     * Listar horarios con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $dias = $request->get('dias', '');
            $turno = $request->get('turno', '');

            $query = Horario::withCount('grupos');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('dias', 'ILIKE', "%{$search}%");
                });
            }

            if ($dias) {
                $query->where('dias', 'ILIKE', "%{$dias}%");
            }

            if ($turno) {
                $query->porTurno($turno);
            }

            $horarios = $query->orderBy('dias', 'asc')
                             ->orderBy('hora_ini', 'asc')
                             ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $horarios,
                'message' => 'Horarios obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener horarios: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener horario por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $horario = Horario::with(['grupos.programa', 'grupos.modulo', 'grupos.docente'])
                             ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $horario,
                'message' => 'Horario obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Horario no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo horario
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'dias' => 'required|string|max:100',
            'hora_ini' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_ini'
        ], [
            'dias.required' => 'Los días son obligatorios',
            'hora_ini.required' => 'La hora de inicio es obligatoria',
            'hora_ini.date_format' => 'La hora de inicio debe tener el formato HH:mm',
            'hora_fin.required' => 'La hora de fin es obligatoria',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:mm',
            'hora_fin.after' => 'La hora de fin debe ser mayor a la hora de inicio'
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

            $horario = Horario::create($validator->validated());

            Cache::forget('horarios_all');
            Cache::forget('catalogos_horarios');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $horario,
                'message' => 'Horario creado exitosamente'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear horario: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar horario
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $horario = Horario::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'dias' => 'sometimes|required|string|max:100',
            'hora_ini' => 'sometimes|required|date_format:H:i',
            'hora_fin' => 'sometimes|required|date_format:H:i|after:hora_ini'
        ], [
            'dias.required' => 'Los días son obligatorios',
            'hora_ini.required' => 'La hora de inicio es obligatoria',
            'hora_ini.date_format' => 'La hora de inicio debe tener el formato HH:mm',
            'hora_fin.required' => 'La hora de fin es obligatoria',
            'hora_fin.date_format' => 'La hora de fin debe tener el formato HH:mm',
            'hora_fin.after' => 'La hora de fin debe ser mayor a la hora de inicio'
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

            $horario->update($validator->validated());

            Cache::forget('horarios_all');
            Cache::forget('catalogos_horarios');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $horario,
                'message' => 'Horario actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar horario: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar horario
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $horario = Horario::findOrFail($id);

            // Verificar si tiene grupos asociados
            if ($horario->grupos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el horario porque está asociado a grupos'
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            DB::beginTransaction();

            $horario->delete();

            Cache::forget('horarios_all');
            Cache::forget('catalogos_horarios');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Horario eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar horario: ' . (config('app.debug') ? $e->getMessage() : 'Error interno del servidor')
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}

