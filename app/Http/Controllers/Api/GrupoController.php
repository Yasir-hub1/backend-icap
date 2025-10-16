<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Estudiante;
use App\Models\Docente;
use App\Models\Programa;
use App\Models\Horario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class GrupoController extends Controller
{
    /**
     * Listar grupos con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Grupo::with([
            'programa:id,nombre,duracion_meses',
            'programa.tipoPrograma:id,nombre',
            'programa.institucion:id,nombre',
            'docente:id,nombre,apellido,registro_docente',
            'horario:id,dias,hora_ini,hora_fin',
            'estudiantes:id,ci,nombre,apellido'
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->whereHas('programa', function($q) use ($buscar) {
                $q->where('nombre', 'ILIKE', "%{$buscar}%");
            })->orWhereHas('docente', function($q) use ($buscar) {
                $q->where('nombre', 'ILIKE', "%{$buscar}%")
                  ->orWhere('apellido', 'ILIKE', "%{$buscar}%");
            });
        }

        if ($request->filled('programa_id')) {
            $query->where('Programa_id', $request->get('programa_id'));
        }

        if ($request->filled('docente_id')) {
            $query->where('Docente_id', $request->get('docente_id'));
        }

        if ($request->filled('estado')) {
            $estado = $request->get('estado');
            if ($estado === 'activos') {
                $query->activos();
            } elseif ($estado === 'finalizados') {
                $query->finalizados();
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha_ini', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_fin', '<=', $request->get('fecha_hasta'));
        }

        // Ordenamiento
        $query->orderBy('fecha_ini', 'desc');

        // Paginación con caché
        $cacheKey = 'grupos_' . md5(serialize($request->all()));

        $grupos = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $grupos,
            'message' => 'Grupos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener grupos activos
     */
    public function activos(Request $request): JsonResponse
    {
        $query = Grupo::with([
            'programa:id,nombre,duracion_meses',
            'programa.tipoPrograma:id,nombre',
            'programa.institucion:id,nombre',
            'docente:id,nombre,apellido',
            'horario:id,dias,hora_ini,hora_fin'
        ])->activos();

        if ($request->filled('programa_id')) {
            $query->where('Programa_id', $request->get('programa_id'));
        }

        $grupos = $query->orderBy('fecha_ini')->get();

        return response()->json([
            'success' => true,
            'data' => $grupos,
            'message' => 'Grupos activos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener grupo específico con todas sus relaciones
     */
    public function show(int $id): JsonResponse
    {
        $grupo = Grupo::with([
            'programa:id,nombre,duracion_meses,costo',
            'programa.tipoPrograma:id,nombre',
            'programa.ramaAcademica:id,nombre',
            'programa.institucion:id,nombre',
            'docente:id,ci,nombre,apellido,registro_docente,area_de_especializacion',
            'horario:id,dias,hora_ini,hora_fin',
            'estudiantes' => function($query) {
                $query->withPivot('nota', 'estado');
            },
            'horariosAdicionales:id,dias,hora_ini,hora_fin'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $grupo,
            'message' => 'Grupo obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo grupo
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'fecha_ini' => 'required|date|after_or_equal:today',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'Programa_id' => 'required|exists:Programa,id',
            'Docente_id' => 'required|exists:Docente,id',
            'horario_id' => 'required|exists:Horario,id',
            'horarios_adicionales' => 'nullable|array',
            'horarios_adicionales.*' => 'exists:Horario,id'
        ]);

        DB::beginTransaction();
        try {
            // Verificar que el docente no tenga conflicto de horarios
            $conflictoHorario = Grupo::where('Docente_id', $request->get('Docente_id'))
                ->where('fecha_fin', '>=', $request->get('fecha_ini'))
                ->where('fecha_ini', '<=', $request->get('fecha_fin'))
                ->whereHas('horario', function($q) use ($request) {
                    $q->where('id', $request->get('horario_id'));
                })
                ->exists();

            if ($conflictoHorario) {
                return response()->json([
                    'success' => false,
                    'message' => 'El docente tiene un conflicto de horarios en las fechas especificadas'
                ], 422);
            }

            // Crear grupo
            $grupo = Grupo::create($request->except('horarios_adicionales'));

            // Asociar horarios adicionales si se proporcionan
            if ($request->has('horarios_adicionales')) {
                $horariosData = [];
                foreach ($request->get('horarios_adicionales') as $horarioId) {
                    $horariosData[$horarioId] = ['aula' => 'Aula por asignar'];
                }
                $grupo->horariosAdicionales()->attach($horariosData);
            }

            // Limpiar caché
            Cache::forget('grupos_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $grupo->load(['programa', 'docente', 'horario']),
                'message' => 'Grupo creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar grupo
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $grupo = Grupo::findOrFail($id);

        $request->validate([
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'Programa_id' => 'required|exists:Programa,id',
            'Docente_id' => 'required|exists:Docente,id',
            'horario_id' => 'required|exists:Horario,id',
            'horarios_adicionales' => 'nullable|array',
            'horarios_adicionales.*' => 'exists:Horario,id'
        ]);

        DB::beginTransaction();
        try {
            $grupo->update($request->except('horarios_adicionales'));

            // Actualizar horarios adicionales
            if ($request->has('horarios_adicionales')) {
                $horariosData = [];
                foreach ($request->get('horarios_adicionales') as $horarioId) {
                    $horariosData[$horarioId] = ['aula' => 'Aula por asignar'];
                }
                $grupo->horariosAdicionales()->sync($horariosData);
            }

            // Limpiar caché
            Cache::forget('grupos_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $grupo->load(['programa', 'docente', 'horario']),
                'message' => 'Grupo actualizado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar grupo
     */
    public function destroy(int $id): JsonResponse
    {
        $grupo = Grupo::findOrFail($id);

        // Verificar si tiene estudiantes inscritos
        if ($grupo->estudiantes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el grupo porque tiene estudiantes inscritos'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Desasociar horarios adicionales
            $grupo->horariosAdicionales()->detach();

            $grupo->delete();

            // Limpiar caché
            Cache::forget('grupos_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar estudiante al grupo
     */
    public function agregarEstudiante(Request $request, int $id): JsonResponse
    {
        $grupo = Grupo::findOrFail($id);

        $request->validate([
            'estudiante_id' => 'required|exists:Estudiante,id'
        ]);

        $estudianteId = $request->get('estudiante_id');

        // Verificar que el estudiante no esté ya en el grupo
        if ($grupo->estudiantes()->where('Estudiante_id', $estudianteId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante ya está inscrito en este grupo'
            ], 422);
        }

        // Verificar que el estudiante esté inscrito en el programa
        $estudiante = Estudiante::findOrFail($estudianteId);
        if (!$estudiante->inscripciones()->where('Programa_id', $grupo->Programa_id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no está inscrito en este programa'
            ], 422);
        }

        $grupo->estudiantes()->attach($estudianteId, [
            'estado' => 'Activo',
            'nota' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estudiante agregado al grupo exitosamente'
        ]);
    }

    /**
     * Remover estudiante del grupo
     */
    public function removerEstudiante(int $id, int $estudianteId): JsonResponse
    {
        $grupo = Grupo::findOrFail($id);

        if (!$grupo->estudiantes()->where('Estudiante_id', $estudianteId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no está inscrito en este grupo'
            ], 422);
        }

        $grupo->estudiantes()->detach($estudianteId);

        return response()->json([
            'success' => true,
            'message' => 'Estudiante removido del grupo exitosamente'
        ]);
    }

    /**
     * Actualizar nota del estudiante
     */
    public function actualizarNota(Request $request, int $id, int $estudianteId): JsonResponse
    {
        $grupo = Grupo::findOrFail($id);

        $request->validate([
            'nota' => 'required|numeric|min:0|max:100'
        ]);

        if (!$grupo->estudiantes()->where('Estudiante_id', $estudianteId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'El estudiante no está inscrito en este grupo'
            ], 422);
        }

        $grupo->estudiantes()->updateExistingPivot($estudianteId, [
            'nota' => $request->get('nota')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Nota actualizada exitosamente'
        ]);
    }

    /**
     * Obtener datos para formularios
     */
    public function datosFormulario(): JsonResponse
    {
        $cacheKey = 'grupos_datos_formulario';

        $datos = Cache::remember($cacheKey, 600, function() {
            return [
                'programas' => Programa::select('id', 'nombre', 'duracion_meses')
                    ->activos()
                    ->orderBy('nombre')
                    ->get(),
                'docentes' => Docente::select('id', 'nombre', 'apellido', 'registro_docente', 'area_de_especializacion')
                    ->activos()
                    ->orderBy('apellido')
                    ->get(),
                'horarios' => Horario::select('id', 'dias', 'hora_ini', 'hora_fin')
                    ->orderBy('hora_ini')
                    ->get()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $datos,
            'message' => 'Datos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de grupos
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_grupos';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Grupo::count(),
                'activos' => Grupo::activos()->count(),
                'finalizados' => Grupo::finalizados()->count(),
                'por_programa' => Programa::withCount('grupos')->activos()->get(),
                'por_docente' => Docente::withCount('grupos')->activos()->get(),
                'estudiantes_promedio' => Grupo::withCount('estudiantes')->get()->avg('estudiantes_count'),
                'duracion_promedio' => Grupo::selectRaw('AVG(fecha_fin - fecha_ini) as duracion_promedio')->value('duracion_promedio')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
