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
            $registroDocente = $docente instanceof \App\Models\Docente
                ? $docente->registro_docente
                : $docente->id;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('grupo_id', $request->grupo_id)
                ->where('registro_docente', $registroDocente)
                ->firstOrFail();

            // Verificar que el estudiante esté en el grupo
            $estudiante = Estudiante::findOrFail($request->estudiante_registro);
            if (!$grupo->estudiantes()->where('registro_estudiante', $request->estudiante_registro)->exists()) {
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

            // Actualizar o crear registro en grupo_estudiante
            $grupo->estudiantes()->updateExistingPivot($request->estudiante_registro, [
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
            $registroDocente = $docente instanceof \App\Models\Docente
                ? $docente->registro_docente
                : $docente->id;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('grupo_id', $request->grupo_id)
                ->where('registro_docente', $registroDocente)
                ->firstOrFail();

            // Verificar que el estudiante esté en el grupo
            $estudiante = Estudiante::findOrFail($request->estudiante_registro);
            if (!$grupo->estudiantes()->where('registro_estudiante', $request->estudiante_registro)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no está inscrito en este grupo'
                ], 404);
            }

            // Obtener nota actual si existe
            $pivot = $grupo->estudiantes()->where('registro_estudiante', $request->estudiante_registro)->first()->pivot;
            $notaActual = $pivot->nota;

            // Actualizar estado en grupo_estudiante
            $grupo->estudiantes()->updateExistingPivot($request->estudiante_registro, [
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
            $registroDocente = $docente instanceof \App\Models\Docente
                ? $docente->registro_docente
                : $docente->id;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('grupo_id', $request->grupo_id)
                ->where('registro_docente', $registroDocente)
                ->firstOrFail();

            $registrosActualizados = 0;
            $errores = [];

            foreach ($request->notas as $notaData) {
                try {
                    // Verificar que el estudiante esté en el grupo
                    if (!$grupo->estudiantes()->where('registro_estudiante', $notaData['estudiante_registro'])->exists()) {
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

                    // Actualizar registro en grupo_estudiante
                    $grupo->estudiantes()->updateExistingPivot($notaData['estudiante_registro'], [
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
}

