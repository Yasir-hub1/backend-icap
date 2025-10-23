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
    public function index()
    {
        try {
            $estudiante = auth()->user();
            
            $tiposRequeridos = TipoDocumento::where('nombre_entidad', 'Estudiante')->get();
            
            $documentos = $tiposRequeridos->map(function($tipo) use ($estudiante) {
                $documento = Documento::where('estudiante_id', $estudiante->id)
                                      ->where('Tipo_documento_id', $tipo->id)
                                      ->latest()
                                      ->first();
                
                return [
                    'tipo_documento_id' => $tipo->id,
                    'nombre_requerido' => $tipo->nombre,
                    'documento_id' => $documento->id ?? null,
                    'nombre_documento' => $documento->nombre_documento ?? null,
                    'estado' => $documento->estado ?? null,
                    'observaciones' => $documento->observaciones ?? null,
                    'path' => $documento->path_documento ?? null,
                    'version' => $documento->version ?? null,
                    'fecha_subida' => $documento->created_at ?? null
                ];
            });

            return response()->json(['success' => true, 'data' => $documentos], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_documento_id' => 'required|exists:Tipo_documento,id',
            'archivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $estudiante = auth()->user();
            $tipoDoc = TipoDocumento::find($request->tipo_documento_id);
            
            $existente = Documento::where('estudiante_id', $estudiante->id)
                                  ->where('Tipo_documento_id', $request->tipo_documento_id)
                                  ->first();

            $version = $existente ? (float)$existente->version + 1.0 : 1.0;
            
            $file = $request->file('archivo');
            $path = $file->store("documentos/estudiante_{$estudiante->id}/{$tipoDoc->nombre}", 'public');

            if ($existente) {
                $existente->update([
                    'nombre_documento' => $tipoDoc->nombre,
                    'version' => $version,
                    'path_documento' => $path,
                    'estado' => 0,
                    'observaciones' => null
                ]);
                $documento = $existente;
            } else {
                $documento = Documento::create([
                    'nombre_documento' => $tipoDoc->nombre,
                    'version' => $version,
                    'path_documento' => $path,
                    'estado' => 0,
                    'Tipo_documento_id' => $request->tipo_documento_id,
                    'estudiante_id' => $estudiante->id
                ]);
            }

            Bitacora::create([
                'tabla' => 'Documentos',
                'codTable' => json_encode(['documento_id' => $documento->id, 'estudiante_id' => $estudiante->id]),
                'transaccion' => 'SUBIDA_DOCUMENTO',
                'Usuario_id' => $estudiante->id
            ]);

            $totalRequeridos = TipoDocumento::where('nombre_entidad', 'Estudiante')->count();
            $totalSubidos = Documento::where('estudiante_id', $estudiante->id)->count();

            if ($totalSubidos >= $totalRequeridos && $estudiante->Estado_id != 3) {
                $estudiante->update(['Estado_id' => 3]);
            }

            return response()->json(['success' => true, 'message' => 'Documento subido exitosamente', 'data' => $documento], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
