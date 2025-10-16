<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Cuota;
use App\Models\PlanPagos;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PagoController extends Controller
{
    /**
     * Listar pagos con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Pago::with([
            'cuota:id,fecha_ini,fecha_fin,monto,plan_pagos_id',
            'cuota.planPagos:id,Inscripcion_id,monto_total',
            'cuota.planPagos.inscripcion:id,Estudiante_id,Programa_id',
            'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
            'cuota.planPagos.inscripcion.programa:id,nombre'
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->whereHas('cuota.planPagos.inscripcion.estudiante', function($q) use ($buscar) {
                $q->where('ci', 'ILIKE', "%{$buscar}%")
                  ->orWhere('nombre', 'ILIKE', "%{$buscar}%")
                  ->orWhere('apellido', 'ILIKE', "%{$buscar}%");
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->get('fecha_hasta'));
        }

        if ($request->filled('monto_minimo')) {
            $query->where('monto', '>=', $request->get('monto_minimo'));
        }

        if ($request->filled('monto_maximo')) {
            $query->where('monto', '<=', $request->get('monto_maximo'));
        }

        if ($request->filled('programa_id')) {
            $query->whereHas('cuota.planPagos.inscripcion', function($q) use ($request) {
                $q->where('Programa_id', $request->get('programa_id'));
            });
        }

        // Ordenamiento
        $query->latest('fecha');

        // Paginación con caché
        $cacheKey = 'pagos_' . md5(serialize($request->all()));

        $pagos = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $pagos,
            'message' => 'Pagos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener pago específico
     */
    public function show(int $id): JsonResponse
    {
        $pago = Pago::with([
            'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido,registro_estudiante',
            'cuota.planPagos.inscripcion.programa:id,nombre,duracion_meses',
            'cuota.planPagos.inscripcion.programa.tipoPrograma:id,nombre',
            'cuota.planPagos.inscripcion.programa.institucion:id,nombre'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $pago,
            'message' => 'Pago obtenido exitosamente'
        ]);
    }

    /**
     * Registrar nuevo pago
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'cuotas_id' => 'required|exists:cuotas,id',
            'monto' => 'required|numeric|min:0.01',
            'token' => 'nullable|string|max:100'
        ]);

        DB::beginTransaction();
        try {
            $cuota = Cuota::findOrFail($request->get('cuotas_id'));

            // Verificar que la cuota no esté ya pagada completamente
            $montoPagado = $cuota->monto_pagado;
            $montoRestante = $cuota->monto - $montoPagado;

            if ($montoRestante <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta cuota ya está pagada completamente'
                ], 422);
            }

            // Verificar que el monto no exceda el saldo pendiente
            if ($request->get('monto') > $montoRestante) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto excede el saldo pendiente de la cuota'
                ], 422);
            }

            // Crear pago
            $pago = Pago::create([
                'cuotas_id' => $request->get('cuotas_id'),
                'monto' => $request->get('monto'),
                'token' => $request->get('token'),
                'fecha' => now()
            ]);

            // Limpiar caché
            Cache::forget('pagos_*');
            Cache::forget('estadisticas_pagos');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pago->load([
                    'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
                    'cuota.planPagos.inscripcion.programa:id,nombre'
                ]),
                'message' => 'Pago registrado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar pago
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $pago = Pago::findOrFail($id);

        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'token' => 'nullable|string|max:100'
        ]);

        $pago->update($request->validated());

        // Limpiar caché
        Cache::forget('pagos_*');
        Cache::forget('estadisticas_pagos');

        return response()->json([
            'success' => true,
            'data' => $pago->load([
                'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
                'cuota.planPagos.inscripcion.programa:id,nombre'
            ]),
            'message' => 'Pago actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar pago
     */
    public function destroy(int $id): JsonResponse
    {
        $pago = Pago::findOrFail($id);

        // Verificar si el pago es muy reciente (menos de 24 horas)
        if ($pago->fecha->diffInHours(now()) > 24) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un pago después de 24 horas'
            ], 422);
        }

        $pago->delete();

        // Limpiar caché
        Cache::forget('pagos_*');
        Cache::forget('estadisticas_pagos');

        return response()->json([
            'success' => true,
            'message' => 'Pago eliminado exitosamente'
        ]);
    }

    /**
     * Obtener cuotas pendientes de pago
     */
    public function cuotasPendientes(Request $request): JsonResponse
    {
        $query = Cuota::with([
            'planPagos.inscripcion.estudiante:id,ci,nombre,apellido,registro_estudiante',
            'planPagos.inscripcion.programa:id,nombre',
            'pagos'
        ])->pendientes();

        // Filtros
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->whereHas('planPagos.inscripcion.estudiante', function($q) use ($buscar) {
                $q->where('ci', 'ILIKE', "%{$buscar}%")
                  ->orWhere('nombre', 'ILIKE', "%{$buscar}%")
                  ->orWhere('apellido', 'ILIKE', "%{$buscar}%");
            });
        }

        if ($request->filled('fecha_vencimiento_desde')) {
            $query->where('fecha_fin', '>=', $request->get('fecha_vencimiento_desde'));
        }

        if ($request->filled('fecha_vencimiento_hasta')) {
            $query->where('fecha_fin', '<=', $request->get('fecha_vencimiento_hasta'));
        }

        $query->orderBy('fecha_fin');

        $cuotas = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $cuotas,
            'message' => 'Cuotas pendientes obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de pagos
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_pagos';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total_pagos' => Pago::count(),
                'monto_total' => Pago::sum('monto'),
                'pagos_recientes' => Pago::recientes()->count(),
                'monto_reciente' => Pago::recientes()->sum('monto'),
                'cuotas_pendientes' => Cuota::pendientes()->count(),
                'cuotas_vencidas' => Cuota::vencidas()->count(),
                'monto_pendiente' => Cuota::pendientes()->sum('monto'),
                'monto_vencido' => Cuota::vencidas()->sum('monto'),
                'pagos_por_mes' => Pago::selectRaw('DATE_TRUNC(\'month\', fecha) as mes, COUNT(*) as cantidad, SUM(monto) as total')
                    ->where('fecha', '>=', now()->subYear())
                    ->groupBy('mes')
                    ->orderBy('mes')
                    ->get(),
                'planes_completos' => PlanPagos::whereHas('cuotas', function($q) {
                    $q->whereDoesntHave('pagos');
                }, '=', 0)->count()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
