<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\Bitacora;
use App\Traits\RegistraBitacora;
use App\Traits\EnviaNotificaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ValidacionDocumentoController extends Controller
{
    use RegistraBitacora, EnviaNotificaciones;
    /**
     * Lista estudiantes con Estado_id=3 (documentos pendientes de validación)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listar(Request $request)
    {
        return $this->index($request);
    }

    /**
     * Lista estudiantes con Estado_id=3 (documentos pendientes de validación)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');

            // SEGÚN EL FLUJO:
            // - estado_id = 3 (En revisión): Estudiante completó los 3 documentos requeridos y están pendientes de validación
            // - estado_id = 5 (Rechazado): Documentos fueron rechazados, pero si volvió a subir documentos, pueden estar pendientes
            //
            // El endpoint debe mostrar estudiantes que:
            // 1. Tengan estado_id = 3 (En revisión) - estos son los que completaron los 3 documentos
            // 2. Y que tengan al menos un documento con estado = '0' (pendiente de revisión)
            // 3. También incluir estado_id = 5 si tienen documentos pendientes (volvieron a subir después del rechazo)

            $estudiantes = Estudiante::with(['estado', 'usuario'])
                ->where(function ($query) {
                    // Estudiantes en estado 3 (En revisión) - completaron los 3 documentos
                    $query->where('estado_id', 3)
                          // O estudiantes en estado 5 (Rechazado) que volvieron a subir documentos
                          ->orWhere('estado_id', 5);
                })
                ->whereHas('documentos', function ($query) {
                    // Que tengan al menos un documento pendiente (estado = '0')
                    $query->where(function ($q) {
                        $q->where('estado', '0')
                          ->orWhere('estado', 0);
                    });
                })
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('nombre', 'ILIKE', "%{$search}%")
                          ->orWhere('apellido', 'ILIKE', "%{$search}%")
                          ->orWhere('ci', 'ILIKE', "%{$search}%")
                          ->orWhere('registro_estudiante', 'ILIKE', "%{$search}%");
                    });
                })
                ->withCount(['documentos as documentos_pendientes' => function ($query) {
                    // Contar documentos pendientes (estado = '0' o 0)
                    $query->where(function ($q) {
                        $q->where('estado', '0')
                          ->orWhere('estado', 0);
                    });
                }])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Transformar los datos para incluir información adicional
            $estudiantes->getCollection()->transform(function ($estudiante) {
                return [
                    'id' => $estudiante->id,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'ci' => $estudiante->ci,
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'estado_id' => $estudiante->estado_id,
                    'estado' => $estudiante->estado ? $estudiante->estado->nombre_estado : null,
                    'documentos_pendientes' => $estudiante->documentos_pendientes ?? 0,
                    'email' => $estudiante->usuario ? $estudiante->usuario->email : null,
                    'celular' => $estudiante->celular,
                    'provincia' => $estudiante->provincia
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Estudiantes con documentos pendientes obtenidos exitosamente',
                'data' => $estudiantes
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en ValidacionDocumentoController::index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estudiantes con documentos pendientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver documentos de un estudiante específico con detalle y versiones
     *
     * @param  int  $estudianteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function obtener($estudianteId)
    {
        return $this->show($estudianteId);
    }

    /**
     * Ver documentos de un estudiante específico con detalle y versiones
     *
     * @param  int  $estudianteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($estudianteId)
    {
        try {
            $estudiante = Estudiante::with(['estado'])->findOrFail($estudianteId);

            // Obtener documentos a través de la relación con Persona
            $documentos = \App\Models\Documento::where('persona_id', $estudiante->id)
                ->with(['tipoDocumento', 'convenio'])
                ->orderBy('tipo_documento_id')
                ->orderBy('version', 'desc')
                ->get();

            // Agrupar documentos por tipo y listar versiones
            $documentosAgrupados = $documentos->groupBy('tipo_documento_id')->map(function ($docs) {
                return [
                    'tipo_documento' => $docs->first()->tipoDocumento,
                    'versiones' => $docs->map(function ($doc) {
                        return [
                            'id' => $doc->documento_id,
                            'nombre' => $doc->nombre_documento,
                            'version' => $doc->version,
                            'path' => $doc->path_documento,
                            'estado' => $doc->estado, // 0: pendiente, 1: aprobado, 2: rechazado
                            'observaciones' => $doc->observaciones,
                            'fecha_subida' => $doc->created_at,
                            'url_descarga' => $doc->path_documento ? url(Storage::url($doc->path_documento)) : null
                        ];
                    })->values()
                ];
            })->values();

            return response()->json([
                'success' => true,
                'message' => 'Documentos del estudiante obtenidos exitosamente',
                'data' => [
                    'estudiante' => [
                        'id' => $estudiante->id,
                        'nombre_completo' => $estudiante->nombre . ' ' . $estudiante->apellido,
                        'ci' => $estudiante->ci,
                        'registro' => $estudiante->registro_estudiante,
                        'estado' => $estudiante->estado
                    ],
                    'documentos' => $documentosAgrupados
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentos del estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar un documento individual (alias para approve)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $documentoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function aprobar(Request $request, $documentoId)
    {
        return $this->approve($request, $documentoId);
    }

    /**
     * Aprobar un documento individual
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $documentoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve(Request $request, $documentoId)
    {
        DB::beginTransaction();
        try {
            $documento = Documento::with(['persona', 'tipoDocumento'])->findOrFail($documentoId);

            // Validar que el documento esté pendiente
            if ($documento->estado != '0' && $documento->estado != 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El documento ya ha sido procesado'
                ], 400);
            }

            // Aprobar documento
            $documento->estado = '1'; // 1 = aprobado
            $documento->observaciones = $request->input('observaciones', 'Documento aprobado');
            $documento->save();

            // Registrar en bitácora
            $persona = $documento->persona;
            $estudiante = $persona ? \App\Models\Estudiante::find($persona->id) : null;
            $descripcion = "Documento '{$documento->tipoDocumento->nombre_entidad}' del estudiante " .
                ($estudiante ? "{$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci})" :
                ($persona ? "{$persona->nombre} {$persona->apellido} (CI: {$persona->ci})" : "ID: {$persona->id}")) . " fue APROBADO";
            $this->registrarAccion('documento', $documento->documento_id, 'APROBAR', $descripcion);

            // Enviar notificación al estudiante
            if ($estudiante) {
                $this->notificarDocumentoAprobado($estudiante, $documento->tipoDocumento->nombre_entidad ?? 'Documento');

                // Verificar si todos los documentos requeridos están aprobados
                if ($this->verificarDocumentosCompletos($estudiante->id)) {
                    // Cambiar estado del estudiante a 4 (Apto para inscripción)
                    if ($estudiante->estado_id != 4) {
                        $estudiante->estado_id = 4;
                        $estudiante->save();

                        // Registrar en bitácora
                        $descripcionEstado = "Todos los documentos requeridos del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) fueron APROBADOS. Estado cambiado a 'Apto para inscripción' (Estado_id=4).";
                        $this->registrarAccion('estudiante', $estudiante->id, 'APROBAR_DOCUMENTOS', $descripcionEstado);

                        // Enviar notificación al estudiante de que todos sus documentos están aprobados
                        $this->notificarDocumentosCompletos($estudiante);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Documento aprobado exitosamente',
                'data' => $documento,
                'estudiante_activado' => $estudiante && $estudiante->estado_id == 4
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar documento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar documento con motivo y crear nueva versión
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rechazar(Request $request)
    {
        return $this->reject($request);
    }

    /**
     * Rechazar documento con motivo y crear nueva versión
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request)
    {
        $request->validate([
            'documento_id' => 'required|exists:documento,documento_id',
            'motivo' => 'required|string|min:10|max:500'
        ]);

        DB::beginTransaction();
        try {
            $documento = Documento::with(['persona', 'tipoDocumento'])->findOrFail($request->documento_id);

            // Validar que el documento esté pendiente
            if ($documento->estado != 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El documento ya ha sido procesado'
                ], 400);
            }

            // Rechazar documento actual
            $documento->estado = '2'; // 2 = rechazado
            $documento->observaciones = $request->motivo;
            $documento->save();

            // Crear nueva versión (pendiente de subida por el estudiante)
            $nuevaVersion = (float)$documento->version + 1.0;
            $nuevoDocumento = Documento::create([
                'nombre_documento' => $documento->nombre_documento,
                'version' => (string)$nuevaVersion,
                'path_documento' => null, // El estudiante deberá subir el archivo
                'estado' => '0', // pendiente
                'observaciones' => "Versión {$nuevaVersion} - Requerida por rechazo de versión anterior. Motivo: {$request->motivo}",
                'tipo_documento_id' => $documento->tipo_documento_id,
                'persona_id' => $documento->persona_id,
                'convenio_id' => $documento->convenio_id
            ]);

            // Registrar rechazo en bitácora
            $persona = $documento->persona;
            $estudiante = $persona ? \App\Models\Estudiante::find($persona->id) : null;
            $descripcion = "Documento '{$documento->tipoDocumento->nombre_entidad}' del estudiante " .
                ($estudiante ? "{$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci})" :
                ($persona ? "{$persona->nombre} {$persona->apellido} (CI: {$persona->ci})" : "ID: {$persona->id}")) .
                " fue RECHAZADO. Motivo: {$request->motivo}. Nueva versión {$nuevaVersion} creada.";
            $this->registrarAccion('documento', $documento->documento_id, 'RECHAZAR', $descripcion);

            // SEGÚN EL FLUJO: Cuando se rechaza un documento, cambiar estado del estudiante a 5 (Rechazado)
            // El estudiante recibirá notificación y deberá volver a subir los documentos
            if ($estudiante) {
                // Cambiar estado a 5 (Rechazado) si no está ya en ese estado
                if ($estudiante->estado_id != 5) {
                    $estudiante->update(['estado_id' => 5]);

                    // Registrar cambio de estado en bitácora
                    $descripcionEstado = "Estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) cambió a estado 'Rechazado' (estado_id=5) debido al rechazo del documento '{$documento->tipoDocumento->nombre_entidad}'. Motivo: {$request->motivo}";
                    $this->registrarAccion('estudiante', $estudiante->id, 'RECHAZAR_DOCUMENTO', $descripcionEstado);
                }

                $this->notificarDocumentoRechazado($estudiante, $documento->tipoDocumento->nombre_entidad ?? 'Documento', $request->motivo);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Documento rechazado exitosamente. Se creó una nueva versión para el estudiante',
                'data' => [
                    'documento_rechazado' => $documento,
                    'nueva_version' => $nuevoDocumento
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar documento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar todos los documentos de un estudiante y cambiar su estado a 4
     *
     * @param  int  $estudianteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function aprobarTodos(Request $request, $estudianteId)
    {
        return $this->approveAll($request, $estudianteId);
    }

    /**
     * Aprobar todos los documentos de un estudiante y cambiar su estado a 4
     *
     * @param  int  $estudianteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveAll(Request $request, $estudianteId)
    {
        DB::beginTransaction();
        try {
            $estudiante = Estudiante::with('documentos')->findOrFail($estudianteId);

            // Validar que el estudiante esté en estado 3 (documentos pendientes)
            if ($estudiante->estado_id != 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no está en estado de documentos pendientes'
                ], 400);
            }

            // Obtener documentos pendientes (última versión de cada tipo)
            $documentosPendientes = \App\Models\Documento::where('persona_id', $estudiante->id)
                ->where('estado', '0')
                ->get()
                ->groupBy('tipo_documento_id')
                ->map(function ($docs) {
                    return $docs->sortByDesc('version')->first();
                });

            if ($documentosPendientes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay documentos pendientes para aprobar'
                ], 400);
            }

            // Aprobar todos los documentos pendientes
            $documentosAprobados = [];
            foreach ($documentosPendientes as $documento) {
                $documento->estado = '1'; // aprobado
                $documento->observaciones = 'Aprobado en validación masiva';
                $documento->save();
                $documentosAprobados[] = $documento;

                // Registrar en bitácora cada aprobación
                $personaDoc = $documento->persona;
                $estudianteDoc = $personaDoc ? \App\Models\Estudiante::find($personaDoc->id) : null;
                $descripcionDoc = "Documento '{$documento->tipoDocumento->nombre_entidad}' del estudiante " .
                    ($estudianteDoc ? "{$estudianteDoc->nombre} {$estudianteDoc->apellido} (CI: {$estudianteDoc->ci})" :
                    ($personaDoc ? "{$personaDoc->nombre} {$personaDoc->apellido} (CI: {$personaDoc->ci})" : "ID: {$personaDoc->id}")) .
                    " fue APROBADO en validación masiva";
                $this->registrarAccion('documento', $documento->documento_id, 'APROBAR', $descripcionDoc);
            }

            // Cambiar estado del estudiante a 4 (Documentos aprobados / Apto para inscripción)
            $estudiante->estado_id = 4;
            $estudiante->save();

            // Registrar en bitácora - cambio de estado del estudiante
            $descripcion = "Todos los documentos del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) fueron APROBADOS. Estado cambiado a 'Apto para inscripción' (Estado_id=4). Total documentos aprobados: " . count($documentosAprobados);
            $this->registrarAccion('estudiante', $estudiante->id, 'APROBAR_DOCUMENTOS', $descripcion);

            // Enviar notificación al estudiante
            $this->notificarDocumentosCompletos($estudiante);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Todos los documentos aprobados exitosamente. Estudiante apto para inscripción',
                'data' => [
                    'estudiante' => $estudiante,
                    'documentos_aprobados' => $documentosAprobados,
                    'total_aprobados' => count($documentosAprobados)
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar documentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si todos los documentos requeridos están aprobados
     *
     * @param  int  $estudianteId
     * @return bool
     */
    private function verificarDocumentosCompletos($estudianteId)
    {
        // Documentos requeridos (excluyendo "Título de Bachiller" que es opcional)
        $tiposRequeridos = TipoDocumento::whereIn('nombre_entidad', [
            'Carnet de Identidad - Anverso',
            'Carnet de Identidad - Reverso',
            'Certificado de Nacimiento'
        ])->pluck('tipo_documento_id');

        $estudiante = Estudiante::find($estudianteId);
        if (!$estudiante) {
            return false;
        }

        // Verificar que todos los documentos requeridos estén aprobados (estado = '1')
        // Obtener la última versión de cada tipo de documento requerido
        $documentosAprobados = Documento::where('persona_id', $estudiante->id)
            ->whereIn('tipo_documento_id', $tiposRequeridos)
            ->where('estado', '1') // 1 = aprobado
            ->get()
            ->groupBy('tipo_documento_id')
            ->map(function ($docs) {
                // Obtener la última versión (mayor número de versión)
                return $docs->sortByDesc(function ($doc) {
                    return (float) $doc->version;
                })->first();
            })
            ->filter(function ($doc) {
                // Solo contar documentos que estén aprobados
                return $doc && $doc->estado == '1';
            });

        // Verificar que todos los documentos requeridos estén aprobados
        return $documentosAprobados->count() === $tiposRequeridos->count();
    }
}
