<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificacionController extends Controller
{
    /**
     * Obtener notificaciones del usuario actual
     */
    public function index(Request $request)
    {
        try {
            $usuarioId = $request->user_id;
            $usuarioTipo = $request->user_type;

            $query = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                ->orderBy('fecha_envio', 'desc');

            // Filtros opcionales
            if ($request->has('no_leidas') && $request->no_leidas) {
                $query->noLeidas();
            }

            if ($request->has('tipo')) {
                $query->porTipo($request->tipo);
            }

            if ($request->has('dias')) {
                $query->recientes($request->dias);
            }

            $notificaciones = $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $notificaciones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener contador de notificaciones no leídas
     */
    public function contador(Request $request)
    {
        try {
            $usuarioId = $request->user_id;
            $usuarioTipo = $request->user_type;

            $contador = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                   ->noLeidas()
                                   ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'contador' => $contador
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener contador',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificación como leída
     */
    public function marcarLeida(Request $request, $id)
    {
        try {
            $usuarioId = $request->user_id;
            $usuarioTipo = $request->user_type;

            $notificacion = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                       ->findOrFail($id);

            $notificacion->marcarComoLeida();

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída',
                'data' => $notificacion
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
            $usuarioId = $request->user_id;
            $usuarioTipo = $request->user_type;

            $actualizadas = Notificacion::porUsuario($usuarioId, $usuarioTipo)
                                       ->noLeidas()
                                       ->update([
                                           'leida' => true,
                                           'fecha_lectura' => now()
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
            $usuarioId = $request->user_id;
            $usuarioTipo = $request->user_type;

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
            $usuarioId = $request->user_id;
            $usuarioTipo = $request->user_type;

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
                        $estudiante->id,
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
