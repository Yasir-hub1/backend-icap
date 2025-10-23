<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelEstudianteController extends Controller
{
    /**
     * Get student dashboard data with alerts based on estado_id
     */
    public function index(Request $request)
    {
        try {
            $estudiante = auth()->user();

            // Get document status
            $tiposRequeridos = TipoDocumento::where('nombre_entidad', 'Estudiante')->count();
            $documentosSubidos = Documento::where('estudiante_id', $estudiante->id)->count();
            $documentosValidados = Documento::where('estudiante_id', $estudiante->id)
                                           ->where('estado', 1)
                                           ->count();
            $documentosRechazados = Documento::where('estudiante_id', $estudiante->id)
                                             ->where('estado', 2)
                                             ->get();

            // Get inscriptions count
            $inscripcionesCount = Inscripcion::where('Estudiante_id', $estudiante->id)->count();

            // Get active groups count
            $gruposActivos = DB::table('grupo_estudiante')
                              ->join('Grupo', 'grupo_estudiante.Grupo_id', '=', 'Grupo.id')
                              ->where('grupo_estudiante.Estudiante_id', $estudiante->id)
                              ->where('Grupo.fecha_fin', '>=', now())
                              ->count();

            // Get pending payments
            $pagosPendientes = DB::table('cuotas')
                                ->join('plan_pagos', 'cuotas.plan_pagos_id', '=', 'plan_pagos.id')
                                ->join('Inscripcion', 'plan_pagos.Inscripcion_id', '=', 'Inscripcion.id')
                                ->leftJoin('pagos', 'cuotas.id', '=', 'pagos.cuotas_id')
                                ->where('Inscripcion.Estudiante_id', $estudiante->id')
                                ->whereNull('pagos.id')
                                ->count();

            // Determine alert based on Estado_id
            $alert = $this->getAlertByEstado($estudiante->Estado_id, $tiposRequeridos, $documentosSubidos, $documentosValidados, $documentosRechazados);

            return response()->json([
                'success' => true,
                'data' => [
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'nombre_completo' => $estudiante->nombre_completo,
                        'registro_estudiante' => $estudiante->registro_estudiante,
                        'Estado_id' => $estudiante->Estado_id,
                        'estado_nombre' => $estudiante->estado->nombre_estado ?? 'Desconocido'
                    ],
                    'estadisticas' => [
                        'documentos_requeridos' => $tiposRequeridos,
                        'documentos_subidos' => $documentosSubidos,
                        'documentos_validados' => $documentosValidados,
                        'inscripciones' => $inscripcionesCount,
                        'grupos_activos' => $gruposActivos,
                        'pagos_pendientes' => $pagosPendientes
                    ],
                    'alert' => $alert,
                    'menu_habilitado' => [
                        'documentos' => true,
                        'programas' => $estudiante->Estado_id == 4,
                        'inscripciones' => $estudiante->Estado_id == 4,
                        'pagos' => $inscripcionesCount > 0,
                        'notas' => $gruposActivos > 0
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get alert configuration based on student state
     */
    private function getAlertByEstado($estadoId, $tiposRequeridos, $documentosSubidos, $documentosValidados, $documentosRechazados)
    {
        switch ($estadoId) {
            case 1: // Pre-registrado
                return [
                    'type' => 'error',
                    'title' => 'URGENTE: Debe subir sus documentos',
                    'message' => 'Debe subir sus documentos para completar su registro',
                    'icon' => 'mdi-alert-circle',
                    'actions' => ['upload_documents']
                ];

            case 2: // Documentos incompletos
                $faltantes = $tiposRequeridos - $documentosSubidos;
                return [
                    'type' => 'warning',
                    'title' => 'ATENCIÓN: Documentos pendientes',
                    'message' => "Tiene {$faltantes} documento(s) pendiente(s) de subir. Progreso: {$documentosSubidos} de {$tiposRequeridos}",
                    'icon' => 'mdi-file-alert',
                    'progress' => ($documentosSubidos / $tiposRequeridos) * 100,
                    'actions' => ['upload_documents']
                ];

            case 3: // En revisión
                return [
                    'type' => 'info',
                    'title' => 'Documentos en revisión',
                    'message' => 'Sus documentos están en revisión por el área administrativa. Será notificado cuando sean validados.',
                    'icon' => 'mdi-clock-outline',
                    'actions' => []
                ];

            case 4: // Validado - Activo
                return [
                    'type' => 'success',
                    'title' => '✅ Su cuenta está activa',
                    'message' => 'Puede inscribirse a programas disponibles',
                    'icon' => 'mdi-check-circle',
                    'actions' => ['view_programs']
                ];

            case 5: // Rechazado
                $rechazadosInfo = $documentosRechazados->map(function($doc) {
                    return [
                        'documento' => $doc->nombre_documento,
                        'motivo' => $doc->observaciones
                    ];
                });
                return [
                    'type' => 'error',
                    'title' => '❌ Documentos rechazados',
                    'message' => 'Sus documentos fueron rechazados. Revise las observaciones y vuelva a subirlos.',
                    'icon' => 'mdi-close-circle',
                    'rejected_documents' => $rechazadosInfo,
                    'actions' => ['re_upload_documents']
                ];

            default:
                return [
                    'type' => 'info',
                    'title' => 'Estado desconocido',
                    'message' => 'Contacte con el administrador',
                    'icon' => 'mdi-help-circle',
                    'actions' => []
                ];
        }
    }
}
