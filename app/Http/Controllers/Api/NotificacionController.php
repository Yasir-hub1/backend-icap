<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class NotificacionController extends Controller
{
    /**
     * Helper para obtener usuarioId y usuarioTipo desde el request
     */
    private function obtenerUsuarioInfo(Request $request)
    {
        try {
            $usuario = $request->auth_user;

            if (!$usuario) {
                Log::warning('NotificacionController: No se encontró auth_user en request');
                return [null, null];
            }

            $usuarioId = null;
            $usuarioTipo = null;

            if ($usuario instanceof \App\Models\Estudiante) {
                // Estudiante: usar registro_estudiante como identificador
                $usuarioId = $usuario->registro_estudiante;
                $usuarioTipo = 'student';
            } elseif ($usuario instanceof \App\Models\Docente) {
                // Docente: usar id del docente
                $usuarioId = $usuario->id;
                $usuarioTipo = 'teacher';
            } elseif ($usuario instanceof \App\Models\Usuario) {
                // Usuario (Admin o Docente con cuenta Usuario)
                if (!$usuario->relationLoaded('rol')) {
                    $usuario->load('rol');
                }

                if ($usuario->rol) {
                    $rolNombre = strtoupper($usuario->rol->nombre_rol);
                    if ($rolNombre === 'ADMIN') {
                        // ADMIN: usar usuario_id del modelo Usuario
                        $usuarioTipo = 'admin';
                        $usuarioId = $usuario->usuario_id;
                    } elseif ($rolNombre === 'DOCENTE') {
                        // DOCENTE: buscar el docente asociado por persona_id
                        $usuarioTipo = 'teacher';
                        $docente = \App\Models\Docente::where('id', $usuario->persona_id)->first();
                        if ($docente) {
                            $usuarioId = $docente->id;
                        } else {
                            // Si no encuentra docente, usar usuario_id como fallback
                            $usuarioId = $usuario->usuario_id;
                        }
                    } else {
                        // Otro rol: tratar como admin por defecto
                        $usuarioTipo = 'admin';
                        $usuarioId = $usuario->usuario_id;
                    }
                } else {
                    // Si no tiene rol, asumir admin
                    $usuarioTipo = 'admin';
                    $usuarioId = $usuario->usuario_id;
                }
            }

            Log::info('NotificacionController: Usuario identificado', [
                'usuario_id' => $usuarioId,
                'usuario_tipo' => $usuarioTipo,
                'usuario_class' => get_class($usuario)
            ]);

            return [$usuarioId, $usuarioTipo];
        } catch (\Exception $e) {
            Log::error('NotificacionController: Error en obtenerUsuarioInfo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [null, null];
        }
    }

    /**
     * Obtener notificaciones del usuario actual
     */
    public function index(Request $request)
    {
        try {
            [$usuarioId, $usuarioTipo] = $this->obtenerUsuarioInfo($request);

            if (!$usuarioId || !$usuarioTipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado o tipo no válido'
                ], 401);
            }

            $query = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                ->orderBy('fecha_envio', 'desc');

            // Filtros opcionales
            // Manejar parámetro 'leida' (true/false) o 'no_leidas' (boolean)
            if ($request->has('leida')) {
                $leida = filter_var($request->leida, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($leida !== null) {
                    if ($leida) {
                        $query->where('leida', true);
                    } else {
                        $query->noLeidas();
                    }
                }
            } elseif ($request->has('no_leidas') && $request->no_leidas) {
                $query->noLeidas();
            }

            if ($request->has('tipo')) {
                $query->porTipo($request->tipo);
            }

            if ($request->has('dias')) {
                $query->recientes($request->dias);
            }

            $notificaciones = $query->paginate($request->get('per_page', 20));

            // Transformar las notificaciones para incluir campos formateados
            $notificaciones->getCollection()->transform(function ($notificacion) {
                // Asegurar que las fechas estén en la zona horaria correcta
                $fechaEnvio = $notificacion->fecha_envio ? 
                    \Carbon\Carbon::parse($notificacion->fecha_envio)->setTimezone(config('app.timezone', 'America/La_Paz')) : null;
                $fechaLectura = $notificacion->fecha_lectura ? 
                    \Carbon\Carbon::parse($notificacion->fecha_lectura)->setTimezone(config('app.timezone', 'America/La_Paz')) : null;
                
                return [
                    'id' => $notificacion->id,
                    'titulo' => $notificacion->titulo,
                    'mensaje' => $notificacion->mensaje,
                    'tipo' => $notificacion->tipo,
                    'leida' => $notificacion->leida,
                    'usuario_id' => $notificacion->usuario_id,
                    'usuario_tipo' => $notificacion->usuario_tipo,
                    'datos_adicionales' => $notificacion->datos_adicionales,
                    'fecha_envio' => $fechaEnvio ? $fechaEnvio->toIso8601String() : null,
                    'fecha_lectura' => $fechaLectura ? $fechaLectura->toIso8601String() : null,
                    // Campos para compatibilidad con frontend
                    'created_at' => $fechaEnvio ? $fechaEnvio->toIso8601String() : null,
                    'leida_at' => $fechaLectura ? $fechaLectura->toIso8601String() : null,
                    // Fecha formateada para mostrar
                    'fecha_formateada' => $fechaEnvio ? $fechaEnvio->format('d/m/Y H:i:s') : null,
                    'fecha_lectura_formateada' => $fechaLectura ? $fechaLectura->format('d/m/Y H:i:s') : null
                ];
            });

            // Obtener contador de no leídas
            $noLeidas = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                   ->noLeidas()
                                   ->count();

            return response()->json([
                'success' => true,
                'data' => $notificaciones,
                'no_leidas' => $noLeidas
            ]);

        } catch (\Exception $e) {
            Log::error('Error en NotificacionController@index', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'usuario_id' => $usuarioId ?? null,
                'usuario_tipo' => $usuarioTipo ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notificaciones',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtener contador de notificaciones no leídas
     */
    public function contador(Request $request)
    {
        try {
            [$usuarioId, $usuarioTipo] = $this->obtenerUsuarioInfo($request);

            if (!$usuarioId || !$usuarioTipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado o tipo no válido'
                ], 401);
            }

            $contador = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                   ->noLeidas()
                                   ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $contador,
                    'contador' => $contador // Alias para compatibilidad
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en NotificacionController@contador', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contador',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarLeida(Request $request, $id)
    {
        try {
            [$usuarioId, $usuarioTipo] = $this->obtenerUsuarioInfo($request);

            if (!$usuarioId || !$usuarioTipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado o tipo no válido'
                ], 401);
            }

            $notificacion = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                       ->findOrFail($id);

            $notificacion->marcarComoLeida();
            $notificacion->refresh(); // Recargar para obtener fecha_lectura actualizada
            
            // Asegurar que las fechas estén en la zona horaria correcta
            $fechaEnvio = $notificacion->fecha_envio ? 
                \Carbon\Carbon::parse($notificacion->fecha_envio)->setTimezone(config('app.timezone', 'America/La_Paz')) : null;
            $fechaLectura = $notificacion->fecha_lectura ? 
                \Carbon\Carbon::parse($notificacion->fecha_lectura)->setTimezone(config('app.timezone', 'America/La_Paz')) : null;

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída',
                'data' => [
                    'id' => $notificacion->id,
                    'titulo' => $notificacion->titulo,
                    'mensaje' => $notificacion->mensaje,
                    'tipo' => $notificacion->tipo,
                    'leida' => $notificacion->leida,
                    'fecha_envio' => $fechaEnvio ? $fechaEnvio->toIso8601String() : null,
                    'fecha_lectura' => $fechaLectura ? $fechaLectura->toIso8601String() : null,
                    'created_at' => $fechaEnvio ? $fechaEnvio->toIso8601String() : null,
                    'leida_at' => $fechaLectura ? $fechaLectura->toIso8601String() : null,
                    'fecha_formateada' => $fechaEnvio ? $fechaEnvio->format('d/m/Y H:i:s') : null,
                    'fecha_lectura_formateada' => $fechaLectura ? $fechaLectura->format('d/m/Y H:i:s') : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function marcarTodasLeidas(Request $request)
    {
        try {
            [$usuarioId, $usuarioTipo] = $this->obtenerUsuarioInfo($request);

            if (!$usuarioId || !$usuarioTipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado o tipo no válido'
                ], 401);
            }

            // Asegurar que la fecha esté en la zona horaria correcta
            $fechaLectura = \Carbon\Carbon::now()->setTimezone(config('app.timezone', 'America/La_Paz'));
            
            $actualizadas = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                       ->noLeidas()
                                       ->update([
                                           'leida' => true,
                                           'fecha_lectura' => $fechaLectura
                                       ]);

            return response()->json([
                'success' => true,
                'message' => "{$actualizadas} notificaciones marcadas como leídas",
                'data' => [
                    'actualizadas' => $actualizadas
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificación
     */
    public function destroy(Request $request, $id)
    {
        try {
            [$usuarioId, $usuarioTipo] = $this->obtenerUsuarioInfo($request);

            if (!$usuarioId || !$usuarioTipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado o tipo no válido'
                ], 401);
            }

            $notificacion = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                       ->findOrFail($id);

            $notificacion->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear notificación (para administradores)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usuario_id' => 'required|integer',
            'usuario_tipo' => 'required|in:student,teacher,admin',
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'tipo' => 'required|in:info,success,warning,error,documento,pago,academico,sistema',
            'datos_adicionales' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $notificacion = Notificacion::crearNotificacion(
                $request->usuario_id,
                $request->usuario_tipo,
                $request->titulo,
                $request->mensaje,
                $request->tipo,
                $request->datos_adicionales
            );

            return response()->json([
                'success' => true,
                'message' => 'Notificación creada exitosamente',
                'data' => $notificacion
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de notificaciones
     */
    public function estadisticas(Request $request)
    {
        try {
            [$usuarioId, $usuarioTipo] = $this->obtenerUsuarioInfo($request);

            if (!$usuarioId || !$usuarioTipo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado o tipo no válido'
                ], 401);
            }

            $estadisticas = [
                'total' => Notificacion::porUsuario($usuarioId, $usuarioTipo)->count(),
                'no_leidas' => Notificacion::porUsuario($usuarioId, $usuarioTipo)->noLeidas()->count(),
                'por_tipo' => Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                         ->selectRaw('tipo, count(*) as cantidad')
                                         ->groupBy('tipo')
                                         ->get(),
                'recientes' => Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                          ->recientes(7)
                                          ->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $estadisticas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación masiva (para administradores)
     */
    public function enviarMasiva(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'usuario_tipo' => 'required|in:student,teacher,admin,all',
            'titulo' => 'required|string|max:255',
            'mensaje' => 'required|string',
            'tipo' => 'required|in:info,success,warning,error,documento,pago,academico,sistema',
            'filtros' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $enviadas = 0;

            if ($request->usuario_tipo === 'all') {
                // Enviar a todos los usuarios
                $estudiantes = \App\Models\Estudiante::all();
                $docentes = \App\Models\Docente::all();

                foreach ($estudiantes as $estudiante) {
                    Notificacion::crearNotificacion(
                        $estudiante->registro_estudiante,
                        'student',
                        $request->titulo,
                        $request->mensaje,
                        $request->tipo,
                        $request->datos_adicionales
                    );
                    $enviadas++;
                }

                foreach ($docentes as $docente) {
                    $role = $docente->cargo === 'Administrador' ? 'admin' : 'teacher';
                    Notificacion::crearNotificacion(
                        $docente->id,
                        $role,
                        $request->titulo,
                        $request->mensaje,
                        $request->tipo,
                        $request->datos_adicionales
                    );
                    $enviadas++;
                }
            } else {
                // Enviar a usuarios específicos
                if ($request->usuario_tipo === 'student') {
                    $usuarios = \App\Models\Estudiante::all();
                } else {
                    $usuarios = \App\Models\Docente::all();
                }

                foreach ($usuarios as $usuario) {
                    Notificacion::crearNotificacion(
                        $usuario->id,
                        $request->usuario_tipo,
                        $request->titulo,
                        $request->mensaje,
                        $request->tipo,
                        $request->datos_adicionales
                    );
                    $enviadas++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Notificación enviada a {$enviadas} usuarios",
                'data' => [
                    'enviadas' => $enviadas
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar notificación masiva',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
