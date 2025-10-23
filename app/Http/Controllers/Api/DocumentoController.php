<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\TipoDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    /**
     * Listar todos los documentos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Documento::with(['tipoDocumento', 'estudiante']);

            // Filtros
            if ($request->has('estudiante_id')) {
                $query->where('estudiante_id', $request->estudiante_id);
            }

            if ($request->has('tipo_documento_id')) {
                $query->where('tipo_documento_id', $request->tipo_documento_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            $documentos = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $documentos,
                'message' => 'Documentos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un documento específico
     */
    public function show($id): JsonResponse
    {
        try {
            $documento = Documento::with(['tipoDocumento', 'estudiante'])->find($id);

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $documento,
                'message' => 'Documento obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir un nuevo documento
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'estudiante_id' => 'required|exists:estudiantes,id',
                'tipo_documento_id' => 'required|exists:tipos_documento,id',
                'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB max
                'descripcion' => 'nullable|string|max:500'
            ]);

            $archivo = $request->file('archivo');
            $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
            $ruta = $archivo->storeAs('documentos', $nombreArchivo, 'public');

            $documento = Documento::create([
                'estudiante_id' => $request->estudiante_id,
                'tipo_documento_id' => $request->tipo_documento_id,
                'nombre_archivo' => $nombreArchivo,
                'ruta_archivo' => $ruta,
                'tamaño_archivo' => $archivo->getSize(),
                'tipo_mime' => $archivo->getMimeType(),
                'descripcion' => $request->descripcion,
                'estado' => 'pendiente'
            ]);

            $documento->load(['tipoDocumento', 'estudiante']);

            return response()->json([
                'success' => true,
                'data' => $documento,
                'message' => 'Documento subido exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un documento
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $documento = Documento::find($id);

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            $request->validate([
                'descripcion' => 'nullable|string|max:500',
                'estado' => 'sometimes|in:pendiente,aprobado,rechazado'
            ]);

            $documento->update($request->only(['descripcion', 'estado']));
            $documento->load(['tipoDocumento', 'estudiante']);

            return response()->json([
                'success' => true,
                'data' => $documento,
                'message' => 'Documento actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un documento
     */
    public function destroy($id): JsonResponse
    {
        try {
            $documento = Documento::find($id);

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            // Eliminar archivo del storage
            if (Storage::disk('public')->exists($documento->ruta_archivo)) {
                Storage::disk('public')->delete($documento->ruta_archivo);
            }

            $documento->delete();

            return response()->json([
                'success' => true,
                'message' => 'Documento eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar un documento
     */
    public function approve($id): JsonResponse
    {
        try {
            $documento = Documento::find($id);

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            $documento->update(['estado' => 'aprobado']);
            $documento->load(['tipoDocumento', 'estudiante']);

            return response()->json([
                'success' => true,
                'data' => $documento,
                'message' => 'Documento aprobado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rechazar un documento
     */
    public function reject($id): JsonResponse
    {
        try {
            $documento = Documento::find($id);

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            $documento->update(['estado' => 'rechazado']);
            $documento->load(['tipoDocumento', 'estudiante']);

            return response()->json([
                'success' => true,
                'data' => $documento,
                'message' => 'Documento rechazado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar un documento
     */
    public function download($id): JsonResponse
    {
        try {
            $documento = Documento::find($id);

            if (!$documento) {
                return response()->json([
                    'success' => false,
                    'message' => 'Documento no encontrado'
                ], 404);
            }

            if (!Storage::disk('public')->exists($documento->ruta_archivo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo no encontrado en el servidor'
                ], 404);
            }

            $rutaCompleta = Storage::disk('public')->path($documento->ruta_archivo);

            return response()->download($rutaCompleta, $documento->nombre_archivo);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al descargar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener documentos por estudiante
     */
    public function getByEstudiante($estudianteId): JsonResponse
    {
        try {
            $documentos = Documento::with(['tipoDocumento'])
                ->where('estudiante_id', $estudianteId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documentos,
                'message' => 'Documentos del estudiante obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentos del estudiante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener documentos pendientes
     */
    public function getPending(): JsonResponse
    {
        try {
            $documentos = Documento::with(['tipoDocumento', 'estudiante'])
                ->where('estado', 'pendiente')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $documentos,
                'message' => 'Documentos pendientes obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentos pendientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipos de documento
     */
    public function getTipos(): JsonResponse
    {
        try {
            $tipos = TipoDocumento::where('activo', true)->get();

            return response()->json([
                'success' => true,
                'data' => $tipos,
                'message' => 'Tipos de documento obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tipos de documento: ' . $e->getMessage()
            ], 500);
        }
    }
}
