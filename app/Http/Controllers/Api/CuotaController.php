<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cuota;
use App\Models\PlanPagos;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CuotaController extends Controller
{
    /**
     * Listar cuotas
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cuota::with([
            'planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
            'planPagos.inscripcion.programa:id,nombre',
            'pagos'
        ]);

        if ($request->filled('plan_pagos_id')) {
            $query->where('plan_pagos_id', $request->get('plan_pagos_id'));
        }

        if ($request->filled('estado')) {
            $estado = $request->get('estado');
            switch ($estado) {
                case 'pendientes':
                    $query->pendientes();
                    break;
                case 'vencidas':
                    $query->vencidas();
                    break;
                case 'pagadas':
                    $query->pagadas();
                    break;
            }
        }

        if ($request->filled('fecha_vencimiento_desde')) {
            $query->where('fecha_fin', '>=', $request->get('fecha_vencimiento_desde'));
        }

        if ($request->filled('fecha_vencimiento_hasta')) {
            $query->where('fecha_fin', '<=', $request->get('fecha_vencimiento_hasta'));
        }

        $cuotas = $query->orderBy('fecha_fin')->get();

        return response()->json([
            'success' => true,
            'data' => $cuotas,
            'message' => 'Cuotas obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener cuota específica
     */
    public function show(int $id): JsonResponse
    {
        $cuota = Cuota::with([
            'planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
            'planPagos.inscripcion.programa:id,nombre',
            'pagos'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cuota,
            'message' => 'Cuota obtenida exitosamente'
        ]);
    }

    /**
     * Crear nueva cuota
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_ini',
            'monto' => 'required|numeric|min:0.01',
            'plan_pagos_id' => 'required|exists:plan_pagos,id'
        ]);

        $cuota = Cuota::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $cuota->load(['planPagos.inscripcion.estudiante']),
            'message' => 'Cuota creada exitosamente'
        ], 201);
    }

    /**
     * Actualizar cuota
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $cuota = Cuota::findOrFail($id);

        $request->validate([
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_ini',
            'monto' => 'required|numeric|min:0.01'
        ]);

        // Verificar que no tenga pagos realizados
        if ($cuota->pagos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar una cuota que ya tiene pagos realizados'
            ], 422);
        }

        $cuota->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $cuota->load(['planPagos.inscripcion.estudiante']),
            'message' => 'Cuota actualizada exitosamente'
        ]);
    }

    /**
     * Eliminar cuota
     */
    public function destroy(int $id): JsonResponse
    {
        $cuota = Cuota::findOrFail($id);

        // Verificar que no tenga pagos realizados
        if ($cuota->pagos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una cuota que ya tiene pagos realizados'
            ], 422);
        }

        $cuota->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuota eliminada exitosamente'
        ]);
    }
}
