<?php

namespace App\Services;

use App\Models\Programa;
use App\Models\RamaAcademica;
use App\Models\TipoPrograma;
use App\Models\Institucion;
use App\Models\Modulo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProgramaService
{
    /**
     * Crear nuevo programa con validaciones de negocio
     */
    public function crearPrograma(array $datos): Programa
    {
        return DB::transaction(function() use ($datos) {
            // Validar que la institución esté activa
            $institucion = Institucion::findOrFail($datos['Institucion_id']);
            if ($institucion->estado != 1) {
                throw new \Exception('La institución no está activa');
            }

            // Validar que el tipo de programa exista
            if (!TipoPrograma::find($datos['Tipo_programa_id'])) {
                throw new \Exception('El tipo de programa no es válido');
            }

            // Validar rama académica si se proporciona
            if (isset($datos['Rama_academica_id']) && !RamaAcademica::find($datos['Rama_academica_id'])) {
                throw new \Exception('La rama académica no es válida');
            }

            // Crear programa
            $programa = Programa::create($datos);

            // Asociar módulos si se proporcionan
            if (isset($datos['modulos']) && is_array($datos['modulos'])) {
                $this->asociarModulos($programa, $datos['modulos']);
            }

            // Limpiar caché
            $this->limpiarCacheProgramas();

            return $programa->load(['tipoPrograma', 'ramaAcademica', 'institucion', 'version']);
        });
    }

    /**
     * Actualizar programa con validaciones
     */
    public function actualizarPrograma(Programa $programa, array $datos): Programa
    {
        return DB::transaction(function() use ($programa, $datos) {
            // Validar que la institución esté activa
            if (isset($datos['Institucion_id'])) {
                $institucion = Institucion::findOrFail($datos['Institucion_id']);
                if ($institucion->estado != 1) {
                    throw new \Exception('La institución no está activa');
                }
            }

            // Validar que el tipo de programa exista
            if (isset($datos['Tipo_programa_id']) && !TipoPrograma::find($datos['Tipo_programa_id'])) {
                throw new \Exception('El tipo de programa no es válido');
            }

            // Validar rama académica si se proporciona
            if (isset($datos['Rama_academica_id']) && $datos['Rama_academica_id'] && !RamaAcademica::find($datos['Rama_academica_id'])) {
                throw new \Exception('La rama académica no es válida');
            }

            // Actualizar programa
            $programa->update($datos);

            // Actualizar módulos si se proporcionan
            if (isset($datos['modulos']) && is_array($datos['modulos'])) {
                $this->asociarModulos($programa, $datos['modulos']);
            }

            // Limpiar caché
            $this->limpiarCacheProgramas();

            return $programa->load(['tipoPrograma', 'ramaAcademica', 'institucion', 'version']);
        });
    }

    /**
     * Eliminar programa con validaciones
     */
    public function eliminarPrograma(Programa $programa): bool
    {
        return DB::transaction(function() use ($programa) {
            // Verificar si tiene inscripciones
            if ($programa->inscripciones()->exists()) {
                throw new \Exception('No se puede eliminar el programa porque tiene inscripciones');
            }

            // Verificar si tiene grupos activos
            if ($programa->grupos()->where('fecha_fin', '>=', now())->exists()) {
                throw new \Exception('No se puede eliminar el programa porque tiene grupos activos');
            }

            // Desasociar módulos
            $programa->modulos()->detach();

            $programa->delete();

            // Limpiar caché
            $this->limpiarCacheProgramas();

            return true;
        });
    }

    /**
     * Asociar módulos al programa
     */
    public function asociarModulos(Programa $programa, array $modulos): void
    {
        $modulosData = [];
        foreach ($modulos as $moduloId) {
            if (Modulo::find($moduloId)) {
                $modulosData[$moduloId] = ['edicion' => date('Y')];
            }
        }

        if (!empty($modulosData)) {
            $programa->modulos()->sync($modulosData);
        }
    }

    /**
     * Obtener estadísticas de programas
     */
    public function obtenerEstadisticas(): array
    {
        return Cache::remember('estadisticas_programas', 600, function() {
            return [
                'total' => Programa::count(),
                'activos' => Programa::activos()->count(),
                'cursos' => Programa::where('duracion_meses', '<', 12)->count(),
                'programas' => Programa::where('duracion_meses', '>=', 12)->count(),
                'por_tipo' => TipoPrograma::withCount('programas')->get(),
                'por_rama' => RamaAcademica::withCount('programas')->get(),
                'por_institucion' => Institucion::withCount('programas')->activas()->get(),
                'costo_promedio' => Programa::avg('costo'),
                'duracion_promedio' => Programa::avg('duracion_meses'),
                'con_inscripciones' => Programa::whereHas('inscripciones')->count(),
                'con_grupos_activos' => Programa::whereHas('grupos', function($q) {
                    $q->where('fecha_fin', '>=', now());
                })->count()
            ];
        });
    }

    /**
     * Obtener datos para formularios
     */
    public function obtenerDatosFormulario(): array
    {
        return Cache::remember('programas_datos_formulario', 600, function() {
            return [
                'tipos_programa' => TipoPrograma::select('id', 'nombre')->orderBy('nombre')->get(),
                'ramas_academicas' => RamaAcademica::select('id', 'nombre')->orderBy('nombre')->get(),
                'instituciones' => Institucion::select('id', 'nombre')->activas()->orderBy('nombre')->get(),
                'versiones' => \App\Models\Version::select('id', 'nombre', 'anio')->recientes()->orderBy('anio', 'desc')->get(),
                'modulos' => Modulo::select('id', 'nombre', 'credito', 'horas_academicas')->orderBy('nombre')->get()
            ];
        });
    }

    /**
     * Buscar programas con filtros
     */
    public function buscarProgramas(array $filtros = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Programa::with([
            'tipoPrograma:id,nombre',
            'ramaAcademica:id,nombre',
            'institucion:id,nombre',
            'version:id,nombre,anio'
        ])->activos();

        // Aplicar filtros
        if (isset($filtros['buscar'])) {
            $query->where('nombre', 'ILIKE', "%{$filtros['buscar']}%");
        }

        if (isset($filtros['tipo_programa_id'])) {
            $query->where('Tipo_programa_id', $filtros['tipo_programa_id']);
        }

        if (isset($filtros['rama_academica_id'])) {
            $query->where('Rama_academica_id', $filtros['rama_academica_id']);
        }

        if (isset($filtros['institucion_id'])) {
            $query->where('Institucion_id', $filtros['institucion_id']);
        }

        if (isset($filtros['es_curso'])) {
            if ($filtros['es_curso']) {
                $query->where('duracion_meses', '<', 12);
            } else {
                $query->where('duracion_meses', '>=', 12);
            }
        }

        if (isset($filtros['costo_maximo'])) {
            $query->where('costo', '<=', $filtros['costo_maximo']);
        }

        $query->orderBy('nombre');

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Obtener programa con información completa
     */
    public function obtenerProgramaCompleto(int $id): Programa
    {
        return Programa::with([
            'tipoPrograma:id,nombre',
            'ramaAcademica:id,nombre',
            'institucion:id,nombre,ciudad_id',
            'institucion.ciudad:id,nombre_ciudad,Provincia_id',
            'institucion.ciudad.provincia:id,nombre_provincia,Pais_id',
            'institucion.ciudad.provincia.pais:id,nombre_pais',
            'version:id,nombre,anio',
            'modulos:id,nombre,credito,horas_academicas',
            'inscripciones' => function($query) {
                $query->with(['estudiante:id,ci,nombre,apellido'])->latest();
            },
            'grupos' => function($query) {
                $query->with([
                    'docente:id,nombre,apellido',
                    'horario:id,dias,hora_ini,hora_fin'
                ])->where('fecha_fin', '>=', now());
            }
        ])->findOrFail($id);
    }

    /**
     * Limpiar caché de programas
     */
    private function limpiarCacheProgramas(): void
    {
        Cache::forget('estadisticas_programas');
        Cache::forget('programas_datos_formulario');
        // Limpiar caché de listados
        $keys = Cache::getRedis()->keys('*programas*');
        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
