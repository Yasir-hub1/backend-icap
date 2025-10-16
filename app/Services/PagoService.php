<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Cuota;
use App\Models\PlanPagos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PagoService
{
    /**
     * Registrar nuevo pago con validaciones de negocio
     */
    public function registrarPago(array $datos): Pago
    {
        return DB::transaction(function() use ($datos) {
            $cuota = Cuota::findOrFail($datos['cuotas_id']);

            // Verificar que la cuota no esté ya pagada completamente
            $montoPagado = $cuota->monto_pagado;
            $montoRestante = $cuota->monto - $montoPagado;

            if ($montoRestante <= 0) {
                throw new \Exception('Esta cuota ya está pagada completamente');
            }

            // Verificar que el monto no exceda el saldo pendiente
            if ($datos['monto'] > $montoRestante) {
                throw new \Exception('El monto excede el saldo pendiente de la cuota');
            }

            // Crear pago
            $pago = Pago::create([
                'cuotas_id' => $datos['cuotas_id'],
                'monto' => $datos['monto'],
                'token' => $datos['token'] ?? null,
                'fecha' => now()
            ]);

            // Limpiar caché
            $this->limpiarCachePagos();

            return $pago->load([
                'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
                'cuota.planPagos.inscripcion.programa:id,nombre'
            ]);
        });
    }

    /**
     * Actualizar pago con validaciones
     */
    public function actualizarPago(Pago $pago, array $datos): Pago
    {
        return DB::transaction(function() use ($pago, $datos) {
            // Verificar que el pago no sea muy antiguo (más de 24 horas)
            if ($pago->fecha->diffInHours(now()) > 24) {
                throw new \Exception('No se puede modificar un pago después de 24 horas');
            }

            $pago->update($datos);

            // Limpiar caché
            $this->limpiarCachePagos();

            return $pago->load([
                'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
                'cuota.planPagos.inscripcion.programa:id,nombre'
            ]);
        });
    }

    /**
     * Eliminar pago con validaciones
     */
    public function eliminarPago(Pago $pago): bool
    {
        return DB::transaction(function() use ($pago) {
            // Verificar que el pago no sea muy antiguo (más de 24 horas)
            if ($pago->fecha->diffInHours(now()) > 24) {
                throw new \Exception('No se puede eliminar un pago después de 24 horas');
            }

            $pago->delete();

            // Limpiar caché
            $this->limpiarCachePagos();

            return true;
        });
    }

    /**
     * Obtener cuotas pendientes con filtros
     */
    public function obtenerCuotasPendientes(array $filtros = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Cuota::with([
            'planPagos.inscripcion.estudiante:id,ci,nombre,apellido,registro_estudiante',
            'planPagos.inscripcion.programa:id,nombre',
            'pagos'
        ])->pendientes();

        // Aplicar filtros
        if (isset($filtros['buscar'])) {
            $buscar = $filtros['buscar'];
            $query->whereHas('planPagos.inscripcion.estudiante', function($q) use ($buscar) {
                $q->where('ci', 'ILIKE', "%{$buscar}%")
                  ->orWhere('nombre', 'ILIKE', "%{$buscar}%")
                  ->orWhere('apellido', 'ILIKE', "%{$buscar}%");
            });
        }

        if (isset($filtros['fecha_vencimiento_desde'])) {
            $query->where('fecha_fin', '>=', $filtros['fecha_vencimiento_desde']);
        }

        if (isset($filtros['fecha_vencimiento_hasta'])) {
            $query->where('fecha_fin', '<=', $filtros['fecha_vencimiento_hasta']);
        }

        if (isset($filtros['programa_id'])) {
            $query->whereHas('planPagos.inscripcion', function($q) use ($filtros) {
                $q->where('Programa_id', $filtros['programa_id']);
            });
        }

        $query->orderBy('fecha_fin');

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Obtener estadísticas de pagos
     */
    public function obtenerEstadisticas(): array
    {
        return Cache::remember('estadisticas_pagos', 600, function() {
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
                }, '=', 0)->count(),
                'por_programa' => \App\Models\Programa::withCount('inscripciones')
                    ->withSum('inscripciones', 'costo')
                    ->activos()
                    ->get()
            ];
        });
    }

    /**
     * Generar reporte de pagos por período
     */
    public function generarReportePagos(string $fechaInicio, string $fechaFin): array
    {
        $pagos = Pago::with([
            'cuota.planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
            'cuota.planPagos.inscripcion.programa:id,nombre',
            'cuota.planPagos.inscripcion.programa.institucion:id,nombre'
        ])
        ->whereBetween('fecha', [$fechaInicio, $fechaFin])
        ->orderBy('fecha')
        ->get();

        $resumen = [
            'total_pagos' => $pagos->count(),
            'monto_total' => $pagos->sum('monto'),
            'por_institucion' => $pagos->groupBy('cuota.planPagos.inscripcion.programa.institucion.nombre')
                ->map(function($grupo) {
                    return [
                        'cantidad' => $grupo->count(),
                        'monto' => $grupo->sum('monto')
                    ];
                }),
            'por_programa' => $pagos->groupBy('cuota.planPagos.inscripcion.programa.nombre')
                ->map(function($grupo) {
                    return [
                        'cantidad' => $grupo->count(),
                        'monto' => $grupo->sum('monto')
                    ];
                }),
            'por_dia' => $pagos->groupBy(function($pago) {
                return $pago->fecha->format('Y-m-d');
            })->map(function($grupo) {
                return [
                    'cantidad' => $grupo->count(),
                    'monto' => $grupo->sum('monto')
                ];
            })
        ];

        return [
            'resumen' => $resumen,
            'detalle' => $pagos
        ];
    }

    /**
     * Obtener alertas de pagos
     */
    public function obtenerAlertas(): array
    {
        return [
            'cuotas_vencidas' => Cuota::vencidas()
                ->with([
                    'planPagos.inscripcion.estudiante:id,ci,nombre,apellido',
                    'planPagos.inscripcion.programa:id,nombre'
                ])
                ->count(),
            'cuotas_por_vencer' => Cuota::where('fecha_fin', '<=', now()->addDays(7))
                ->where('fecha_fin', '>', now())
                ->whereDoesntHave('pagos')
                ->count(),
            'planes_atrasados' => PlanPagos::whereHas('cuotas', function($q) {
                $q->where('fecha_fin', '<', now())
                  ->whereDoesntHave('pagos');
            })->count()
        ];
    }

    /**
     * Limpiar caché de pagos
     */
    private function limpiarCachePagos(): void
    {
        Cache::forget('estadisticas_pagos');
        // Limpiar caché de listados
        $keys = Cache::getRedis()->keys('*pagos*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
