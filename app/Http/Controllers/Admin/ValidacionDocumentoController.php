<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Documento;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ValidacionDocumentoController extends Controller
{
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

            $estudiantes = Estudiante::with(['documentos.tipoDocumento', 'estado'])
                ->where('Estado_id', 3) // Estado: Documentos pendientes de validación
                ->when($search, function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('nombre', 'ILIKE', "%{$search}%")
                          ->orWhere('apellido', 'ILIKE', "%{$search}%")
                          ->orWhere('ci', 'ILIKE', "%{$search}%")
                          ->orWhere('registro_estudiante', 'ILIKE', "%{$search}%");
                    });
                })
                ->withCount(['documentos as documentos_pendientes' => function ($query) {
                    $query->where('estado', 0); // 0 = pendiente
                }])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Estudiantes con documentos pendientes obtenidos exitosamente',
                'data' => $estudiantes
            ], 200);

        } catch (\Exception $e) {
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
    public function show($estudianteId)
    {
        try {
            $estudiante = Estudiante::with([
                'documentos' => function ($query) {
                    $query->with(['tipoDocumento', 'convenio'])
                          ->orderBy('Tipo_documento_id')
                          ->orderBy('version', 'desc');
                },
                'estado'
            ])->findOrFail($estudianteId);

            // Agrupar documentos por tipo y listar versiones
            $documentosAgrupados = $estudiante->documentos->groupBy('Tipo_documento_id')->map(function ($docs) {
                return [
                    'tipo_documento' => $docs->first()->tipoDocumento,
                    'versiones' => $docs->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'nombre' => $doc->nombre_documento,
                            'version' => $doc->version,
                            'path' => $doc->path_documento,
                            'estado' => $doc->estado, // 0: pendiente, 1: aprobado, 2: rechazado
                            'observaciones' => $doc->observaciones,
                            'fecha_subida' => $doc->created_at,
                            'url_descarga' => $doc->path_documento ? Storage::url($doc->path_documento) : null
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
     * Aprobar un documento individual
     *
     * @param  int  $documentoId
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve(Request $request, $documentoId)
    {
        DB::beginTransaction();
        try {
            $documento = Documento::with(['estudiante', 'tipoDocumento'])->findOrFail($documentoId);

            // Validar que el documento esté pendiente
            if ($documento->estado != 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El documento ya ha sido procesado'
                ], 400);
            }

            // Aprobar documento
            $documento->estado = 1; // 1 = aprobado
            $documento->observaciones = $request->input('observaciones', 'Documento aprobado');
            $documento->save();

            // Registrar en bitácora
            $authUser = $request->auth_user;
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Documentos',
                'codTable' => $documento->id,
                'transaccion' => "Documento '{$documento->tipoDocumento->nombre}' del estudiante {$documento->estudiante->nombre} {$documento->estudiante->apellido} (CI: {$documento->estudiante->ci}) fue APROBADO",
                'Usuario_id' => $authUser->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Documento aprobado exitosamente',
                'data' => $documento
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
    public function reject(Request $request)
    {
        $request->validate([
            'documento_id' => 'required|exists:Documentos,id',
            'motivo' => 'required|string|min:10|max:500'
        ]);

        DB::beginTransaction();
        try {
            $documento = Documento::with(['estudiante', 'tipoDocumento'])->findOrFail($request->documento_id);

            // Validar que el documento esté pendiente
            if ($documento->estado != 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El documento ya ha sido procesado'
                ], 400);
            }

            // Rechazar documento actual
            $documento->estado = 2; // 2 = rechazado
            $documento->observaciones = $request->motivo;
            $documento->save();

            // Crear nueva versión (pendiente de subida por el estudiante)
            $nuevaVersion = $documento->version + 1;
            $nuevoDocumento = Documento::create([
                'nombre_documento' => $documento->nombre_documento,
                'version' => $nuevaVersion,
                'path_documento' => null, // El estudiante deberá subir el archivo
                'estado' => 0, // pendiente
                'observaciones' => "Versión {$nuevaVersion} - Requerida por rechazo de versión anterior. Motivo: {$request->motivo}",
                'Tipo_documento_id' => $documento->Tipo_documento_id,
                'convenio_id' => $documento->convenio_id,
                'estudiante_id' => $documento->estudiante_id
            ]);

            // Registrar en bitácora
            $authUser = $request->auth_user;
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Documentos',
                'codTable' => $documento->id,
                'transaccion' => "Documento '{$documento->tipoDocumento->nombre}' del estudiante {$documento->estudiante->nombre} {$documento->estudiante->apellido} (CI: {$documento->estudiante->ci}) fue RECHAZADO. Motivo: {$request->motivo}. Nueva versión {$nuevaVersion} creada.",
                'Usuario_id' => $authUser->id
            ]);

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
    public function approveAll(Request $request, $estudianteId)
    {
        DB::beginTransaction();
        try {
            $estudiante = Estudiante::with('documentos')->findOrFail($estudianteId);

            // Validar que el estudiante esté en estado 3 (documentos pendientes)
            if ($estudiante->Estado_id != 3) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no está en estado de documentos pendientes'
                ], 400);
            }

            // Obtener documentos pendientes (última versión de cada tipo)
            $documentosPendientes = $estudiante->documentos()
                ->where('estado', 0)
                ->get()
                ->groupBy('Tipo_documento_id')
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
                $documento->estado = 1; // aprobado
                $documento->observaciones = 'Aprobado en validación masiva';
                $documento->save();
                $documentosAprobados[] = $documento;
            }

            // Cambiar estado del estudiante a 4 (Documentos aprobados / Apto para inscripción)
            $estudiante->Estado_id = 4;
            $estudiante->save();

            // Registrar en bitácora
            $authUser = $request->auth_user;
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Estudiante',
                'codTable' => $estudiante->id,
                'transaccion' => "Todos los documentos del estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) fueron APROBADOS. Estado cambiado a 'Apto para inscripción' (Estado_id=4). Total documentos aprobados: " . count($documentosAprobados),
                'Usuario_id' => $authUser->id
            ]);

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
}
