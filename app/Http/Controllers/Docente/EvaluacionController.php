<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Estudiante;
use App\Models\Bitacora;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EvaluacionController extends Controller
{
    /**
     * Registrar o actualizar nota de un estudiante en un grupo
     */
    public function registrarNota(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'required|exists:grupo,grupo_id',
            'estudiante_registro' => 'required|exists:estudiante,registro_estudiante',
            'nota' => 'required|numeric|min:0|max:100',
            'estado' => 'nullable|in:APROBADO,REPROBADO,RETIRADO,EN_CURSO'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $docente = $request->auth_user;
            $docenteId = $docente instanceof \App\Models\Docente
                ? $docente->id
                : $docente->id;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('grupo_id', $request->grupo_id)
                ->where('docente_id', $docenteId)
                ->firstOrFail();

            // Obtener estudiante por registro o ID
            $estudiante = Estudiante::where('registro_estudiante', $request->estudiante_registro)
                ->orWhere('id', $request->estudiante_registro)
                ->firstOrFail();

            $estudianteId = $estudiante->id;

            // Verificar que el estudiante esté en el grupo
            if (!$grupo->estudiantes()->where('estudiante.id', $estudianteId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no está inscrito en este grupo'
                ], 404);
            }

            // Determinar estado automáticamente si no se proporciona
            $estado = $request->estado;
            if (!$estado) {
                if ($request->nota >= 51) {
                    $estado = 'APROBADO';
                } else {
                    $estado = 'REPROBADO';
                }
            }

            // Actualizar o crear registro en grupo_estudiante usando estudiante_id
            $grupo->estudiantes()->updateExistingPivot($estudianteId, [
                'nota' => $request->nota,
                'estado' => $estado
            ]);

            // Registrar en bitácora
            $usuario = $docente->usuario ?? null;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Grupo_estudiante',
                    'codTabla' => "{$request->grupo_id}-{$request->estudiante_registro}",
                    'transaccion' => "Docente {$docente->nombre} {$docente->apellido} registró nota {$request->nota} y estado {$estado} para el estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) en el grupo {$grupo->grupo_id}",
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            // Notificar al estudiante sobre la nota registrada
            $this->notificarNotaRegistrada($estudiante, $grupo, $request->nota, $estado);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Nota registrada exitosamente',
                'data' => [
                    'nota' => $request->nota,
                    'estado' => $estado
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar nota: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estado académico de un estudiante
     */
    public function actualizarEstado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'required|exists:grupo,grupo_id',
            'estudiante_registro' => 'required|exists:estudiante,registro_estudiante',
            'estado' => 'required|in:APROBADO,REPROBADO,RETIRADO,EN_CURSO'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $docente = $request->auth_user;
            $docenteId = $docente instanceof \App\Models\Docente
                ? $docente->id
                : $docente->id;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('grupo_id', $request->grupo_id)
                ->where('docente_id', $docenteId)
                ->firstOrFail();

            // Obtener estudiante por registro o ID
            $estudiante = Estudiante::where('registro_estudiante', $request->estudiante_registro)
                ->orWhere('id', $request->estudiante_registro)
                ->firstOrFail();

            $estudianteId = $estudiante->id;

            // Verificar que el estudiante esté en el grupo
            $estudianteEnGrupo = $grupo->estudiantes()->where('estudiante.id', $estudianteId)->first();
            if (!$estudianteEnGrupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no está inscrito en este grupo'
                ], 404);
            }

            // Obtener nota actual si existe
            $notaActual = $estudianteEnGrupo->pivot->nota ?? null;

            // Actualizar estado en grupo_estudiante usando estudiante_id
            $grupo->estudiantes()->updateExistingPivot($estudianteId, [
                'estado' => $request->estado
            ]);

            // Registrar en bitácora
            $usuario = $docente->usuario ?? null;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Grupo_estudiante',
                    'codTabla' => "{$request->grupo_id}-{$request->estudiante_registro}",
                    'transaccion' => "Docente {$docente->nombre} {$docente->apellido} cambió el estado a {$request->estado} para el estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) en el grupo {$grupo->grupo_id}" . ($notaActual ? " (Nota: {$notaActual})" : ''),
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado exitosamente',
                'data' => [
                    'estado' => $request->estado
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar múltiples notas a la vez
     */
    public function registrarNotasMasivas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'required|exists:grupo,grupo_id',
            'notas' => 'required|array|min:1',
            'notas.*.estudiante_registro' => 'required|exists:estudiante,registro_estudiante',
            'notas.*.nota' => 'required|numeric|min:0|max:100',
            'notas.*.estado' => 'nullable|in:APROBADO,REPROBADO,RETIRADO,EN_CURSO'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $docente = $request->auth_user;
            $docenteId = $docente instanceof \App\Models\Docente
                ? $docente->id
                : $docente->id;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('grupo_id', $request->grupo_id)
                ->where('docente_id', $docenteId)
                ->firstOrFail();

            $registrosActualizados = 0;
            $errores = [];

            foreach ($request->notas as $notaData) {
                try {
                    // Obtener estudiante por registro
                    $estudiante = Estudiante::where('registro_estudiante', $notaData['estudiante_registro'])->first();
                    if (!$estudiante) {
                        $errores[] = "Estudiante con registro {$notaData['estudiante_registro']} no encontrado";
                        continue;
                    }

                    $estudianteId = $estudiante->id;

                    // Verificar que el estudiante esté en el grupo
                    if (!$grupo->estudiantes()->where('estudiante.id', $estudianteId)->exists()) {
                        $errores[] = "Estudiante {$notaData['estudiante_registro']} no está en el grupo";
                        continue;
                    }

                    // Determinar estado automáticamente si no se proporciona
                    $estado = $notaData['estado'] ?? null;
                    if (!$estado) {
                        if ($notaData['nota'] >= 51) {
                            $estado = 'APROBADO';
                        } else {
                            $estado = 'REPROBADO';
                        }
                    }

                    // Actualizar registro en grupo_estudiante usando estudiante_id
                    $grupo->estudiantes()->updateExistingPivot($estudianteId, [
                        'nota' => $notaData['nota'],
                        'estado' => $estado
                    ]);

                    $registrosActualizados++;
                } catch (\Exception $e) {
                    $errores[] = "Error con estudiante {$notaData['estudiante_registro']}: " . $e->getMessage();
                }
            }

            // Registrar en bitácora
            $usuario = $docente->usuario ?? null;
            if ($usuario && $registrosActualizados > 0) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Grupo_estudiante',
                    'codTabla' => $request->grupo_id,
                    'transaccion' => "Docente {$docente->nombre} {$docente->apellido} registró {$registrosActualizados} notas masivamente en el grupo {$grupo->grupo_id}",
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            // Notificar a los estudiantes sobre las notas registradas
            foreach ($request->notas as $notaData) {
                try {
                    $estudiante = Estudiante::where('registro_estudiante', $notaData['estudiante_registro'])->first();
                    if ($estudiante) {
                        $estadoFinal = $notaData['estado'] ?? ($notaData['nota'] >= 51 ? 'APROBADO' : 'REPROBADO');
                        $this->notificarNotaRegistrada($estudiante, $grupo, $notaData['nota'], $estadoFinal);
                    }
                } catch (\Exception $e) {
                    // Continuar con el siguiente estudiante si hay error en notificación
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Se registraron {$registrosActualizados} notas exitosamente",
                'data' => [
                    'registros_actualizados' => $registrosActualizados,
                    'errores' => $errores
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar notas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notificar a un estudiante sobre una nota registrada
     */
    private function notificarNotaRegistrada(Estudiante $estudiante, Grupo $grupo, $nota, $estado)
    {
        try {
            $programaNombre = $grupo->programa ? $grupo->programa->nombre : 'Programa';
            $moduloNombre = $grupo->modulo ? $grupo->modulo->nombre : 'Módulo';

            $titulo = 'Nota Registrada - ' . $programaNombre;
            $mensaje = "Se ha registrado tu nota de {$nota} puntos en el módulo '{$moduloNombre}' del programa '{$programaNombre}'. Estado: {$estado}.";

            Notificacion::crearNotificacion(
                $estudiante->registro_estudiante,
                'student',
                $titulo,
                $mensaje,
                $estado === 'APROBADO' ? 'success' : ($estado === 'REPROBADO' ? 'error' : 'academico'),
                [
                    'grupo_id' => $grupo->grupo_id,
                    'nota' => $nota,
                    'estado' => $estado,
                    'programa' => $programaNombre,
                    'modulo' => $moduloNombre
                ]
            );
        } catch (\Exception $e) {
            // Log error pero no fallar el proceso principal
            Log::error('Error al notificar estudiante sobre nota: ' . $e->getMessage());
        }
    }

    /**
     * Enviar notificación a estudiantes de un grupo
     */
    public function enviarNotificacionEstudiantes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grupo_id' => 'required|exists:grupo,grupo_id',
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'tipo' => 'nullable|in:info,success,warning,error,academico',
            'estudiante_registro' => 'nullable|exists:estudiante,registro_estudiante' // Opcional: enviar a un estudiante específico
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $docente = $request->auth_user;
            $docenteId = $docente instanceof \App\Models\Docente
                ? $docente->id
                : $docente->id;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('grupo_id', $request->grupo_id)
                ->where('docente_id', $docenteId)
                ->firstOrFail();

            $enviadas = 0;

            if ($request->estudiante_registro) {
                // Enviar a un estudiante específico
                $estudiante = Estudiante::where('registro_estudiante', $request->estudiante_registro)->firstOrFail();

                // Verificar que el estudiante esté en el grupo
                if (!$grupo->estudiantes()->where('estudiante.id', $estudiante->id)->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El estudiante no está inscrito en este grupo'
                    ], 404);
                }

                Notificacion::crearNotificacion(
                    $estudiante->registro_estudiante,
                    'student',
                    $request->titulo,
                    $request->mensaje,
                    $request->tipo ?? 'info',
                    [
                        'grupo_id' => $grupo->grupo_id,
                        'programa' => $grupo->programa ? $grupo->programa->nombre : null,
                        'modulo' => $grupo->modulo ? $grupo->modulo->nombre : null
                    ]
                );
                $enviadas = 1;
            } else {
                // Enviar a todos los estudiantes del grupo
                $estudiantes = $grupo->estudiantes;

                foreach ($estudiantes as $estudiante) {
                    Notificacion::crearNotificacion(
                        $estudiante->registro_estudiante,
                        'student',
                        $request->titulo,
                        $request->mensaje,
                        $request->tipo ?? 'info',
                        [
                            'grupo_id' => $grupo->grupo_id,
                            'programa' => $grupo->programa ? $grupo->programa->nombre : null,
                            'modulo' => $grupo->modulo ? $grupo->modulo->nombre : null
                        ]
                    );
                    $enviadas++;
                }
            }

            // Registrar en bitácora
            $usuario = $docente->usuario ?? null;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Notificaciones',
                    'codTabla' => $request->grupo_id,
                    'transaccion' => "Docente {$docente->nombre} {$docente->apellido} envió notificación a {$enviadas} estudiante(s) del grupo {$grupo->grupo_id}",
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Notificación enviada a {$enviadas} estudiante(s)",
                'data' => [
                    'enviadas' => $enviadas
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar notificación: ' . $e->getMessage()
            ], 500);
        }
    }
}

