<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CertificadoController extends Controller
{
    /**
     * Listado de certificados disponibles (programas aprobados) (alias para listar)
     */
    public function listar(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Listado de certificados disponibles (programas aprobados)
     */
    public function index(Request $request)
    {
        try {
            $estudiante = $request->auth_user;

            // Obtener grupos donde el estudiante aprobó
            $certificadosDisponibles = $estudiante->grupos()
                ->with(['programa', 'docente'])
                ->wherePivot('nota', '>=', 51)
                ->wherePivot('estado', 'APROBADO')
                ->orderBy('fecha_fin', 'desc')
                ->get()
                ->map(function ($grupo) use ($estudiante) {
                    return [
                        'grupo_id' => $grupo->id,
                        'programa' => [
                            'id' => $grupo->programa->id,
                            'nombre' => $grupo->programa->nombre,
                            'duracion_meses' => $grupo->programa->duracion_meses
                        ],
                        'docente' => $grupo->docente ? $grupo->docente->nombre . ' ' . $grupo->docente->apellido : 'Sin asignar',
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'nota' => $grupo->pivot->nota,
                        'puede_descargar' => true
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Certificados disponibles obtenidos exitosamente',
                'data' => [
                    'certificados' => $certificadosDisponibles,
                    'total_certificados' => $certificadosDisponibles->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener certificados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar y descargar certificado en PDF (alias para descargar)
     */
    public function descargar(Request $request, $grupoId)
    {
        return $this->download($request, $grupoId);
    }

    /**
     * Generar y descargar certificado en PDF
     */
    public function download(Request $request, $grupoId)
    {
        try {
            $estudiante = $request->auth_user;

            // Verificar que el estudiante aprobó el grupo
            $grupo = $estudiante->grupos()
                ->with(['programa', 'docente'])
                ->where('Grupo_id', $grupoId)
                ->wherePivot('nota', '>=', 51)
                ->wherePivot('estado', 'APROBADO')
                ->firstOrFail();

            $nota = $grupo->pivot->nota;

            // Generar datos del certificado
            $certificadoData = [
                'estudiante' => [
                    'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                    'ci' => $estudiante->ci,
                    'registro' => $estudiante->registro_estudiante
                ],
                'programa' => [
                    'nombre' => $grupo->programa->nombre,
                    'duracion_meses' => $grupo->programa->duracion_meses
                ],
                'grupo' => [
                    'fecha_ini' => $grupo->fecha_ini->format('d/m/Y'),
                    'fecha_fin' => $grupo->fecha_fin->format('d/m/Y')
                ],
                'docente' => $grupo->docente ? $grupo->docente->nombre . ' ' . $grupo->docente->apellido : 'Sin asignar',
                'calificacion' => [
                    'nota' => $nota,
                    'estado' => 'APROBADO'
                ],
                'fecha_emision' => now()->format('d/m/Y')
            ];

            // TODO: Implementar generación de PDF usando DomPDF o similar
            // Por ahora retornamos los datos JSON que se usarían para generar el PDF

            return response()->json([
                'success' => true,
                'message' => 'Datos del certificado obtenidos exitosamente',
                'data' => $certificadoData,
                'nota' => 'Implementar generación de PDF con librería DomPDF o TCPDF'
            ], 200);

            // Ejemplo de implementación con DomPDF:
            /*
            $pdf = \PDF::loadView('certificates.template', $certificadoData);
            return $pdf->download('certificado_' . $estudiante->registro_estudiante . '_' . $grupo->id . '.pdf');
            */

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar certificado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Previsualizar certificado (alias para vistaPrevia)
     */
    public function vistaPrevia(Request $request, $grupoId)
    {
        return $this->preview($request, $grupoId);
    }

    /**
     * Previsualizar certificado
     */
    public function preview(Request $request, $grupoId)
    {
        try {
            $estudiante = $request->auth_user;

            $grupo = $estudiante->grupos()
                ->with(['programa', 'docente'])
                ->where('Grupo_id', $grupoId)
                ->wherePivot('nota', '>=', 51)
                ->wherePivot('estado', 'APROBADO')
                ->firstOrFail();

            $certificadoData = [
                'estudiante' => $estudiante->nombre . ' ' . $estudiante->apellido,
                'ci' => $estudiante->ci,
                'programa' => $grupo->programa->nombre,
                'duracion' => $grupo->programa->duracion_meses . ' meses',
                'periodo' => $grupo->fecha_ini->format('d/m/Y') . ' - ' . $grupo->fecha_fin->format('d/m/Y'),
                'docente' => $grupo->docente ? $grupo->docente->nombre . ' ' . $grupo->docente->apellido : 'Sin asignar',
                'nota' => $grupo->pivot->nota,
                'fecha_emision' => now()->format('d/m/Y')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Vista previa del certificado',
                'data' => $certificadoData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar vista previa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
