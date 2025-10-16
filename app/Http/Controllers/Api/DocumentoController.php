<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\Convenio;
use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    /**
     * Listar documentos con filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Documento::with([
            'tipoDocumento:id,nombre_entidad,descripcion',
            'convenio:id,numero_convenio,objeto_convenio',
            'estudiante:id,ci,nombre,apellido'
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where('nombre_documento', 'ILIKE', "%{$buscar}%")
                  ->orWhereHas('estudiante', function($q) use ($buscar) {
                      $q->where('ci', 'ILIKE', "%{$buscar}%")
                        ->orWhere('nombre', 'ILIKE', "%{$buscar}%")
                        ->orWhere('apellido', 'ILIKE', "%{$buscar}%");
                  });
        }

        if ($request->filled('tipo_documento_id')) {
            $query->where('Tipo_documento_id', $request->get('tipo_documento_id'));
        }

        if ($request->filled('convenio_id')) {
            $query->where('convenio_id', $request->get('convenio_id'));
        }

        if ($request->filled('estudiante_id')) {
            $query->where('estudiante_id', $request->get('estudiante_id'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->get('estado'));
        }

        // Ordenamiento
        $query->latest();

        // Paginación con caché
        $cacheKey = 'documentos_' . md5(serialize($request->all()));

        $documentos = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $documentos,
            'message' => 'Documentos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener documento específico
     */
    public function show(int $id): JsonResponse
    {
        $documento = Documento::with([
            'tipoDocumento:id,nombre_entidad,descripcion',
            'convenio:id,numero_convenio,objeto_convenio',
            'estudiante:id,ci,nombre,apellido,registro_estudiante'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $documento,
            'message' => 'Documento obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo documento
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'nombre_documento' => 'required|string|max:200',
            'version' => 'nullable|string|max:20',
            'path_documento' => 'required|string',
            'estado' => 'required|integer|in:0,1',
            'observaciones' => 'nullable|string',
            'Tipo_documento_id' => 'required|exists:Tipo_documento,id',
            'convenio_id' => 'required|exists:Convenio,id',
            'estudiante_id' => 'required|exists:Estudiante,id'
        ]);

        DB::beginTransaction();
        try {
            $documento = Documento::create($request->validated());

            // Limpiar caché
            Cache::forget('documentos_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $documento->load(['tipoDocumento', 'convenio', 'estudiante']),
                'message' => 'Documento creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar documento
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);

        $request->validate([
            'nombre_documento' => 'required|string|max:200',
            'version' => 'nullable|string|max:20',
            'path_documento' => 'required|string',
            'estado' => 'required|integer|in:0,1',
            'observaciones' => 'nullable|string',
            'Tipo_documento_id' => 'required|exists:Tipo_documento,id',
            'convenio_id' => 'required|exists:Convenio,id',
            'estudiante_id' => 'required|exists:Estudiante,id'
        ]);

        $documento->update($request->validated());

        // Limpiar caché
        Cache::forget('documentos_*');

        return response()->json([
            'success' => true,
            'data' => $documento->load(['tipoDocumento', 'convenio', 'estudiante']),
            'message' => 'Documento actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar documento
     */
    public function destroy(int $id): JsonResponse
    {
        $documento = Documento::findOrFail($id);

        DB::beginTransaction();
        try {
            // Eliminar archivo físico si existe
            if ($documento->path_documento && Storage::exists($documento->path_documento)) {
                Storage::delete($documento->path_documento);
            }

            $documento->delete();

            // Limpiar caché
            Cache::forget('documentos_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Documento eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir archivo de documento
     */
    public function subirArchivo(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB max
            'tipo_documento_id' => 'required|exists:Tipo_documento,id',
            'convenio_id' => 'required|exists:Convenio,id',
            'estudiante_id' => 'required|exists:Estudiante,id'
        ]);

        try {
            $archivo = $request->file('archivo');
            $nombreArchivo = time() . '_' . $archivo->getClientOriginalName();
            $ruta = $archivo->storeAs('documentos', $nombreArchivo, 'public');

            $documento = Documento::create([
                'nombre_documento' => $archivo->getClientOriginalName(),
                'version' => '1.0',
                'path_documento' => $ruta,
                'estado' => 1,
                'Tipo_documento_id' => $request->get('tipo_documento_id'),
                'convenio_id' => $request->get('convenio_id'),
                'estudiante_id' => $request->get('estudiante_id')
            ]);

            return response()->json([
                'success' => true,
                'data' => $documento->load(['tipoDocumento', 'convenio', 'estudiante']),
                'message' => 'Archivo subido exitosamente'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al subir archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descargar documento
     */
    public function descargar(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $documento = Documento::findOrFail($id);

        if (!Storage::exists($documento->path_documento)) {
            abort(404, 'Archivo no encontrado');
        }

        return Storage::download($documento->path_documento, $documento->nombre_documento);
    }

    /**
     * Obtener estadísticas de documentos
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_documentos';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Documento::count(),
                'activos' => Documento::activos()->count(),
                'por_tipo' => TipoDocumento::withCount('documentos')->get(),
                'por_convenio' => Convenio::withCount('documentos')->get(),
                'por_estudiante' => Estudiante::withCount('documentos')->get(),
                'tamaño_total' => Documento::whereNotNull('path_documento')
                    ->get()
                    ->sum(function($doc) {
                        return Storage::exists($doc->path_documento) ? Storage::size($doc->path_documento) : 0;
                    })
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }
}
