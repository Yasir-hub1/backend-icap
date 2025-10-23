<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotaController extends Controller
{
    /**
     * Registrar o actualizar nota de un estudiante
     */
    public function store(Request $request)
    {
        $request->validate([
            'grupo_id' => 'required|exists:Grupo,id',
            'estudiante_id' => 'required|exists:Estudiante,id',
            'nota' => 'required|numeric|min:0|max:100'
        ]);

        DB::beginTransaction();
        try {
            $docente = $request->auth_user;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('id', $request->grupo_id)
                ->where('Docente_id', $docente->id)
                ->with('programa')
                ->firstOrFail();

            // Verificar que el estudiante esté en el grupo
            if (!$grupo->estudiantes()->where('Estudiante_id', $request->estudiante_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no pertenece a este grupo'
                ], 400);
            }

            // Obtener datos del estudiante antes de actualizar
            $estudiante = $grupo->estudiantes()->where('Estudiante_id', $request->estudiante_id)->first();
            $notaAnterior = $estudiante->pivot->nota;

            // Actualizar nota y calcular aprobado
            $aprobado = $request->nota >= 51;

            $grupo->estudiantes()->updateExistingPivot($request->estudiante_id, [
                'nota' => $request->nota,
                'estado' => $aprobado ? 'APROBADO' : 'REPROBADO'
            ]);

            // Registrar en bitácora
            $accion = $notaAnterior ? 'actualizada' : 'registrada';
            $notaInfo = $notaAnterior ? "de {$notaAnterior} a {$request->nota}" : $request->nota;

            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'grupo_estudiante',
                'codTable' => $grupo->id,
                'transaccion' => "Docente {$docente->nombre} {$docente->apellido} {$accion} nota {$notaInfo} para estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) en el programa '{$grupo->programa->nombre}'. Estado: " . ($aprobado ? 'APROBADO' : 'REPROBADO'),
                'Usuario_id' => $docente->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Nota registrada exitosamente',
                'data' => [
                    'estudiante_id' => $request->estudiante_id,
                    'nota' => $request->nota,
                    'aprobado' => $aprobado,
                    'estado' => $aprobado ? 'APROBADO' : 'REPROBADO',
                    'nota_anterior' => $notaAnterior
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar nota',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar notas masivas para un grupo
     */
    public function storeBulk(Request $request)
    {
        $request->validate([
            'grupo_id' => 'required|exists:Grupo,id',
            'notas' => 'required|array',
            'notas.*.estudiante_id' => 'required|exists:Estudiante,id',
            'notas.*.nota' => 'required|numeric|min:0|max:100'
        ]);

        DB::beginTransaction();
        try {
            $docente = $request->auth_user;

            // Verificar que el grupo pertenezca al docente
            $grupo = Grupo::where('id', $request->grupo_id)
                ->where('Docente_id', $docente->id)
                ->with('programa', 'estudiantes')
                ->firstOrFail();

            $notasRegistradas = [];
            $errores = [];

            foreach ($request->notas as $item) {
                // Verificar que el estudiante esté en el grupo
                if (!$grupo->estudiantes()->where('Estudiante_id', $item['estudiante_id'])->exists()) {
                    $errores[] = "Estudiante ID {$item['estudiante_id']} no pertenece al grupo";
                    continue;
                }

                $aprobado = $item['nota'] >= 51;

                $grupo->estudiantes()->updateExistingPivot($item['estudiante_id'], [
                    'nota' => $item['nota'],
                    'estado' => $aprobado ? 'APROBADO' : 'REPROBADO'
                ]);

                $notasRegistradas[] = [
                    'estudiante_id' => $item['estudiante_id'],
                    'nota' => $item['nota'],
                    'aprobado' => $aprobado
                ];
            }

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'grupo_estudiante',
                'codTable' => $grupo->id,
                'transaccion' => "Docente {$docente->nombre} {$docente->apellido} registró/actualizó " . count($notasRegistradas) . " notas para el programa '{$grupo->programa->nombre}'",
                'Usuario_id' => $docente->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($notasRegistradas) . ' notas registradas exitosamente',
                'data' => [
                    'notas_registradas' => $notasRegistradas,
                    'errores' => $errores,
                    'total_exitosas' => count($notasRegistradas),
                    'total_errores' => count($errores)
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar notas masivas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de notas de un grupo
     */
    public function getGroupStatistics(Request $request, $grupoId)
    {
        try {
            $docente = $request->auth_user;

            $grupo = Grupo::where('id', $grupoId)
                ->where('Docente_id', $docente->id)
                ->with(['programa', 'estudiantes'])
                ->firstOrFail();

            $estudiantes = $grupo->estudiantes;

            $conNotas = $estudiantes->filter(function ($e) {
                return $e->pivot->nota !== null;
            });

            $notas = $conNotas->pluck('pivot.nota')->filter();

            $estadisticas = [
                'total_estudiantes' => $estudiantes->count(),
                'con_notas' => $conNotas->count(),
                'sin_notas' => $estudiantes->count() - $conNotas->count(),
                'aprobados' => $conNotas->filter(fn($e) => $e->pivot->nota >= 51)->count(),
                'reprobados' => $conNotas->filter(fn($e) => $e->pivot->nota < 51)->count(),
                'promedio' => $notas->count() > 0 ? round($notas->avg(), 2) : 0,
                'nota_maxima' => $notas->count() > 0 ? $notas->max() : 0,
                'nota_minima' => $notas->count() > 0 ? $notas->min() : 0,
                'porcentaje_aprobacion' => $conNotas->count() > 0 
                    ? round(($conNotas->filter(fn($e) => $e->pivot->nota >= 51)->count() / $conNotas->count()) * 100, 2)
                    : 0
            ];

            return response()->json([
                'success' => true,
                'message' => 'Estadísticas del grupo obtenidas exitosamente',
                'data' => [
                    'grupo' => [
                        'id' => $grupo->id,
                        'programa' => $grupo->programa->nombre
                    ],
                    'estadisticas' => $estadisticas
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
