<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Estudiante;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class GrupoController extends Controller
{
    /**
     * Listar grupos asignados al docente autenticado
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $usuario = $request->auth_user;
            
            // El auth_user es un Usuario. El docente_id corresponde al persona_id del usuario
            // porque docente hereda de persona (docente.id == persona.id)
            if (!$usuario instanceof \App\Models\Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado correctamente'
                ], 401);
            }
            
            $docenteId = $usuario->persona_id;

            $grupos = Grupo::where('docente_id', $docenteId)
                ->with([
                    'programa',
                    'modulo',
                    'horarios'
                ])
                ->withCount('estudiantes')
                ->orderBy('fecha_ini', 'desc')
                ->get()
                ->map(function ($grupo) {
                    return [
                        'grupo_id' => $grupo->grupo_id,
                        'programa' => $grupo->programa ? [
                            'id' => $grupo->programa->id,
                            'nombre' => $grupo->programa->nombre
                        ] : null,
                        'modulo' => $grupo->modulo ? [
                            'modulo_id' => $grupo->modulo->modulo_id,
                            'nombre' => $grupo->modulo->nombre
                        ] : null,
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'esta_activo' => $grupo->esta_activo,
                        'numero_estudiantes' => $grupo->estudiantes_count,
                        'horarios' => $grupo->horarios->map(function ($horario) {
                            return [
                                'horario_id' => $horario->horario_id,
                                'dias' => $horario->dias,
                                'hora_ini' => $horario->hora_ini->format('H:i'),
                                'hora_fin' => $horario->hora_fin->format('H:i'),
                                'aula' => $horario->pivot->aula ?? 'N/A'
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $grupos,
                'message' => 'Grupos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener detalle de un grupo con sus estudiantes
     */
    public function obtener(int $grupoId): JsonResponse
    {
        try {
            $usuario = request()->auth_user;
            
            // El auth_user es un Usuario. El docente_id corresponde al persona_id del usuario
            if (!$usuario instanceof \App\Models\Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado correctamente'
                ], 401);
            }
            
            $docenteId = $usuario->persona_id;

            $grupo = Grupo::where('grupo_id', $grupoId)
                ->where('docente_id', $docenteId)
                ->with([
                    'programa',
                    'modulo',
                    'horarios',
                    'estudiantes' => function ($query) {
                        $query->orderBy('apellido')->orderBy('nombre');
                    }
                ])
                ->firstOrFail();

            $estudiantes = $grupo->estudiantes->map(function ($estudiante) use ($grupo) {
                $pivot = $estudiante->pivot;
                return [
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'ci' => $estudiante->ci,
                    'nota' => $pivot->nota,
                    'estado' => $pivot->estado,
                    'fecha_actualizacion' => $pivot->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'grupo' => [
                        'grupo_id' => $grupo->grupo_id,
                        'programa' => $grupo->programa ? [
                            'id' => $grupo->programa->id,
                            'nombre' => $grupo->programa->nombre
                        ] : null,
                        'modulo' => $grupo->modulo ? [
                            'modulo_id' => $grupo->modulo->modulo_id,
                            'nombre' => $grupo->modulo->nombre
                        ] : null,
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'esta_activo' => $grupo->esta_activo,
                        'horarios' => $grupo->horarios->map(function ($horario) {
                            return [
                                'horario_id' => $horario->horario_id,
                                'dias' => $horario->dias,
                                'hora_ini' => $horario->hora_ini->format('H:i'),
                                'hora_fin' => $horario->hora_fin->format('H:i'),
                                'aula' => $horario->pivot->aula ?? 'N/A'
                            ];
                        })
                    ],
                    'estudiantes' => $estudiantes
                ],
                'message' => 'Grupo obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupo: ' . $e->getMessage()
            ], 404);
        }
    }
}

