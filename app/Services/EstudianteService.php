<?php

namespace App\Services;

use App\Models\Estudiante;
use App\Models\EstadoEstudiante;
use App\Models\Inscripcion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class EstudianteService
{
    /**
     * Crear nuevo estudiante con validaciones de negocio
     */
    public function crearEstudiante(array $datos): Estudiante
    {
        return DB::transaction(function() use ($datos) {
            // Validar que el CI no exista
            if (Estudiante::where('ci', $datos['ci'])->exists()) {
                throw new \Exception('El CI ya está registrado en el sistema');
            }

            // Generar registro de estudiante único
            $registroEstudiante = $this->generarRegistroEstudiante();

            // Crear estudiante
            $estudiante = Estudiante::create([
                ...$datos,
                'registro_estudiante' => $registroEstudiante
            ]);

            // Limpiar caché
            $this->limpiarCacheEstudiantes();

            return $estudiante->load('estado');
        });
    }

    /**
     * Actualizar estudiante con validaciones
     */
    public function actualizarEstudiante(Estudiante $estudiante, array $datos): Estudiante
    {
        return DB::transaction(function() use ($estudiante, $datos) {
            // Validar CI único si cambió
            if (isset($datos['ci']) && $datos['ci'] !== $estudiante->ci) {
                if (Estudiante::where('ci', $datos['ci'])->where('id', '!=', $estudiante->id)->exists()) {
                    throw new \Exception('El CI ya está registrado en el sistema');
                }
            }

            $estudiante->update($datos);

            // Limpiar caché
            $this->limpiarCacheEstudiantes();

            return $estudiante->load('estado');
        });
    }

    /**
     * Desactivar estudiante (soft delete lógico)
     */
    public function desactivarEstudiante(Estudiante $estudiante): bool
    {
        return DB::transaction(function() use ($estudiante) {
            // Verificar si tiene inscripciones activas
            if ($estudiante->inscripciones()->exists()) {
                throw new \Exception('No se puede desactivar el estudiante porque tiene inscripciones activas');
            }

            // Cambiar estado a inactivo
            $estadoInactivo = EstadoEstudiante::where('nombre_estado', 'Inactivo')->first();
            if (!$estadoInactivo) {
                throw new \Exception('No se encontró el estado inactivo');
            }

            $estudiante->update(['Estado_id' => $estadoInactivo->id]);

            // Limpiar caché
            $this->limpiarCacheEstudiantes();

            return true;
        });
    }

    /**
     * Obtener estadísticas de estudiantes
     */
    public function obtenerEstadisticas(): array
    {
        return Cache::remember('estadisticas_estudiantes', 600, function() {
            return [
                'total' => Estudiante::count(),
                'activos' => Estudiante::activos()->count(),
                'con_inscripciones' => Estudiante::conInscripciones()->count(),
                'por_estado' => EstadoEstudiante::withCount('estudiantes')->get(),
                'inscripciones_recientes' => Estudiante::whereHas('inscripciones', function($q) {
                    $q->where('fecha', '>=', now()->subDays(30));
                })->count(),
                'por_provincia' => Estudiante::selectRaw('provincia, COUNT(*) as total')
                    ->whereNotNull('provincia')
                    ->groupBy('provincia')
                    ->orderBy('total', 'desc')
                    ->get()
            ];
        });
    }

    /**
     * Buscar estudiantes para autocompletado
     */
    public function buscarEstudiantes(string $termino, int $limite = 10): \Illuminate\Database\Eloquent\Collection
    {
        return Estudiante::select('id', 'ci', 'nombre', 'apellido', 'registro_estudiante')
            ->where(function($q) use ($termino) {
                $q->where('ci', 'ILIKE', "%{$termino}%")
                  ->orWhere('nombre', 'ILIKE', "%{$termino}%")
                  ->orWhere('apellido', 'ILIKE', "%{$termino}%")
                  ->orWhere('registro_estudiante', 'ILIKE', "%{$termino}%");
            })
            ->limit($limite)
            ->get();
    }

    /**
     * Obtener historial completo del estudiante
     */
    public function obtenerHistorialCompleto(Estudiante $estudiante): array
    {
        return [
            'estudiante' => $estudiante->load('estado'),
            'inscripciones' => $estudiante->inscripciones()
                ->with([
                    'programa.tipoPrograma',
                    'programa.ramaAcademica',
                    'programa.institucion',
                    'descuento',
                    'planPagos.cuotas.pagos'
                ])
                ->latest()
                ->get(),
            'grupos' => $estudiante->grupos()
                ->with([
                    'programa',
                    'docente',
                    'horario'
                ])
                ->get(),
            'documentos' => $estudiante->documentos()
                ->with('tipoDocumento')
                ->get(),
            'estadisticas' => [
                'total_inscripciones' => $estudiante->inscripciones()->count(),
                'programas_completados' => $estudiante->grupos()
                    ->where('fecha_fin', '<', now())
                    ->count(),
                'monto_total_pagado' => $estudiante->inscripciones()
                    ->with('planPagos.cuotas.pagos')
                    ->get()
                    ->sum(function($inscripcion) {
                        return $inscripcion->planPagos?->cuotas
                            ->sum(function($cuota) {
                                return $cuota->pagos->sum('monto');
                            }) ?? 0;
                    })
            ]
        ];
    }

    /**
     * Generar registro de estudiante único
     */
    private function generarRegistroEstudiante(): string
    {
        $ultimoRegistro = Estudiante::max('registro_estudiante');
        $numeroSiguiente = $ultimoRegistro ? (int)substr($ultimoRegistro, -6) + 1 : 1;
        return 'EST' . str_pad($numeroSiguiente, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Limpiar caché de estudiantes
     */
    private function limpiarCacheEstudiantes(): void
    {
        Cache::forget('estadisticas_estudiantes');
        // Limpiar caché de listados (patrón de búsqueda)
        $keys = Cache::getRedis()->keys('*estudiantes*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
