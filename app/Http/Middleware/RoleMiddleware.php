<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleMiddleware
{
    /**
     * Handle an incoming request and verify user role.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            $user = null;

            // Verificar que el token esté presente
            $token = $request->bearerToken() ?? $request->header('Authorization');
            if (!$token) {
                // Intentar obtener del header Authorization sin Bearer
                $authHeader = $request->header('Authorization');
                if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                }
            }

            if (!$token) {
                Log::warning('RoleMiddleware: No token provided', [
                    'url' => $request->url(),
                    'headers' => $request->headers->all()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado'
                ], 401);
            }

            // Primero intentar obtener el payload del JWT para determinar el tipo de usuario
            $payload = null;
            $userRole = null;

            try {
                // Establecer el token manualmente
                JWTAuth::setToken($token);
                $payload = JWTAuth::parseToken()->getPayload();
                $userRole = $payload ? $payload->get('rol') : null;

                Log::info('RoleMiddleware: Payload obtenido', [
                    'rol' => $userRole,
                    'sub' => $payload ? $payload->get('sub') : null,
                    'token_length' => strlen($token)
                ]);
            } catch (\Exception $e) {
                Log::error('RoleMiddleware: Error obteniendo payload', [
                    'error' => $e->getMessage(),
                    'token_preview' => substr($token, 0, 20) . '...'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido: ' . $e->getMessage()
                ], 401);
            }

            // Si el rol es ESTUDIANTE, obtener el Estudiante directamente desde el token
            if ($userRole === 'ESTUDIANTE' || in_array('ESTUDIANTE', $roles)) {
                try {
                    if ($payload) {
                        $registroEstudiante = $payload->get('sub');
                        $estudiante = \App\Models\Estudiante::find($registroEstudiante);

                        if ($estudiante) {
                            $user = $estudiante;
                            Log::info('RoleMiddleware: Estudiante obtenido desde token', [
                                'registro_estudiante' => $registroEstudiante
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('RoleMiddleware: Error obteniendo estudiante', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Si no es estudiante o no se pudo obtener, intentar con auth('api')->user()
            if (!$user) {
                try {
                    $user = auth('api')->user();
                } catch (\Exception $e) {
                    Log::warning('RoleMiddleware: Error con auth()->user()', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Si aún no hay usuario, intentar con JWTAuth directamente
            if (!$user) {
                try {
                    $user = JWTAuth::parseToken()->authenticate();
                } catch (\Exception $e) {
                    Log::warning('RoleMiddleware: Error obteniendo usuario con JWTAuth', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (!$user) {
                Log::warning('RoleMiddleware: Usuario no autenticado', [
                    'token_present' => $request->hasHeader('Authorization'),
                    'auth_guard' => 'api',
                    'authorization_header' => $request->header('Authorization') ? 'Present' : 'Missing',
                    'payload_rol' => $userRole
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado'
                ], 401);
            }

            // Si no obtuvimos el rol del payload anterior, intentar obtenerlo ahora
            if (!$userRole && $payload) {
                $userRole = $payload->get('rol');
            }

            // Si aún no hay rol, intentar obtenerlo desde el modelo
            if (!$userRole) {
                // Si es Estudiante, el rol es ESTUDIANTE
                if ($user instanceof \App\Models\Estudiante) {
                    $userRole = 'ESTUDIANTE';
                } elseif ($user instanceof \App\Models\Docente) {
                    $userRole = 'DOCENTE';
                } elseif ($user instanceof \App\Models\Usuario) {
                    // Para Usuario, cargar el rol desde la relación
                    if (!$user->relationLoaded('rol')) {
                        $user->load('rol');
                    }
                    if ($user->rol) {
                        $userRole = $user->rol->nombre_rol;
                    } else {
                        $userRole = 'ADMIN'; // Fallback
                    }
                }
            }

            // Debug logging
            Log::info('RoleMiddleware Debug', [
                'user_class' => get_class($user),
                'user_id' => $user->getKey(),
                'jwt_payload' => $payload ? $payload->toArray() : null,
                'user_role' => $userRole,
                'required_roles' => $roles
            ]);

            // Verificar si el rol del usuario está en los roles permitidos
            if (!in_array($userRole, $roles)) {
                Log::warning('Access denied', [
                    'user_role' => $userRole,
                    'required_roles' => $roles,
                    'user_class' => get_class($user)
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para acceder a este recurso',
                    'rol_requerido' => $roles,
                    'rol_actual' => $userRole
                ], 403);
            }

            // Agregar el usuario autenticado al request para acceso en controladores
            $request->merge(['auth_user' => $user]);

            return $next($request);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            Log::warning('RoleMiddleware: Token expirado');
            return response()->json([
                'success' => false,
                'message' => 'Token expirado. Por favor, inicia sesión nuevamente.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            Log::warning('RoleMiddleware: Token inválido');
            return response()->json([
                'success' => false,
                'message' => 'Token inválido. Por favor, inicia sesión nuevamente.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::error('RoleMiddleware: Error JWT', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 401);
        } catch (\Exception $e) {
            Log::error('RoleMiddleware Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 401);
        }
    }
}
