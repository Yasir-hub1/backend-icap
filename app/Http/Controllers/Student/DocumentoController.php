<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentoController extends Controller
{
    public function listar(Request $request)
    {
        return $this->index($request);
    }

    public function index(Request $request)
    {
        try {
            $estudiante = $request->auth_user;

            $tiposRequeridos = TipoDocumento::where('nombre_entidad', 'Estudiante')->get();

            $documentos = $tiposRequeridos->map(function($tipo) use ($estudiante) {
                $documento = Documento::where('persona_id', $estudiante->id)
                                      ->where('tipo_documento_id', $tipo->tipo_documento_id)
                                      ->latest()
                                      ->first();

                return [
                    'tipo_documento_id' => $tipo->tipo_documento_id,
                    'nombre_entidad' => $tipo->nombre_entidad,
                    'documento_id' => $documento->documento_id ?? null,
                    'nombre_documento' => $documento->nombre_documento ?? null,
                    'estado' => $documento->estado ?? null,
                    'observaciones' => $documento->observaciones ?? null,
                    'path' => $documento->path_documento ?? null,
                    'version' => $documento->version ?? null,
                    'fecha_subida' => $documento->created_at ?? null,
                    'url_descarga' => $documento && $documento->path_documento ? Storage::url($documento->path_documento) : null
                ];
            });

            return response()->json(['success' => true, 'data' => $documentos], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function subir(Request $request)
    {
        return $this->upload($request);
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_documento_id' => 'required|exists:tipo_documento,tipo_documento_id',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240' // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $estudiante = $request->auth_user;
            $tipoDoc = TipoDocumento::findOrFail($request->tipo_documento_id);

            // Buscar documento existente (última versión)
            $existente = Documento::where('persona_id', $estudiante->id)
                                  ->where('tipo_documento_id', $request->tipo_documento_id)
                                  ->orderBy('version', 'desc')
                                  ->first();

            $version = $existente ? (float)$existente->version + 1.0 : 1.0;

            $file = $request->file('archivo');
            $nombreArchivo = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs("documentos/estudiante_{$estudiante->registro_estudiante}", $nombreArchivo, 'public');

            // Si existe un documento pendiente o rechazado, actualizarlo
            // Si está aprobado, crear nueva versión
            if ($existente && ($existente->estado == '0' || $existente->estado == '2')) {
                // Eliminar archivo anterior si existe
                if ($existente->path_documento && Storage::disk('public')->exists($existente->path_documento)) {
                    Storage::disk('public')->delete($existente->path_documento);
                }

                $existente->update([
                    'nombre_documento' => $tipoDoc->nombre_entidad,
                    'version' => (string)$version,
                    'path_documento' => $path,
                    'estado' => '0', // pendiente
                    'observaciones' => null
                ]);
                $documento = $existente;
            } else {
                // Crear nuevo documento
                $documento = Documento::create([
                    'nombre_documento' => $tipoDoc->nombre_entidad,
                    'version' => (string)$version,
                    'path_documento' => $path,
                    'estado' => '0', // pendiente
                    'tipo_documento_id' => $request->tipo_documento_id,
                    'persona_id' => $estudiante->id
                ]);
            }

            // Registrar en bitácora
            $usuario = $estudiante->usuario;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Documento',
                    'codTabla' => $documento->documento_id,
                    'transaccion' => "Estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) subió documento {$tipoDoc->nombre_entidad}. Versión: {$version}",
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            // Verificar si todos los documentos requeridos están subidos
            $totalRequeridos = TipoDocumento::where('nombre_entidad', 'Estudiante')->count();
            $totalSubidos = Documento::where('persona_id', $estudiante->id)
                                     ->whereIn('tipo_documento_id', TipoDocumento::where('nombre_entidad', 'Estudiante')->pluck('tipo_documento_id'))
                                     ->distinct('tipo_documento_id')
                                     ->count('tipo_documento_id');

            // Cambiar estado a 3 (Documentos pendientes de validación) si todos están subidos
            if ($totalSubidos >= $totalRequeridos && $estudiante->Estado_id != 3) {
                $estudiante->update(['Estado_id' => 3]);
            }

            return response()->json(['success' => true, 'message' => 'Documento subido exitosamente', 'data' => $documento], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
