<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanPagos;
use App\Models\Inscripcion;
use App\Models\Cuota;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PlanPagosController extends Controller
{
    /**
     * Listar planes de pago
     */
    public function index(Request $request): JsonResponse
    {
        $query = PlanPagos::with([
            'inscripcion.estudiante:id,ci,nombre,apellido',
            'inscripcion.programa:id,nombre',
            'cuotas'
        ]);

        if ($request->filled('inscripcion_id')) {
            $query->where('Inscripcion_id', $request->get('inscripcion_id'));
        }

        if ($request->filled('activos')) {
            $query->activos();
        }

        $planes = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $planes,
            'message' => 'Planes de pago obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener plan de pago específico
     */
    public function show(int $id): JsonResponse
    {
        $plan = PlanPagos::with([
            'inscripcion.estudiante:id,ci,nombre,apellido',
            'inscripcion.programa:id,nombre',
            'inscripcion.descuento:id,nombre,descuento',
            'cuotas.pagos'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $plan,
            'message' => 'Plan de pago obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo plan de pago
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'monto_total' => 'required|numeric|min:0.01',
            'total_cuotas' => 'required|integer|min:1',
            'Inscripcion_id' => 'required|exists:inscripcion,id',
            'cuotas' => 'required|array|min:1',
            'cuotas.*.fecha_ini' => 'required|date|after_or_equal:today',
            'cuotas.*.fecha_fin' => 'required|date|after:cuotas.*.fecha_ini',
            'cuotas.*.monto' => 'required|numeric|min:0.01'
        ]);

        DB::beginTransaction();
        try {
            // Verificar que la inscripción no tenga ya un plan de pagos
            $inscripcion = Inscripcion::findOrFail($request->get('Inscripcion_id'));
            if ($inscripcion->planPagos()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La inscripción ya tiene un plan de pagos'
                ], 422);
            }

            // Verificar que el monto total coincida con la suma de cuotas
            $montoCuotas = collect($request->get('cuotas'))->sum('monto');
            if (abs($montoCuotas - $request->get('monto_total')) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto total debe coincidir con la suma de las cuotas'
                ], 422);
            }

            // Crear plan de pagos
            $plan = PlanPagos::create([
                'monto_total' => $request->get('monto_total'),
                'total_cuotas' => $request->get('total_cuotas'),
                'Inscripcion_id' => $request->get('Inscripcion_id')
            ]);

            // Crear cuotas
            foreach ($request->get('cuotas') as $cuotaData) {
                Cuota::create([
                    'fecha_ini' => $cuotaData['fecha_ini'],
                    'fecha_fin' => $cuotaData['fecha_fin'],
                    'monto' => $cuotaData['monto'],
                    'plan_pago_id' => $plan->id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $plan->load(['inscripcion.estudiante', 'cuotas']),
                'message' => 'Plan de pago creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear plan de pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar plan de pago
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $plan = PlanPagos::findOrFail($id);

        $request->validate([
            'monto_total' => 'required|numeric|min:0.01',
            'total_cuotas' => 'required|integer|min:1'
        ]);

        // Verificar que no tenga pagos realizados
        if ($plan->cuotas()->whereHas('pagos')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede modificar un plan de pago que ya tiene pagos realizados'
            ], 422);
        }

        $plan->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $plan->load(['inscripcion.estudiante', 'cuotas']),
            'message' => 'Plan de pago actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar plan de pago
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = PlanPagos::findOrFail($id);

        // Verificar que no tenga pagos realizados
        if ($plan->cuotas()->whereHas('pagos')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un plan de pago que ya tiene pagos realizados'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Eliminar cuotas
            $plan->cuotas()->delete();

            // Eliminar plan
            $plan->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Plan de pago eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar plan de pago: ' . $e->getMessage()
            ], 500);
        }
    }
}
