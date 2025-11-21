<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Descuento;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DescuentoController extends Controller
{
    /**
     * Listar descuentos con paginación
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');

            $query = Descuento::with(['inscripcion.estudiante', 'inscripcion.programa']);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'ILIKE', "%{$search}%")
                      ->orWhereHas('inscripcion.estudiante', function ($q2) use ($search) {
                          $q2->where('nombre', 'ILIKE', "%{$search}%")
                             ->orWhere('apellido', 'ILIKE', "%{$search}%")
                             ->orWhere('ci', 'ILIKE', "%{$search}%");
                      });
                });
            }

            $descuentos = $query->orderBy('created_at', 'desc')
                              ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $descuentos,
                'message' => 'Descuentos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener descuentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener descuento por ID
     */
    public function obtener(int $id): JsonResponse
    {
        try {
            $descuento = Descuento::with(['inscripcion.estudiante', 'inscripcion.programa'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $descuento,
                'message' => 'Descuento obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Descuento no encontrado'
            ], 404);
        }
    }

    /**
     * Obtener inscripciones sin descuento para asignar
     */
    public function inscripcionesSinDescuento(): JsonResponse
    {
        try {
            $inscripciones = Inscripcion::whereDoesntHave('descuento')
                ->with(['estudiante', 'programa'])
                ->get()
                ->map(function ($inscripcion) {
                    return [
                        'id' => $inscripcion->id,
                        'estudiante' => [
                            'nombre' => $inscripcion->estudiante->nombre ?? '',
                            'apellido' => $inscripcion->estudiante->apellido ?? '',
                            'ci' => $inscripcion->estudiante->ci ?? '',
                            'registro' => $inscripcion->estudiante->registro_estudiante ?? ''
                        ],
                        'programa' => [
                            'id' => $inscripcion->programa->id ?? null,
                            'nombre' => $inscripcion->programa->nombre ?? ''
                        ],
                        'fecha' => $inscripcion->fecha
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $inscripciones
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo descuento
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'inscripcion_id' => 'required|exists:inscripcion,id|unique:descuento,inscripcion_id',
            'nombre' => 'required|string|max:100',
            'descuento' => 'required|numeric|min:0|max:100'
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

            $descuento = Descuento::create($validator->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $descuento->load(['inscripcion.estudiante', 'inscripcion.programa']),
                'message' => 'Descuento creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear descuento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar descuento
     */
    public function actualizar(Request $request, int $id): JsonResponse
    {
        $descuento = Descuento::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'descuento' => 'required|numeric|min:0|max:100'
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

            $descuento->update($validator->validated());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $descuento->load(['inscripcion.estudiante', 'inscripcion.programa']),
                'message' => 'Descuento actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar descuento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar descuento
     */
    public function eliminar(int $id): JsonResponse
    {
        try {
            $descuento = Descuento::findOrFail($id);

            DB::beginTransaction();

            $descuento->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Descuento eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar descuento: ' . $e->getMessage()
            ], 500);
        }
    }
}

