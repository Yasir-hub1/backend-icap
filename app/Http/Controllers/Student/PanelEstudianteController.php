<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\Inscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PanelEstudianteController extends Controller
{
    /**
     * Get student dashboard data with alerts based on estado_id (alias para obtenerDashboard)
     */
    public function obtenerDashboard(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Get student dashboard data with alerts based on estado_id
     */
    public function index(Request $request)
    {
        try {
            // Obtener el estudiante desde auth_user (agregado por StudentAuthMiddleware)
            $estudiante = $request->auth_user ?? $request->auth_estudiante;

            // Si no está en auth_user, intentar obtenerlo desde el token
            if (!$estudiante || !($estudiante instanceof \App\Models\Estudiante)) {
                try {
                    $payload = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->getPayload();
                    $estudianteId = $payload->get('sub'); // Para estudiantes, sub es el id
                    $estudiante = \App\Models\Estudiante::find($estudianteId);
                    
                    if ($estudiante) {
                        Log::info('Estudiante obtenido desde token en dashboard', [
                            'id' => $estudiante->id,
                            'registro_estudiante' => $estudiante->registro_estudiante
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error obteniendo estudiante en dashboard', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if (!$estudiante) {
                Log::error('Estudiante no encontrado en dashboard', [
                    'auth_user' => $request->auth_user ? get_class($request->auth_user) : null,
                    'auth_estudiante' => $request->auth_estudiante ? get_class($request->auth_estudiante) : null
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            // Cargar relaciones necesarias
            $estudiante->load('estadoEstudiante');

            // Get document status
            // Estudiante hereda de Persona, usa el mismo id
            // Obtener todos los tipos de documento (incluyendo el opcional "Título de Bachiller")
            $tiposDocumento = TipoDocumento::whereIn('nombre_entidad', [
                'Carnet de Identidad - Anverso',
                'Carnet de Identidad - Reverso',
                'Certificado de Nacimiento',
                'Título de Bachiller'
            ])->get();
            
            // Documentos requeridos (excluyendo "Título de Bachiller" que es opcional)
            $tiposRequeridos = $tiposDocumento->filter(function($tipo) {
                return $tipo->nombre_entidad !== 'Título de Bachiller';
            });
            $tiposRequeridosCount = $tiposRequeridos->count(); // Debe ser 3
            
            // Obtener documentos del estudiante agrupados por tipo
            // Para cada tipo, obtener la última versión APROBADA (estado = '1')
            $tiposRequeridosIds = $tiposRequeridos->pluck('tipo_documento_id')->toArray();
            
            // Lista de documentos requeridos con su estado (incluyendo el opcional)
            // Para cada tipo, obtener la última versión (cualquier estado)
            $documentosRequeridosLista = $tiposDocumento->map(function($tipo) use ($estudiante) {
                // Obtener la última versión de este tipo de documento (cualquier estado: 0=pendiente, 1=aprobado, 2=rechazado)
                $documento = Documento::where('persona_id', $estudiante->id)
                    ->where('tipo_documento_id', $tipo->tipo_documento_id)
                    ->orderBy('version', 'desc')
                    ->first();
                
                return [
                    'tipo_documento_id' => $tipo->tipo_documento_id,
                    'nombre' => $tipo->nombre_entidad,
                    'subido' => $documento !== null, // Cualquier documento subido cuenta
                    'estado' => $documento ? $documento->estado : null,
                    'observaciones' => $documento ? $documento->observaciones : null,
                    'fecha_subida' => $documento && $documento->created_at ? $documento->created_at->toDateTimeString() : null,
                    'documento_id' => $documento ? $documento->documento_id : null
                ];
            });
            
            // Contar documentos requeridos SUBIDOS (cualquier estado, excluyendo "Título de Bachiller")
            $documentosSubidos = $documentosRequeridosLista->filter(function($doc) use ($tiposRequeridosIds) {
                return in_array($doc['tipo_documento_id'], $tiposRequeridosIds) && $doc['subido'];
            })->count();
            
            // Contar documentos validados (aprobados)
            $documentosValidados = Documento::where('persona_id', $estudiante->id)
                                           ->whereIn('tipo_documento_id', $tiposRequeridosIds)
                                           ->where('estado', '1') // '1' = aprobado
                                           ->distinct('tipo_documento_id')
                                           ->count('tipo_documento_id');
            
            // Obtener documentos rechazados (estado = '2')
            $documentosRechazados = Documento::where('persona_id', $estudiante->id)
                                             ->whereIn('tipo_documento_id', $tiposRequeridosIds)
                                             ->where('estado', '2') // '2' = rechazado
                                             ->get();
            
            // Documentos faltantes (no subidos) - solo los requeridos (excluyendo el opcional)
            $documentosFaltantes = $documentosRequeridosLista->filter(function($doc) {
                // Excluir "Título de Bachiller" de los faltantes
                return !$doc['subido'] && !str_contains(strtolower($doc['nombre']), 'título') && !str_contains(strtolower($doc['nombre']), 'bachiller');
            })->values();

            // Get inscriptions count
            $inscripcionesCount = Inscripcion::where('estudiante_id', $estudiante->id)->count();

            // Get active groups count
            // La tabla grupo_estudiante usa estudiante_id (id de Estudiante, no registro_estudiante)
            // La tabla grupo usa grupo_id como primary key (no id)
            $gruposActivos = DB::table('grupo_estudiante')
                              ->join('grupo', 'grupo_estudiante.grupo_id', '=', 'grupo.grupo_id')
                              ->where('grupo_estudiante.estudiante_id', $estudiante->id)
                              ->where('grupo.fecha_fin', '>=', now())
                              ->count();

            // Get pending payments
            // Usar nombres de tablas en minúsculas (PostgreSQL)
            // La tabla se llama 'plan_pago' (singular), no 'plan_pagos'
            // La tabla pagos usa cuota_id (singular), no cuotas_id
            $pagosPendientes = DB::table('cuotas')
                                ->join('plan_pago', 'cuotas.plan_pago_id', '=', 'plan_pago.id')
                                ->join('inscripcion', 'plan_pago.inscripcion_id', '=', 'inscripcion.id')
                                ->leftJoin('pagos', 'cuotas.id', '=', 'pagos.cuota_id')
                                ->where('inscripcion.estudiante_id', $estudiante->id)
                                ->whereNull('pagos.id')
                                ->count();

            // Determine alert based on estado_id
            $alert = $this->getAlertByEstado($estudiante->estado_id, $tiposRequeridosCount, $documentosSubidos, $documentosValidados, $documentosRechazados, $documentosFaltantes);

            return response()->json([
                'success' => true,
                'data' => [
                    'estudiante' => [
                        'id' => $estudiante->registro_estudiante,
                        'nombre_completo' => $estudiante->nombre_completo,
                        'registro_estudiante' => $estudiante->registro_estudiante,
                        'estado_id' => $estudiante->estado_id,
                        'estado_nombre' => $estudiante->estadoEstudiante->nombre_estado ?? 'Desconocido'
                    ],
                    'estadisticas' => [
                        'documentos_requeridos' => $tiposRequeridosCount,
                        'documentos_subidos' => $documentosSubidos,
                        'documentos_validados' => $documentosValidados,
                        'inscripciones' => $inscripcionesCount,
                        'grupos_activos' => $gruposActivos,
                        'pagos_pendientes' => $pagosPendientes
                    ],
                    'documentos' => [
                        'requeridos' => $documentosRequeridosLista,
                        'faltantes' => $documentosFaltantes
                    ],
                    'alert' => $alert,
                    'menu_habilitado' => [
                        'documentos' => true,
                        'programas' => $estudiante->estado_id == 4,
                        'inscripciones' => $estudiante->estado_id == 4,
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
    private function getAlertByEstado($estadoId, $tiposRequeridos, $documentosSubidos, $documentosValidados, $documentosRechazados, $documentosFaltantes = [])
    {
        // Lista de documentos requeridos con nombres específicos
        $nombresDocumentos = [
            'Fotocopia de cédula de identidad',
            'Certificado de nacimiento',
            '2 fotografías tamaño 3x3 (fondo gris)',
            'Título de bachiller (solo para técnico medio)'
        ];
        
        switch ($estadoId) {
            case 1: // Pre-registrado
                $listaFaltantes = $documentosFaltantes->map(function($doc) use ($nombresDocumentos) {
                    return $doc['nombre'] ?? 'Documento requerido';
                })->toArray();
                
                // Si no hay documentos faltantes específicos, usar la lista estándar
                if (empty($listaFaltantes)) {
                    $listaFaltantes = $nombresDocumentos;
                }
                
                return [
                    'type' => 'error',
                    'title' => '⚠️ URGENTE: Debe subir sus documentos',
                    'message' => 'Para completar tu registro y poder inscribirte a programas, debes subir los siguientes documentos:',
                    'documentos_faltantes' => $listaFaltantes,
                    'icon' => 'mdi-alert-circle',
                    'actions' => ['upload_documents'],
                    'edad_minima' => 'Edad mínima: 14 años'
                ];

            case 2: // Documentos incompletos
                $faltantes = $tiposRequeridos - $documentosSubidos;
                $listaFaltantes = $documentosFaltantes->map(function($doc) {
                    return $doc['nombre'] ?? 'Documento requerido';
                })->toArray();
                
                return [
                    'type' => 'warning',
                    'title' => '⚠️ ATENCIÓN: Documentos pendientes',
                    'message' => "Tienes {$faltantes} documento(s) pendiente(s) de subir. Progreso: {$documentosSubidos} de {$tiposRequeridos}",
                    'documentos_faltantes' => $listaFaltantes,
                    'icon' => 'mdi-file-alert',
                    'progress' => $tiposRequeridos > 0 ? ($documentosSubidos / $tiposRequeridos) * 100 : 0,
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
