<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Obtener estadísticas del dashboard del docente
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $docente = $request->auth_user;
            $docenteId = $docente instanceof \App\Models\Docente
                ? $docente->id
                : $docente->id;

            // Obtener grupos del docente
            $grupos = Grupo::where('docente_id', $docenteId)
                ->withCount('estudiantes')
                ->get();

            // Calcular estadísticas
            $totalGrupos = $grupos->count();
            $gruposActivos = $grupos->filter(function ($grupo) {
                return $grupo->esta_activo;
            })->count();

            // Total de estudiantes en todos los grupos
            $totalEstudiantes = $grupos->sum('estudiantes_count');

            // Estudiantes con notas pendientes (sin nota registrada)
            $estudiantesSinNota = DB::table('grupo_estudiante')
                ->join('grupo', 'grupo_estudiante.grupo_id', '=', 'grupo.grupo_id')
                ->where('grupo.docente_id', $docenteId)
                ->whereNull('grupo_estudiante.nota')
                ->count();

            // Estudiantes aprobados
            $estudiantesAprobados = DB::table('grupo_estudiante')
                ->join('grupo', 'grupo_estudiante.grupo_id', '=', 'grupo.grupo_id')
                ->where('grupo.docente_id', $docenteId)
                ->where('grupo_estudiante.estado', 'APROBADO')
                ->count();

            // Estudiantes reprobados
            $estudiantesReprobados = DB::table('grupo_estudiante')
                ->join('grupo', 'grupo_estudiante.grupo_id', '=', 'grupo.grupo_id')
                ->where('grupo.docente_id', $docenteId)
                ->where('grupo_estudiante.estado', 'REPROBADO')
                ->count();

            // Estudiantes en curso
            $estudiantesEnCurso = DB::table('grupo_estudiante')
                ->join('grupo', 'grupo_estudiante.grupo_id', '=', 'grupo.grupo_id')
                ->where('grupo.docente_id', $docenteId)
                ->where('grupo_estudiante.estado', 'EN_CURSO')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_grupos' => $totalGrupos,
                    'grupos_activos' => $gruposActivos,
                    'grupos_finalizados' => $totalGrupos - $gruposActivos,
                    'total_estudiantes' => $totalEstudiantes,
                    'estudiantes_sin_nota' => $estudiantesSinNota,
                    'estudiantes_aprobados' => $estudiantesAprobados,
                    'estudiantes_reprobados' => $estudiantesReprobados,
                    'estudiantes_en_curso' => $estudiantesEnCurso,
                    'calificaciones_pendientes' => $estudiantesSinNota
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
}

