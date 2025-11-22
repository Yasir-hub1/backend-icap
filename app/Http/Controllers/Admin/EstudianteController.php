<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Documento;
use App\Models\Notificacion;
use App\Models\EstadoEstudiante;
use App\Helpers\CodigoHelper;
use App\Traits\RegistraBitacora;
use App\Traits\EnviaNotificaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EstudianteController extends Controller
{
    use RegistraBitacora, EnviaNotificaciones;
    /**
     * Alias para mantener compatibilidad con rutas RESTful
     */
    public function index(Request $request)
    {
        return $this->listar($request);
    }

    /**
     * Listar estudiantes con filtros
     */
    public function listar(Request $request)
    {
        try {
            // Estudiante hereda de Persona, no tiene relación persona
            // Los campos de Persona están directamente disponibles en Estudiante
            $query = Estudiante::with(['usuario', 'estadoEstudiante']);

            // Filtros
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('ci', 'like', "%{$search}%")
                      ->orWhere('nombre', 'like', "%{$search}%")
                      ->orWhere('apellido', 'like', "%{$search}%")
                      ->orWhere('registro_estudiante', 'like', "%{$search}%");
                });
            }

            // Filtro por estado
            if ($request->has('estado') && $request->estado) {
                $query->where('Estado_id', $request->estado);
            }

            if ($request->has('provincia') && $request->provincia) {
                $query->where('provincia', $request->provincia);
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $estudiantes = $query->paginate($perPage);

            // Agregar información adicional
            $estudiantes->getCollection()->transform(function ($estudiante) {
                return [
                    'id' => $estudiante->id, // ID real del estudiante (persona_id), usado en inscripcion.estudiante_id
                    'persona_id' => $estudiante->id, // Alias para claridad
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'celular' => $estudiante->celular,
                    'provincia' => $estudiante->provincia,
                    'estado' => $estudiante->estadoEstudiante ? $estudiante->estadoEstudiante->nombre_estado : 'Sin estado',
                    'estado_id' => $estudiante->Estado_id ?? 1,
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'fecha_inscripcion' => $estudiante->created_at ? $estudiante->created_at->format('d M Y') : 'N/A',
                    'fotografia' => $estudiante->fotografia || null,
                    'email' => $estudiante->usuario ? $estudiante->usuario->email : 'N/A',
                    'activo' => ($estudiante->Estado_id ?? 1) == 2, // Activo si Estado_id es 2
                    'documentos_completos' => $this->verificarDocumentosCompletos($estudiante->registro_estudiante),
                    'programa_actual' => $this->obtenerProgramaActual($estudiante->registro_estudiante)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $estudiantes,
                'message' => 'Estudiantes obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar estudiantes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estudiantes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alias para estadísticas
     */
    public function stats()
    {
        return $this->estadisticas();
    }

    /**
     * Obtener estadísticas de estudiantes
     */
    public function estadisticas()
    {
        try {
            $totalEstudiantes = Estudiante::count();
            // Since there's no estado_id column, we'll use basic counts
            $estudiantesActivos = $totalEstudiantes; // All students are considered active for now
            $estudiantesNuevos = 0; // No created_at column to track new students
            $estudiantesGraduados = 0; // No graduation status yet

            return response()->json([
                'success' => true,
                'data' => [
                    'total_estudiantes' => $totalEstudiantes,
                    'estudiantes_activos' => $estudiantesActivos,
                    'estudiantes_nuevos' => $estudiantesNuevos,
                    'estudiantes_graduados' => $estudiantesGraduados
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Alias para obtener un estudiante
     */
    public function show($id)
    {
        return $this->obtener($id);
    }

    /**
     * Obtener un estudiante específico
     */
    public function obtener($id)
    {
        try {
            // Cargar estudiante con todas sus relaciones
            // Estudiante hereda de Persona, los documentos se obtienen directamente
            $estudiante = Estudiante::with([
                'usuario.rol',
                'inscripciones.programa',
                'documentos.tipoDocumento', // documentos está heredado de Persona
                'estadoEstudiante'
            ])
                ->where('registro_estudiante', $id)
                ->orWhere('id', $id) // También buscar por id
                ->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            // Obtener programa actual de la inscripción más reciente
            $inscripcionActiva = $estudiante->inscripciones()
                ->with('programa')
                ->latest('fecha')
                ->first();

            $data = [
                'id' => $estudiante->registro_estudiante,
                'ci' => $estudiante->ci,
                'nombre' => $estudiante->nombre,
                'apellido' => $estudiante->apellido,
                'celular' => $estudiante->celular,
                'sexo' => $estudiante->sexo,
                'fecha_nacimiento' => $estudiante->fecha_nacimiento
                    ? Carbon::parse($estudiante->fecha_nacimiento)->format('Y-m-d')
                    : null,
                'direccion' => $estudiante->direccion,
                'provincia' => $estudiante->provincia,
                'fotografia' => $estudiante->fotografia,
                'estado' => $estudiante->estadoEstudiante ? $estudiante->estadoEstudiante->nombre_estado : 'Sin estado',
                'estado_id' => $estudiante->Estado_id ?? 1,
                'email' => $estudiante->usuario ? $estudiante->usuario->email : 'N/A',
                'fecha_inscripcion' => $estudiante->created_at ? $estudiante->created_at->format('d M Y') : 'N/A',
                'activo' => ($estudiante->Estado_id ?? 1) == 2,
                'documentos_completos' => $this->verificarDocumentosCompletos($estudiante->registro_estudiante),
                'programa_actual' => $inscripcionActiva && $inscripcionActiva->programa
                    ? $inscripcionActiva->programa->nombre
                    : 'Sin programa',
                'persona_id' => $estudiante->id, // Estudiante hereda de Persona, usa el mismo id
                'registro_estudiante' => $estudiante->registro_estudiante
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estudiante: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo estudiante
     */
    public function crear(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ci' => 'required|string|max:20|unique:estudiante,ci',
                'nombre' => 'required|string|max:100',
                'apellido' => 'required|string|max:100',
                'celular' => 'required|string|max:20',
                'sexo' => 'sometimes|string|in:M,F',
                'fecha_nacimiento' => 'sometimes|date',
                'direccion' => 'sometimes|string|max:255',
                'provincia' => 'sometimes|string|max:50',
                'estado_id' => 'sometimes|integer|exists:estado_estudiante,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generar código único de 5 dígitos para el estudiante
            $registroEstudiante = CodigoHelper::generarCodigoEstudiante();

            // Preparar datos para crear
            $datosCrear = [
                'ci' => trim($request->ci),
                'nombre' => trim($request->nombre),
                'apellido' => trim($request->apellido),
                'celular' => trim($request->celular),
                'sexo' => $request->sexo ?? null,
                'fecha_nacimiento' => $request->fecha_nacimiento ?? null,
                'direccion' => $request->direccion ? trim($request->direccion) : null,
                'provincia' => $request->provincia ? trim($request->provincia) : null,
                'registro_estudiante' => $registroEstudiante,
            ];

            // Manejar estado_id - verificar si la columna existe
            if ($request->has('estado_id')) {
                try {
                    $columnExists = DB::select("
                        SELECT column_name
                        FROM information_schema.columns
                        WHERE table_name = 'estudiante'
                        AND column_name IN ('Estado_id', 'estado_id')
                    ");

                    if (!empty($columnExists)) {
                        $columnName = $columnExists[0]->column_name;
                        $datosCrear[$columnName] = $request->estado_id;
                    }
                } catch (\Exception $e) {
                    Log::error('Error verificando columna estado_id: ' . $e->getMessage());
                }
            }

            // Crear estudiante (que hereda de Persona)
            $estudiante = Estudiante::create($datosCrear);

            // Registrar en bitácora
            $this->registrarCreacion('estudiante', $estudiante->id, "Estudiante: {$estudiante->nombre} {$estudiante->apellido} - CI: {$estudiante->ci}");

            Log::info('Estudiante creado desde admin', [
                'id' => $estudiante->id,
                'registro_estudiante' => $estudiante->registro_estudiante,
                'ci' => $estudiante->ci
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estudiante creado exitosamente',
                'data' => [
                    'id' => $estudiante->registro_estudiante,
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'registro_estudiante' => $estudiante->registro_estudiante,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear estudiante: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar datos de un estudiante
     */
    public function actualizar(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|string|max:100',
                'apellido' => 'sometimes|string|max:100',
                'celular' => 'sometimes|string|max:20',
                'direccion' => 'sometimes|string|max:255',
                'provincia' => 'sometimes|string|max:50',
                'fecha_nacimiento' => 'sometimes|date',
                'Estado_id' => 'sometimes|integer|exists:estado_estudiante,estado_id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $estudiante = Estudiante::where('registro_estudiante', $id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            // Preparar datos para actualizar
            $datosActualizar = [];

            // Solo incluir campos que están presentes en la request
            if ($request->has('nombre')) $datosActualizar['nombre'] = $request->nombre;
            if ($request->has('apellido')) $datosActualizar['apellido'] = $request->apellido;
            if ($request->has('celular')) $datosActualizar['celular'] = $request->celular;
            if ($request->has('direccion')) $datosActualizar['direccion'] = $request->direccion;
            if ($request->has('provincia')) $datosActualizar['provincia'] = $request->provincia;
            if ($request->has('fecha_nacimiento')) $datosActualizar['fecha_nacimiento'] = $request->fecha_nacimiento;

            // Manejar Estado_id - verificar si la columna existe antes de actualizar
            if ($request->has('Estado_id')) {
                try {
                    // Verificar si la columna existe en la tabla
                    $columnExists = DB::select("
                        SELECT column_name
                        FROM information_schema.columns
                        WHERE table_name = 'estudiante'
                        AND column_name IN ('Estado_id', 'estado_id')
                    ");

                    if (!empty($columnExists)) {
                        $columnName = $columnExists[0]->column_name;
                        $datosActualizar[$columnName] = $request->Estado_id;
                    } else {
                        Log::warning('Columna Estado_id no existe en tabla estudiante. Ejecuta el script SQL add_estado_id_to_estudiante.sql');
                    }
                } catch (\Exception $e) {
                    Log::error('Error verificando columna Estado_id: ' . $e->getMessage());
                }
            }

            if (!empty($datosActualizar)) {
                $estudiante->update($datosActualizar);
                
                // Registrar en bitácora
                $this->registrarEdicion('estudiante', $estudiante->id, "Estudiante: {$estudiante->nombre} {$estudiante->apellido} - CI: {$estudiante->ci}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Estudiante actualizado exitosamente',
                'data' => $estudiante
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar estudiante: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener documentos de un estudiante
     */
    public function obtenerDocumentos($id)
    {
        try {
            $estudiante = Estudiante::where('registro_estudiante', $id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            // Obtener documentos - Estudiante hereda de Persona, usa el mismo id
            $documentos = Documento::where('persona_id', $estudiante->id)
                ->with('tipoDocumento')
                ->orderBy('documento_id', 'desc')
                ->get();

            $documentosTransformados = $documentos->map(function ($doc) {
                // Mapear estado numérico a texto
                $estadoTexto = 'pendiente';
                if ($doc->estado === 1 || $doc->estado === 'aprobado') {
                    $estadoTexto = 'aprobado';
                } elseif ($doc->estado === 2 || $doc->estado === 'rechazado') {
                    $estadoTexto = 'rechazado';
                } elseif ($doc->estado === 0 || $doc->estado === 'pendiente') {
                    $estadoTexto = 'pendiente';
                }

                return [
                    'id' => $doc->documento_id,
                    'nombre' => $doc->nombre_documento,
                    'tipo' => $doc->tipoDocumento ? $doc->tipoDocumento->nombre_entidad : 'Sin tipo',
                    'version' => $doc->version,
                    'estado' => $estadoTexto,
                    'path' => $doc->path_documento,
                    'observaciones' => $doc->observaciones,
                    'fecha_subida' => $doc->created_at ? $doc->created_at->format('d M Y H:i') : 'N/A',
                    'fecha_subida_formatted' => $doc->created_at ? $doc->created_at->format('d M Y H:i') : 'N/A'
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $documentosTransformados
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener documentos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener documentos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activar estudiante
     */
    public function activar($id)
    {
        try {
            $estudiante = Estudiante::where('registro_estudiante', $id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            // Verificar que tenga documentos completos
            if (!$this->verificarDocumentosCompletos($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estudiante no tiene todos los documentos requeridos'
                ], 400);
            }

            // Cambiar estado a activo (asumiendo que 2 es "Activo")
            $estudiante->update(['Estado_id' => 2]);

            // Registrar en bitácora
            $this->registrarAccion('estudiante', $estudiante->id, 'ACTIVAR', "Estudiante: {$estudiante->nombre} {$estudiante->apellido} - CI: {$estudiante->ci}");

            // Enviar notificación al estudiante
            $this->notificarEstudianteActivado($estudiante);

            return response()->json([
                'success' => true,
                'message' => 'Estudiante activado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al activar estudiante: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al activar estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desactivar estudiante
     */
    public function desactivar(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'motivo' => 'required|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $estudiante = Estudiante::where('registro_estudiante', $id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            // Cambiar estado a inactivo (asumiendo que 3 es "Inactivo")
            $estudiante->update(['Estado_id' => 3]);

            // Crear notificación para el estudiante
            $this->crearNotificacionEstudiante($estudiante, 'Perfil Desactivado',
                'Tu perfil ha sido desactivado. Motivo: ' . $request->motivo);

            return response()->json([
                'success' => true,
                'message' => 'Estudiante desactivado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al desactivar estudiante: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al desactivar estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si un estudiante tiene documentos completos
     */
    private function verificarDocumentosCompletos($estudianteId)
    {
        // Aquí puedes definir qué documentos son requeridos
        $documentosRequeridos = ['CI', 'Certificado de Nacimiento', 'Fotografía'];

        $estudiante = Estudiante::where('registro_estudiante', $estudianteId)->first();
        if (!$estudiante) return false;

        // Estudiante hereda de Persona, usa el mismo id
        $documentosSubidos = Documento::where('persona_id', $estudiante->id)
            ->where('estado', 'aprobado')
            ->count();

        // Por ahora, consideramos que tiene documentos completos si tiene al menos 3 documentos aprobados
        return $documentosSubidos >= 3;
    }

    /**
     * Crear notificación para estudiante
     */
    private function crearNotificacionEstudiante($estudiante, $titulo, $mensaje)
    {
        try {
            Notificacion::create([
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'tipo' => 'sistema',
                'leida' => false,
                'usuario_id' => $estudiante->usuario ? $estudiante->usuario->usuario_id : null,
                'usuario_tipo' => 'student'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear notificación: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar un estudiante
     */
    public function eliminar($id)
    {
        try {
            $estudiante = Estudiante::where('registro_estudiante', $id)->first();

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'Estudiante no encontrado'
                ], 404);
            }

            // Verificar si el estudiante tiene inscripciones activas
            $tieneInscripciones = DB::table('inscripcion')
                ->where('estudiante_id', $estudiante->id)
                ->exists();

            if ($tieneInscripciones) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el estudiante porque tiene inscripciones asociadas'
                ], 422);
            }

            // Verificar si el estudiante está en grupos
            $columnExists = DB::selectOne(
                "SELECT column_name FROM information_schema.columns WHERE table_name = 'grupo_estudiante' AND column_name = 'estudiante_id'"
            );

            $tieneGrupos = false;
            if ($columnExists) {
                $tieneGrupos = DB::table('grupo_estudiante')
                    ->where('estudiante_id', $estudiante->id)
                    ->exists();
            } else {
                $tieneGrupos = DB::table('grupo_estudiante')
                    ->where('estudiante_registro', $estudiante->registro_estudiante)
                    ->exists();
            }

            if ($tieneGrupos) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el estudiante porque está asignado a grupos'
                ], 422);
            }

            // Guardar datos para bitácora antes de eliminar
            $nombreCompleto = "{$estudiante->nombre} {$estudiante->apellido}";
            $ci = $estudiante->ci;
            $estudianteId = $estudiante->id;

            // Eliminar usuario asociado si existe
            if ($estudiante->usuario) {
                $estudiante->usuario->delete();
            }

            // Eliminar el estudiante (esto también eliminará los registros en persona por herencia)
            $estudiante->delete();

            // Registrar en bitácora
            $this->registrarEliminacion('estudiante', $estudianteId, "Estudiante: {$nombreCompleto} - CI: {$ci}");

            Log::info('Estudiante eliminado', [
                'registro_estudiante' => $id,
                'ci' => $ci
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estudiante eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar estudiante: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estados de estudiante disponibles
     */
    public function obtenerEstados()
    {
        try {
            $estados = \App\Models\EstadoEstudiante::select('estado_id as id', 'nombre_estado as nombre')
                ->orderBy('nombre_estado')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $estados
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener programa actual del estudiante
     */
    private function obtenerProgramaActual($estudianteId)
    {
        try {
            $estudiante = Estudiante::where('registro_estudiante', $estudianteId)->first();
            if (!$estudiante) return 'Sin programa';

            // Buscar inscripción más reciente (la tabla no tiene columna estado)
            $inscripcion = \App\Models\Inscripcion::where('registro_estudiante', $estudianteId)
                ->with('programa')
                ->latest('fecha')
                ->first();

            return $inscripcion && $inscripcion->programa ? $inscripcion->programa->nombre : 'Sin programa';
        } catch (\Exception $e) {
            Log::error('Error al obtener programa actual: ' . $e->getMessage());
            return 'Sin programa';
        }
    }
}
