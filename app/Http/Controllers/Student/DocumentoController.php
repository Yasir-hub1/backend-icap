<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Documento;
use App\Models\TipoDocumento;
use App\Models\Bitacora;
use App\Traits\EnviaNotificaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentoController extends Controller
{
    use EnviaNotificaciones;
    public function listar(Request $request)
    {
        return $this->index($request);
    }

    public function index(Request $request)
    {
        try {
            $estudiante = $request->auth_user;

            // Obtener todos los tipos de documento para estudiantes (incluyendo el opcional)
            $tiposDocumento = TipoDocumento::whereIn('nombre_entidad', [
                'Carnet de Identidad - Anverso',
                'Carnet de Identidad - Reverso',
                'Certificado de Nacimiento',
                'Título de Bachiller'
            ])->get();

            $documentos = $tiposDocumento->map(function($tipo) use ($estudiante) {
                // Obtener la última versión APROBADA de este tipo de documento
                // Si no hay aprobada, obtener la última versión (pendiente o rechazada) para que el estudiante sepa que debe subirla
                $documentoAprobado = Documento::where('persona_id', $estudiante->id)
                    ->where('tipo_documento_id', $tipo->tipo_documento_id)
                    ->where('estado', '1') // Solo documentos aprobados
                    ->orderBy('version', 'desc')
                    ->first();

                // Si no hay documento aprobado, obtener la última versión (para mostrar estado pendiente/rechazado)
                if (!$documentoAprobado) {
                    $documentoAprobado = Documento::where('persona_id', $estudiante->id)
                        ->where('tipo_documento_id', $tipo->tipo_documento_id)
                        ->orderBy('version', 'desc')
                        ->first();
                }

                return [
                    'tipo_documento_id' => $tipo->tipo_documento_id,
                    'nombre_entidad' => $tipo->nombre_entidad,
                    'nombre' => $tipo->nombre_entidad,
                    'documento_id' => $documentoAprobado->documento_id ?? null,
                    'nombre_documento' => $documentoAprobado->nombre_documento ?? null,
                    'estado' => $documentoAprobado->estado ?? null,
                    'observaciones' => $documentoAprobado->observaciones ?? null,
                    'path' => $documentoAprobado->path_documento ?? null,
                    'version' => $documentoAprobado->version ?? null,
                    'fecha_subida' => $documentoAprobado->created_at ?? null,
                    'url_descarga' => $documentoAprobado && $documentoAprobado->path_documento ? Storage::url($documentoAprobado->path_documento) : null
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

            // SEGÚN EL FLUJO:
            // 1. estado_id = 1 (Pre-registrado): Estudiante se registra, no ha subido documentos
            // 2. estado_id = 2 (Documentos incompletos): Estudiante ha subido algunos documentos pero no los 3 requeridos
            // 3. estado_id = 3 (En revisión): Estudiante ha subido los 3 documentos requeridos → Se envía a revisión
            // 4. estado_id = 4 (Validado - Activo): Todos los documentos aprobados
            // 5. estado_id = 5 (Rechazado): Documentos rechazados, debe volver a subirlos

            // Verificar si todos los documentos requeridos están subidos
            // Documentos requeridos (excluyendo "Título de Bachiller" que es opcional)
            $tiposRequeridos = TipoDocumento::whereIn('nombre_entidad', [
                'Carnet de Identidad - Anverso',
                'Carnet de Identidad - Reverso',
                'Certificado de Nacimiento'
            ])->pluck('tipo_documento_id');

            $totalRequeridos = $tiposRequeridos->count(); // Debe ser 3
            $totalSubidos = Documento::where('persona_id', $estudiante->id)
                                     ->whereIn('tipo_documento_id', $tiposRequeridos)
                                     ->distinct('tipo_documento_id')
                                     ->count('tipo_documento_id');

            // LÓGICA DE CAMBIO DE ESTADO SEGÚN EL FLUJO:
            // 1. estado_id = 1 (Pre-registrado): Estudiante se registra, no ha subido documentos
            // 2. estado_id = 2 (Documentos incompletos): Estudiante ha subido algunos documentos pero no los 3 requeridos
            // 3. estado_id = 3 (En revisión): Estudiante ha subido los 3 documentos requeridos → Se envía a revisión
            // 4. estado_id = 4 (Validado - Activo): Todos los documentos aprobados
            // 5. estado_id = 5 (Rechazado): Documentos rechazados, debe volver a subirlos

            if ($totalSubidos >= $totalRequeridos && $totalRequeridos >= 3) {
                // Si se han subido los 3 documentos requeridos → estado_id = 3 (En revisión)
                // Esto envía automáticamente los documentos a revisión
                // Aplicar si está en estado 1, 2 o 5 (no cambiar si ya está en 3 o 4)
                if (in_array($estudiante->estado_id, [1, 2, 5])) {
                    $estudiante->update(['estado_id' => 3]);

                    // NOTIFICAR A TODOS LOS ADMINISTRADORES que hay documentos pendientes de revisión
                    // Esto es crítico para que los admins sepan que deben revisar los documentos
                    $this->notificarTodosAdmins(
                        'Documentos Pendientes de Revisión',
                        "El estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}, Registro: {$estudiante->registro_estudiante}) ha completado la carga de sus 3 documentos requeridos y están pendientes de validación. Por favor, revisa los documentos en el panel de validación.",
                        'documento',
                        [
                            'estudiante_id' => $estudiante->id,
                            'registro_estudiante' => $estudiante->registro_estudiante,
                            'estudiante_nombre' => "{$estudiante->nombre} {$estudiante->apellido}",
                            'estudiante_ci' => $estudiante->ci,
                            'cantidad_documentos' => $totalSubidos,
                            'accion' => 'revisar_documentos',
                            'url' => "/admin/validacion-documentos"
                        ]
                    );
                }
            } elseif ($totalSubidos > 0 && $totalSubidos < $totalRequeridos) {
                // Si se han subido algunos documentos pero no todos → estado_id = 2 (Documentos incompletos)
                // Solo cambiar si está en estado 1 (Pre-registrado) o 5 (Rechazado)
                // No cambiar si ya está en estado 2, 3 o 4
                if (in_array($estudiante->estado_id, [1, 5])) {
                    $estudiante->update(['estado_id' => 2]);
                }
            }
            // Si totalSubidos == 0, mantener estado_id = 1 (Pre-registrado) o el estado actual

            return response()->json(['success' => true, 'message' => 'Documento subido exitosamente', 'data' => $documento], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
