<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotaController extends Controller
{
    /**
     * Obtener todas mis notas de todos los grupos (alias para listar)
     */
    public function listar(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Obtener todas mis notas de todos los grupos
     */
    public function index(Request $request)
    {
        try {
            $estudiante = $request->auth_user;

            $grupos = $estudiante->grupos()
                ->with(['programa', 'docente', 'horarios'])
                ->orderBy('fecha_ini', 'desc')
                ->get()
                ->map(function ($grupo) {
                    $nota = $grupo->pivot->nota;
                    $estado = $grupo->pivot->estado;

                    return [
                        'grupo_id' => $grupo->grupo_id,
                        'programa' => [
                            'id' => $grupo->programa->id,
                            'nombre' => $grupo->programa->nombre
                        ],
                        'docente' => $grupo->docente ? $grupo->docente->nombre . ' ' . $grupo->docente->apellido : 'Sin asignar',
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'nota' => $nota,
                        'estado' => $estado,
                        'aprobado' => $nota !== null ? ($nota >= 51) : null,
                        'tiene_nota' => $nota !== null,
                        'horarios' => $grupo->horarios
                    ];
                });

            $conNotas = $grupos->where('tiene_nota', true);

            return response()->json([
                'success' => true,
                'message' => 'Notas obtenidas exitosamente',
                'data' => [
                    'notas' => $grupos,
                    'estadisticas' => [
                        'total_grupos' => $grupos->count(),
                        'con_notas' => $conNotas->count(),
                        'sin_notas' => $grupos->where('tiene_nota', false)->count(),
                        'aprobados' => $grupos->where('aprobado', true)->count(),
                        'reprobados' => $grupos->where('aprobado', false)->count(),
                        'promedio_general' => $conNotas->count() > 0 ? round($conNotas->avg('nota'), 2) : 0
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener nota de un grupo específico (alias para obtener)
     */
    public function obtener(Request $request, $grupoId)
    {
        return $this->show($request, $grupoId);
    }

    /**
     * Obtener nota de un grupo específico
     */
    public function show(Request $request, $grupoId)
    {
        try {
            $estudiante = $request->auth_user;

            $grupo = $estudiante->grupos()
                ->with(['programa', 'docente', 'horarios'])
                ->where('grupo_id', $grupoId)
                ->firstOrFail();

            $nota = $grupo->pivot->nota;

            return response()->json([
                'success' => true,
                'message' => 'Detalle de nota obtenido exitosamente',
                'data' => [
                    'grupo' => [
                        'id' => $grupo->grupo_id,
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin
                    ],
                    'programa' => $grupo->programa,
                    'docente' => $grupo->docente,
                    'horarios' => $grupo->horarios,
                    'calificacion' => [
                        'nota' => $nota,
                        'estado' => $grupo->pivot->estado,
                        'aprobado' => $nota !== null ? ($nota >= 51) : null,
                        'tiene_nota' => $nota !== null
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle de nota',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
