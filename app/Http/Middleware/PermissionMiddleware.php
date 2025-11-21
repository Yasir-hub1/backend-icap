<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class PermissionMiddleware
{
    /**
     * Handle an incoming request and verify user permission.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        try {
            // Obtener el usuario autenticado del JWT
            // Intentar primero con auth('api')->user() que debería funcionar después de auth:api middleware
            $user = auth('api')->user();

            // Si no funciona, intentar con JWTAuth directamente
            if (!$user) {
                try {
                    $user = JWTAuth::parseToken()->authenticate();
                } catch (\Exception $e) {
                    Log::warning('PermissionMiddleware: Error obteniendo usuario con JWTAuth', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (!$user) {
                Log::warning('PermissionMiddleware: Usuario no autenticado', [
                    'token_present' => request()->hasHeader('Authorization'),
                    'auth_guard' => 'api',
                    'authorization_header' => request()->header('Authorization') ? 'Present' : 'Missing'
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado'
                ], 401);
            }

            // Obtener el rol del JWT primero (más rápido)
            $userRole = null;
            $payload = null;
            try {
                // Obtener payload con JWTAuth directamente (más confiable)
                $payload = JWTAuth::parseToken()->getPayload();
                $userRole = $payload ? $payload->get('rol') : null;
            } catch (\Exception $e) {
                Log::warning('PermissionMiddleware: Error obteniendo payload, usando modelo', [
                    'error' => $e->getMessage()
                ]);
            }

            // Si no hay rol en el payload, cargar desde el modelo
            if (!$userRole) {
                if (!$user->relationLoaded('rol')) {
                    $user->load('rol');
                }
                if ($user->rol) {
                    $userRole = $user->rol->nombre_rol;
                } else {
                    // Fallback: determinar por tipo de modelo
                    if ($user instanceof \App\Models\Usuario) {
                        $userRole = 'ADMIN';
                    } elseif ($user instanceof \App\Models\Estudiante) {
                        $userRole = 'ESTUDIANTE';
                    } elseif ($user instanceof \App\Models\Docente) {
                        $userRole = 'DOCENTE';
                    }
                }
            }

            // Si el usuario es ADMIN, tiene acceso a todo (sin necesidad de cargar permisos)
            if ($userRole === 'ADMIN') {
                Log::debug('PermissionMiddleware: Admin tiene acceso completo', [
                    'usuario_id' => $user->getKey(),
                    'permiso_solicitado' => $permissions
                ]);
                $request->merge(['auth_user' => $user]);
                return $next($request);
            }

            // Cargar el rol con sus permisos solo si no es ADMIN
            $user->load('rol.permisos');

            // Verificar si el usuario tiene alguno de los permisos solicitados
            $tienePermiso = false;

            if ($user->rol) {
                foreach ($permissions as $permiso) {
                    if ($user->tienePermiso($permiso)) {
                        $tienePermiso = true;
                        break;
                    }
                }
            }

            if (!$tienePermiso) {
                $permisosUsuario = $user->rol ? $user->rol->permisos->pluck('nombre_permiso')->toArray() : [];

                Log::warning('PermissionMiddleware: Acceso denegado', [
                    'usuario_id' => $user->getKey(),
                    'rol' => $userRole,
                    'permisos_solicitados' => $permissions,
                    'permisos_del_usuario' => $permisosUsuario
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No tiene permisos para realizar esta acción',
                    'permisos_requeridos' => $permissions,
                    'rol_actual' => $userRole,
                    'permisos_disponibles' => $permisosUsuario
                ], 403);
            }

            Log::debug('PermissionMiddleware: Permiso concedido', [
                'usuario_id' => $user->getKey(),
                'rol' => $userRole,
                'permiso' => $permissions
            ]);

            // Agregar el usuario autenticado al request
            $request->merge(['auth_user' => $user]);

            return $next($request);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            Log::warning('PermissionMiddleware: Token expirado');
            return response()->json([
                'success' => false,
                'message' => 'Token expirado. Por favor, inicia sesión nuevamente.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            Log::warning('PermissionMiddleware: Token inválido');
            return response()->json([
                'success' => false,
                'message' => 'Token inválido. Por favor, inicia sesión nuevamente.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::error('PermissionMiddleware: Error JWT', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 401);
        } catch (\Exception $e) {
            Log::error('PermissionMiddleware Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de verificación de permisos',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

