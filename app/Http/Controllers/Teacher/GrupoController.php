<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\Request;

class GrupoController extends Controller
{
    /**
     * Mis grupos asignados como docente
     */
    public function index(Request $request)
    {
        try {
            $docente = $request->auth_user;

            $grupos = Grupo::where('Docente_id', $docente->id)
                ->with([
                    'programa.tipoPrograma',
                    'horario'
                ])
                ->withCount('estudiantes')
                ->orderBy('fecha_ini', 'desc')
                ->get()
                ->map(function ($grupo) {
                    return [
                        'id' => $grupo->id,
                        'programa' => [
                            'id' => $grupo->programa->id,
                            'nombre' => $grupo->programa->nombre,
                            'tipo' => $grupo->programa->tipoPrograma->nombre ?? ''
                        ],
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'horario' => $grupo->horario,
                        'total_estudiantes' => $grupo->estudiantes_count,
                        'esta_activo' => $grupo->esta_activo,
                        'duracion_dias' => $grupo->duracion_dias
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Grupos asignados obtenidos exitosamente',
                'data' => [
                    'grupos' => $grupos,
                    'total_grupos' => $grupos->count(),
                    'grupos_activos' => $grupos->where('esta_activo', true)->count(),
                    'total_estudiantes' => $grupos->sum('total_estudiantes')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos asignados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver estudiantes del grupo con sus notas actuales
     */
    public function show(Request $request, $grupoId)
    {
        try {
            $docente = $request->auth_user;

            $grupo = Grupo::where('Docente_id', $docente->id)
                ->with([
                    'programa',
                    'horario',
                    'estudiantes' => function ($query) {
                        $query->orderBy('nombre')->orderBy('apellido');
                    }
                ])
                ->findOrFail($grupoId);

            $estudiantes = $grupo->estudiantes->map(function ($estudiante) {
                $nota = $estudiante->pivot->nota;
                return [
                    'id' => $estudiante->id,
                    'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                    'ci' => $estudiante->ci,
                    'registro' => $estudiante->registro_estudiante,
                    'celular' => $estudiante->celular,
                    'nota' => $nota,
                    'estado' => $estudiante->pivot->estado,
                    'aprobado' => $nota !== null ? ($nota >= 51) : null,
                    'tiene_nota' => $nota !== null
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Detalle del grupo obtenido exitosamente',
                'data' => [
                    'grupo' => [
                        'id' => $grupo->id,
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'esta_activo' => $grupo->esta_activo
                    ],
                    'programa' => $grupo->programa,
                    'horario' => $grupo->horario,
                    'estudiantes' => $estudiantes,
                    'estadisticas' => [
                        'total_estudiantes' => $estudiantes->count(),
                        'con_notas' => $estudiantes->where('tiene_nota', true)->count(),
                        'sin_notas' => $estudiantes->where('tiene_nota', false)->count(),
                        'aprobados' => $estudiantes->where('aprobado', true)->count(),
                        'reprobados' => $estudiantes->where('aprobado', false)->count(),
                        'promedio' => $estudiantes->where('tiene_nota', true)->avg('nota')
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
}
