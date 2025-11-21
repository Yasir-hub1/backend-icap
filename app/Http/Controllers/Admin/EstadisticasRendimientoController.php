<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Docente;
use App\Models\Modulo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EstadisticasRendimientoController extends Controller
{
    /**
     * Obtener estadísticas de rendimiento por grupo
     */
    public function porGrupo(Request $request): JsonResponse
    {
        try {
            $grupoId = $request->get('grupo_id');

            if (!$grupoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El grupo_id es requerido'
                ], 422);
            }

            $grupo = Grupo::with(['programa', 'modulo', 'docente'])->findOrFail($grupoId);

            // Obtener estadísticas de estudiantes del grupo
            $estadisticas = DB::table('grupo_estudiante')
                ->where('grupo_id', $grupoId)
                ->selectRaw('
                    COUNT(*) as total_estudiantes,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as aprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as reprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as retirados,
                    COUNT(CASE WHEN estado = ? OR estado IS NULL THEN 1 END) as en_curso,
                    AVG(nota) as promedio_notas,
                    MAX(nota) as nota_maxima,
                    MIN(nota) as nota_minima,
                    COUNT(CASE WHEN nota >= 51 THEN 1 END) as aprobados_por_nota,
                    COUNT(CASE WHEN nota < 51 AND nota IS NOT NULL THEN 1 END) as reprobados_por_nota
                ', ['APROBADO', 'REPROBADO', 'RETIRADO', 'EN_CURSO'])
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'grupo' => [
                        'grupo_id' => $grupo->grupo_id,
                        'programa' => $grupo->programa ? $grupo->programa->nombre : null,
                        'modulo' => $grupo->modulo ? $grupo->modulo->nombre : null,
                        'docente' => $grupo->docente ? "{$grupo->docente->nombre} {$grupo->docente->apellido}" : null,
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin
                    ],
                    'estadisticas' => [
                        'total_estudiantes' => (int)$estadisticas->total_estudiantes,
                        'aprobados' => (int)$estadisticas->aprobados,
                        'reprobados' => (int)$estadisticas->reprobados,
                        'retirados' => (int)$estadisticas->retirados,
                        'en_curso' => (int)$estadisticas->en_curso,
                        'promedio_notas' => $estadisticas->promedio_notas ? round((float)$estadisticas->promedio_notas, 2) : null,
                        'nota_maxima' => $estadisticas->nota_maxima ? (float)$estadisticas->nota_maxima : null,
                        'nota_minima' => $estadisticas->nota_minima ? (float)$estadisticas->nota_minima : null,
                        'aprobados_por_nota' => (int)$estadisticas->aprobados_por_nota,
                        'reprobados_por_nota' => (int)$estadisticas->reprobados_por_nota,
                        'tasa_aprobacion' => $estadisticas->total_estudiantes > 0
                            ? round(($estadisticas->aprobados / $estadisticas->total_estudiantes) * 100, 2)
                            : 0
                    ]
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de rendimiento por docente
     */
    public function porDocente(Request $request): JsonResponse
    {
        try {
            $registroDocente = $request->get('registro_docente');

            if (!$registroDocente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El registro_docente es requerido'
                ], 422);
            }

            $docente = Docente::findOrFail($registroDocente);

            // Obtener todos los grupos del docente
            $gruposIds = Grupo::where('registro_docente', $registroDocente)
                ->pluck('grupo_id');

            if ($gruposIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'docente' => [
                            'registro_docente' => $docente->registro_docente,
                            'nombre' => "{$docente->nombre} {$docente->apellido}"
                        ],
                        'estadisticas' => [
                            'total_grupos' => 0,
                            'total_estudiantes' => 0,
                            'aprobados' => 0,
                            'reprobados' => 0,
                            'retirados' => 0,
                            'promedio_notas' => null,
                            'tasa_aprobacion' => 0
                        ]
                    ],
                    'message' => 'El docente no tiene grupos asignados'
                ]);
            }

            // Obtener estadísticas agregadas de todos los grupos
            $estadisticas = DB::table('grupo_estudiante')
                ->whereIn('grupo_id', $gruposIds)
                ->selectRaw('
                    COUNT(DISTINCT grupo_id) as total_grupos,
                    COUNT(*) as total_estudiantes,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as aprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as reprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as retirados,
                    AVG(nota) as promedio_notas,
                    COUNT(CASE WHEN nota >= 51 THEN 1 END) as aprobados_por_nota
                ', ['APROBADO', 'REPROBADO', 'RETIRADO'])
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'docente' => [
                        'registro_docente' => $docente->registro_docente,
                        'nombre' => "{$docente->nombre} {$docente->apellido}",
                        'cargo' => $docente->cargo,
                        'area_especializacion' => $docente->area_de_especializacion
                    ],
                    'estadisticas' => [
                        'total_grupos' => (int)$estadisticas->total_grupos,
                        'total_estudiantes' => (int)$estadisticas->total_estudiantes,
                        'aprobados' => (int)$estadisticas->aprobados,
                        'reprobados' => (int)$estadisticas->reprobados,
                        'retirados' => (int)$estadisticas->retirados,
                        'promedio_notas' => $estadisticas->promedio_notas ? round((float)$estadisticas->promedio_notas, 2) : null,
                        'aprobados_por_nota' => (int)$estadisticas->aprobados_por_nota,
                        'tasa_aprobacion' => $estadisticas->total_estudiantes > 0
                            ? round(($estadisticas->aprobados / $estadisticas->total_estudiantes) * 100, 2)
                            : 0
                    ]
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de rendimiento por módulo
     */
    public function porModulo(Request $request): JsonResponse
    {
        try {
            $moduloId = $request->get('modulo_id');

            if (!$moduloId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El modulo_id es requerido'
                ], 422);
            }

            $modulo = Modulo::findOrFail($moduloId);

            // Obtener todos los grupos del módulo
            $gruposIds = Grupo::where('modulo_id', $moduloId)
                ->pluck('grupo_id');

            if ($gruposIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'modulo' => [
                            'modulo_id' => $modulo->modulo_id,
                            'nombre' => $modulo->nombre
                        ],
                        'estadisticas' => [
                            'total_grupos' => 0,
                            'total_estudiantes' => 0,
                            'aprobados' => 0,
                            'reprobados' => 0,
                            'promedio_notas' => null,
                            'tasa_aprobacion' => 0
                        ]
                    ],
                    'message' => 'El módulo no tiene grupos asignados'
                ]);
            }

            // Obtener estadísticas agregadas de todos los grupos
            $estadisticas = DB::table('grupo_estudiante')
                ->whereIn('grupo_id', $gruposIds)
                ->selectRaw('
                    COUNT(DISTINCT grupo_id) as total_grupos,
                    COUNT(*) as total_estudiantes,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as aprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as reprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as retirados,
                    AVG(nota) as promedio_notas,
                    COUNT(CASE WHEN nota >= 51 THEN 1 END) as aprobados_por_nota
                ', ['APROBADO', 'REPROBADO', 'RETIRADO'])
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'modulo' => [
                        'modulo_id' => $modulo->modulo_id,
                        'nombre' => $modulo->nombre,
                        'descripcion' => $modulo->descripcion
                    ],
                    'estadisticas' => [
                        'total_grupos' => (int)$estadisticas->total_grupos,
                        'total_estudiantes' => (int)$estadisticas->total_estudiantes,
                        'aprobados' => (int)$estadisticas->aprobados,
                        'reprobados' => (int)$estadisticas->reprobados,
                        'retirados' => (int)$estadisticas->retirados,
                        'promedio_notas' => $estadisticas->promedio_notas ? round((float)$estadisticas->promedio_notas, 2) : null,
                        'aprobados_por_nota' => (int)$estadisticas->aprobados_por_nota,
                        'tasa_aprobacion' => $estadisticas->total_estudiantes > 0
                            ? round(($estadisticas->aprobados / $estadisticas->total_estudiantes) * 100, 2)
                            : 0
                    ]
                ],
                'message' => 'Estadísticas obtenidas exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen general de rendimiento
     */
    public function resumenGeneral(Request $request): JsonResponse
    {
        try {
            // Obtener estadísticas generales de todos los grupos
            $estadisticas = DB::table('grupo_estudiante')
                ->selectRaw('
                    COUNT(DISTINCT grupo_id) as total_grupos,
                    COUNT(DISTINCT estudiante_registro) as total_estudiantes_unicos,
                    COUNT(*) as total_registros,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as aprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as reprobados,
                    COUNT(CASE WHEN estado = ? THEN 1 END) as retirados,
                    COUNT(CASE WHEN estado = ? OR estado IS NULL THEN 1 END) as en_curso,
                    AVG(nota) as promedio_notas,
                    MAX(nota) as nota_maxima,
                    MIN(nota) as nota_minima,
                    COUNT(CASE WHEN nota >= 51 THEN 1 END) as aprobados_por_nota,
                    COUNT(CASE WHEN nota < 51 AND nota IS NOT NULL THEN 1 END) as reprobados_por_nota
                ', ['APROBADO', 'REPROBADO', 'RETIRADO', 'EN_CURSO'])
                ->first();

            // Obtener distribución de notas
            $distribucionNotas = DB::table('grupo_estudiante')
                ->whereNotNull('nota')
                ->selectRaw('
                    CASE
                        WHEN nota >= 90 THEN \'90-100\'
                        WHEN nota >= 80 THEN \'80-89\'
                        WHEN nota >= 70 THEN \'70-79\'
                        WHEN nota >= 60 THEN \'60-69\'
                        WHEN nota >= 51 THEN \'51-59\'
                        ELSE \'0-50\'
                    END as rango,
                    COUNT(*) as cantidad
                ')
                ->groupBy('rango')
                ->orderBy('rango', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'estadisticas_generales' => [
                        'total_grupos' => (int)$estadisticas->total_grupos,
                        'total_estudiantes_unicos' => (int)$estadisticas->total_estudiantes_unicos,
                        'total_registros' => (int)$estadisticas->total_registros,
                        'aprobados' => (int)$estadisticas->aprobados,
                        'reprobados' => (int)$estadisticas->reprobados,
                        'retirados' => (int)$estadisticas->retirados,
                        'en_curso' => (int)$estadisticas->en_curso,
                        'promedio_notas' => $estadisticas->promedio_notas ? round((float)$estadisticas->promedio_notas, 2) : null,
                        'nota_maxima' => $estadisticas->nota_maxima ? (float)$estadisticas->nota_maxima : null,
                        'nota_minima' => $estadisticas->nota_minima ? (float)$estadisticas->nota_minima : null,
                        'aprobados_por_nota' => (int)$estadisticas->aprobados_por_nota,
                        'reprobados_por_nota' => (int)$estadisticas->reprobados_por_nota,
                        'tasa_aprobacion' => $estadisticas->total_registros > 0
                            ? round(($estadisticas->aprobados / $estadisticas->total_registros) * 100, 2)
                            : 0
                    ],
                    'distribucion_notas' => $distribucionNotas->map(function ($item) {
                        return [
                            'rango' => $item->rango,
                            'cantidad' => (int)$item->cantidad
                        ];
                    })
                ],
                'message' => 'Resumen general obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen: ' . $e->getMessage()
            ], 500);
        }
    }
}

