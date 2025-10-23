<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Estudiante;
use App\Models\Programa;
use App\Models\Docente;
use App\Models\Horario;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GrupoController extends Controller
{
    /**
     * Listar todos los grupos con información resumida
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $programaId = $request->input('programa_id', '');
            $docenteId = $request->input('docente_id', '');

            $grupos = Grupo::with([
                    'programa',
                    'docente',
                    'horario'
                ])
                ->withCount('estudiantes')
                ->when($search, function ($query) use ($search) {
                    $query->whereHas('programa', function ($q) use ($search) {
                        $q->where('nombre', 'ILIKE', "%{$search}%");
                    })
                    ->orWhereHas('docente', function ($q) use ($search) {
                        $q->where('nombre', 'ILIKE', "%{$search}%")
                          ->orWhere('apellido', 'ILIKE', "%{$search}%");
                    });
                })
                ->when($programaId, function ($query) use ($programaId) {
                    $query->where('Programa_id', $programaId);
                })
                ->when($docenteId, function ($query) use ($docenteId) {
                    $query->where('Docente_id', $docenteId);
                })
                ->orderBy('fecha_ini', 'desc')
                ->paginate($perPage);

            $grupos->getCollection()->transform(function ($grupo) {
                return [
                    'id' => $grupo->id,
                    'programa' => $grupo->programa->nombre,
                    'docente' => $grupo->docente ? $grupo->docente->nombre . ' ' . $grupo->docente->apellido : 'Sin asignar',
                    'fecha_ini' => $grupo->fecha_ini,
                    'fecha_fin' => $grupo->fecha_fin,
                    'horario' => $grupo->horario ? $grupo->horario->descripcion : 'Sin horario',
                    'total_estudiantes' => $grupo->estudiantes_count,
                    'esta_activo' => $grupo->esta_activo,
                    'duracion_dias' => $grupo->duracion_dias
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Grupos obtenidos exitosamente',
                'data' => $grupos
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de un grupo con estudiantes
     */
    public function show($grupoId)
    {
        try {
            $grupo = Grupo::with([
                    'programa.tipoPrograma',
                    'docente',
                    'horario',
                    'estudiantes' => function ($query) {
                        $query->orderBy('nombre')->orderBy('apellido');
                    }
                ])
                ->findOrFail($grupoId);

            $estudiantes = $grupo->estudiantes->map(function ($estudiante) {
                return [
                    'id' => $estudiante->id,
                    'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                    'ci' => $estudiante->ci,
                    'registro' => $estudiante->registro_estudiante,
                    'nota' => $estudiante->pivot->nota,
                    'estado' => $estudiante->pivot->estado,
                    'aprobado' => $estudiante->pivot->nota >= 51
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Detalle de grupo obtenido exitosamente',
                'data' => [
                    'grupo' => [
                        'id' => $grupo->id,
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'esta_activo' => $grupo->esta_activo
                    ],
                    'programa' => $grupo->programa,
                    'docente' => $grupo->docente,
                    'horario' => $grupo->horario,
                    'estudiantes' => $estudiantes,
                    'estadisticas' => [
                        'total_estudiantes' => $estudiantes->count(),
                        'con_notas' => $estudiantes->where('nota', '!=', null)->count(),
                        'aprobados' => $estudiantes->where('aprobado', true)->count(),
                        'reprobados' => $estudiantes->where('nota', '!=', null)->where('aprobado', false)->count()
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle del grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear nuevo grupo
     */
    public function store(Request $request)
    {
        $request->validate([
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'programa_id' => 'required|exists:Programa,id',
            'docente_id' => 'required|exists:Docente,id',
            'horario_id' => 'nullable|exists:Horario,id'
        ]);

        DB::beginTransaction();
        try {
            $admin = $request->auth_user;

            $grupo = Grupo::create([
                'fecha_ini' => $request->fecha_ini,
                'fecha_fin' => $request->fecha_fin,
                'Programa_id' => $request->programa_id,
                'Docente_id' => $request->docente_id,
                'horario_id' => $request->horario_id
            ]);

            $programa = Programa::find($request->programa_id);
            $docente = Docente::find($request->docente_id);

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Grupo',
                'codTable' => $grupo->id,
                'transaccion' => "Grupo creado para el programa '{$programa->nombre}' con docente {$docente->nombre} {$docente->apellido}. Fecha inicio: {$request->fecha_ini}, Fecha fin: {$request->fecha_fin}",
                'Usuario_id' => $admin->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grupo creado exitosamente',
                'data' => $grupo->load(['programa', 'docente', 'horario'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar grupo
     */
    public function update(Request $request, $grupoId)
    {
        $request->validate([
            'fecha_ini' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after:fecha_ini',
            'programa_id' => 'sometimes|exists:Programa,id',
            'docente_id' => 'sometimes|exists:Docente,id',
            'horario_id' => 'nullable|exists:Horario,id'
        ]);

        DB::beginTransaction();
        try {
            $admin = $request->auth_user;
            $grupo = Grupo::findOrFail($grupoId);

            $grupo->update($request->only([
                'fecha_ini',
                'fecha_fin',
                'Programa_id',
                'Docente_id',
                'horario_id'
            ]));

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Grupo',
                'codTable' => $grupo->id,
                'transaccion' => "Grupo ID {$grupo->id} actualizado",
                'Usuario_id' => $admin->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Grupo actualizado exitosamente',
                'data' => $grupo->load(['programa', 'docente', 'horario'])
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar estudiantes a un grupo
     */
    public function assignStudents(Request $request, $grupoId)
    {
        $request->validate([
            'estudiante_ids' => 'required|array',
            'estudiante_ids.*' => 'exists:Estudiante,id'
        ]);

        DB::beginTransaction();
        try {
            $admin = $request->auth_user;
            $grupo = Grupo::with('programa')->findOrFail($grupoId);

            // Validar que los estudiantes estén en estado 4 (aptos para inscripción)
            $estudiantesNoAptos = Estudiante::whereIn('id', $request->estudiante_ids)
                ->where('Estado_id', '<', 4)
                ->get();

            if ($estudiantesNoAptos->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Algunos estudiantes no están aptos para ser asignados (Estado_id debe ser >= 4)',
                    'estudiantes_no_aptos' => $estudiantesNoAptos->pluck('registro_estudiante')
                ], 400);
            }

            // Asignar estudiantes al grupo (sin duplicar)
            $nuevosEstudiantes = [];
            foreach ($request->estudiante_ids as $estudianteId) {
                // Verificar si ya está en el grupo
                if (!$grupo->estudiantes()->where('Estudiante_id', $estudianteId)->exists()) {
                    $grupo->estudiantes()->attach($estudianteId, [
                        'nota' => null,
                        'estado' => 'ACTIVO'
                    ]);
                    $nuevosEstudiantes[] = $estudianteId;
                }
            }

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'grupo_estudiante',
                'codTable' => $grupo->id,
                'transaccion' => count($nuevosEstudiantes) . " estudiantes asignados al grupo del programa '{$grupo->programa->nombre}'",
                'Usuario_id' => $admin->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($nuevosEstudiantes) . ' estudiantes asignados exitosamente',
                'data' => [
                    'grupo_id' => $grupo->id,
                    'estudiantes_asignados' => count($nuevosEstudiantes),
                    'total_estudiantes_grupo' => $grupo->estudiantes()->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar estudiantes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quitar estudiante de un grupo
     */
    public function removeStudent(Request $request, $grupoId, $estudianteId)
    {
        DB::beginTransaction();
        try {
            $admin = $request->auth_user;
            $grupo = Grupo::with('programa')->findOrFail($grupoId);
            $estudiante = Estudiante::findOrFail($estudianteId);

            // Verificar que el estudiante esté en el grupo
            if (!$grupo->estudiantes()->where('Estudiante_id', $estudianteId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no está en este grupo'
                ], 400);
            }

            $grupo->estudiantes()->detach($estudianteId);

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'grupo_estudiante',
                'codTable' => $grupo->id,
                'transaccion' => "Estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) removido del grupo del programa '{$grupo->programa->nombre}'",
                'Usuario_id' => $admin->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estudiante removido del grupo exitosamente'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al remover estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estudiantes disponibles para asignar a un grupo
     */
    public function getAvailableStudents(Request $request, $grupoId)
    {
        try {
            $grupo = Grupo::with('programa')->findOrFail($grupoId);

            // Estudiantes con Estado_id >= 4 que NO estén ya en este grupo
            $estudiantesDisponibles = Estudiante::where('Estado_id', '>=', 4)
                ->whereDoesntHave('grupos', function ($query) use ($grupoId) {
                    $query->where('Grupo_id', $grupoId);
                })
                ->orderBy('nombre')
                ->orderBy('apellido')
                ->get()
                ->map(function ($estudiante) {
                    return [
                        'id' => $estudiante->id,
                        'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'registro' => $estudiante->registro_estudiante,
                        'estado_id' => $estudiante->Estado_id
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Estudiantes disponibles obtenidos exitosamente',
                'data' => $estudiantesDisponibles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estudiantes disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
