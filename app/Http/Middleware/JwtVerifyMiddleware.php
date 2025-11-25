<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtVerifyMiddleware
{
    /**
     * Handle an incoming request and verify JWT token.
     * This middleware works with both Estudiante and Usuario models.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
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
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado'
                ], 401);
            }

            // Obtener el payload del JWT para determinar el tipo de usuario
            try {
                JWTAuth::setToken($token);
                $payload = JWTAuth::parseToken()->getPayload();
                $userRole = $payload ? $payload->get('rol') : null;
                $sub = $payload ? $payload->get('sub') : null;

                // Si el rol es ESTUDIANTE, obtener el Estudiante directamente
                if ($userRole === 'ESTUDIANTE') {
                    // Para estudiantes, el 'sub' es el id (devuelto por getJWTIdentifier que usa getKey())
                    $estudiante = \App\Models\Estudiante::find($sub);
                    if ($estudiante) {
                        $user = $estudiante;
                    }
                } else {
                    // Para admin/docente, el 'sub' es usuario_id
                    $usuario = \App\Models\Usuario::with('rol', 'persona')->find($sub);
                    if ($usuario) {
                        $user = $usuario;
                    }
                }
            } catch (\Exception $e) {
                Log::error('JwtVerifyMiddleware: Error obteniendo usuario desde token', [
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido: ' . $e->getMessage()
                ], 401);
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 401);
            }

            // Agregar el usuario autenticado al request para acceso en controladores
            $request->merge(['auth_user' => $user]);

            return $next($request);

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expirado. Por favor, inicia sesión nuevamente.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido. Por favor, inicia sesión nuevamente.'
            ], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            Log::error('JwtVerifyMiddleware: Error JWT', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 401);
        } catch (\Exception $e) {
            Log::error('JwtVerifyMiddleware Error', [
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

