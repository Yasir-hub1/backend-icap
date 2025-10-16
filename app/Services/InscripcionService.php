<?php

namespace App\Services;

use App\Models\Inscripcion;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Descuento;
use App\Models\PlanPagos;
use App\Models\Cuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InscripcionService
{
    /**
     * Crear nueva inscripción con validaciones de negocio
     */
    public function crearInscripcion(array $datos): Inscripcion
    {
        return DB::transaction(function() use ($datos) {
            // Validar que el estudiante no esté ya inscrito en el programa
            $inscripcionExistente = Inscripcion::where('Estudiante_id', $datos['Estudiante_id'])
                ->where('Programa_id', $datos['Programa_id'])
                ->exists();

            if ($inscripcionExistente) {
                throw new \Exception('El estudiante ya está inscrito en este programa');
            }

            // Validar que el programa esté activo
            $programa = Programa::findOrFail($datos['Programa_id']);
            if (!$programa->institucion || $programa->institucion->estado != 1) {
                throw new \Exception('El programa no está disponible para inscripciones');
            }

            // Validar descuento si se proporciona
            if (isset($datos['Descuento_id'])) {
                $descuento = Descuento::findOrFail($datos['Descuento_id']);
                if ($descuento->descuento <= 0) {
                    throw new \Exception('El descuento no es válido');
                }
            }

            // Crear inscripción
            $inscripcion = Inscripcion::create([
                'Programa_id' => $datos['Programa_id'],
                'Estudiante_id' => $datos['Estudiante_id'],
                'Descuento_id' => $datos['Descuento_id'] ?? null,
                'fecha' => now()
            ]);

            // Crear plan de pagos si se proporciona
            if (isset($datos['plan_pagos'])) {
                $this->crearPlanPagos($inscripcion, $datos['plan_pagos']);
            }

            // Limpiar caché
            $this->limpiarCacheInscripciones();

            return $inscripcion->load([
                'estudiante:id,ci,nombre,apellido',
                'programa:id,nombre',
                'descuento:id,nombre,descuento',
                'planPagos.cuotas'
            ]);
        });
    }

    /**
     * Actualizar inscripción
     */
    public function actualizarInscripcion(Inscripcion $inscripcion, array $datos): Inscripcion
    {
        return DB::transaction(function() use ($inscripcion, $datos) {
            // Solo permitir actualizar el descuento
            if (isset($datos['Descuento_id'])) {
                if ($datos['Descuento_id']) {
                    $descuento = Descuento::findOrFail($datos['Descuento_id']);
                    if ($descuento->descuento <= 0) {
                        throw new \Exception('El descuento no es válido');
                    }
                }
                $inscripcion->update(['Descuento_id' => $datos['Descuento_id']]);
            }

            // Limpiar caché
            $this->limpiarCacheInscripciones();

            return $inscripcion->load([
                'estudiante:id,ci,nombre,apellido',
                'programa:id,nombre',
                'descuento:id,nombre,descuento'
            ]);
        });
    }

    /**
     * Eliminar inscripción con validaciones
     */
    public function eliminarInscripcion(Inscripcion $inscripcion): bool
    {
        return DB::transaction(function() use ($inscripcion) {
            // Verificar si tiene pagos realizados
            if ($inscripcion->pagos()->exists()) {
                throw new \Exception('No se puede eliminar la inscripción porque tiene pagos realizados');
            }

            // Verificar si está en grupos activos
            if ($inscripcion->estudiante->grupos()
                ->where('fecha_fin', '>=', now())
                ->whereHas('programa', function($q) use ($inscripcion) {
                    $q->where('id', $inscripcion->Programa_id);
                })
                ->exists()) {
                throw new \Exception('No se puede eliminar la inscripción porque el estudiante está en grupos activos');
            }

            // Eliminar plan de pagos y cuotas si existen
            if ($inscripcion->planPagos) {
                $inscripcion->planPagos->cuotas()->delete();
                $inscripcion->planPagos()->delete();
            }

            $inscripcion->delete();

            // Limpiar caché
            $this->limpiarCacheInscripciones();

            return true;
        });
    }

    /**
     * Crear plan de pagos para inscripción
     */
    public function crearPlanPagos(Inscripcion $inscripcion, array $datosPlan): PlanPagos
    {
        $montoTotal = collect($datosPlan['cuotas'])->sum('monto');
        $costoPrograma = $inscripcion->programa->costo;
        $descuento = $inscripcion->descuento ? $inscripcion->descuento->descuento : 0;
        $costoFinal = $costoPrograma - ($costoPrograma * $descuento / 100);

        // Validar que el monto total coincida con el costo final
        if (abs($montoTotal - $costoFinal) > 0.01) {
            throw new \Exception('El monto total del plan de pagos no coincide con el costo final del programa');
        }

        // Crear plan de pagos
        $planPagos = PlanPagos::create([
            'monto_total' => $montoTotal,
            'total_cuotas' => $datosPlan['total_cuotas'],
            'Inscripcion_id' => $inscripcion->id
        ]);

        // Crear cuotas
        foreach ($datosPlan['cuotas'] as $cuotaData) {
            Cuota::create([
                'fecha_ini' => $cuotaData['fecha_ini'],
                'fecha_fin' => $cuotaData['fecha_fin'],
                'monto' => $cuotaData['monto'],
                'plan_pagos_id' => $planPagos->id
            ]);
        }

        return $planPagos->load('cuotas');
    }

    /**
     * Obtener estadísticas de inscripciones
     */
    public function obtenerEstadisticas(): array
    {
        return Cache::remember('estadisticas_inscripciones', 600, function() {
            return [
                'total' => Inscripcion::count(),
                'recientes' => Inscripcion::recientes()->count(),
                'con_plan_pagos' => Inscripcion::whereHas('planPagos')->count(),
                'sin_plan_pagos' => Inscripcion::whereDoesntHave('planPagos')->count(),
                'por_programa' => Programa::withCount('inscripciones')->activos()->get(),
                'por_mes' => Inscripcion::selectRaw('DATE_TRUNC(\'month\', fecha) as mes, COUNT(*) as total')
                    ->where('fecha', '>=', now()->subYear())
                    ->groupBy('mes')
                    ->orderBy('mes')
                    ->get(),
                'ingresos_totales' => Inscripcion::with('descuento', 'programa')
                    ->get()
                    ->sum('costo_final'),
                'por_institucion' => \App\Models\Institucion::withCount('programas.inscripciones')
                    ->activas()
                    ->get()
            ];
        });
    }

    /**
     * Obtener datos para formularios
     */
    public function obtenerDatosFormulario(): array
    {
        return Cache::remember('inscripciones_datos_formulario', 600, function() {
            return [
                'programas' => Programa::select('id', 'nombre', 'costo')
                    ->activos()
                    ->orderBy('nombre')
                    ->get(),
                'descuentos' => Descuento::select('id', 'nombre', 'descuento')
                    ->activos()
                    ->orderBy('nombre')
                    ->get()
            ];
        });
    }

    /**
     * Limpiar caché de inscripciones
     */
    private function limpiarCacheInscripciones(): void
    {
        Cache::forget('estadisticas_inscripciones');
        Cache::forget('inscripciones_datos_formulario');
        // Limpiar caché de listados
        $keys = Cache::getRedis()->keys('*inscripciones*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
