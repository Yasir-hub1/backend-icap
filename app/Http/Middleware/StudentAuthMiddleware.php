<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Middleware específico para autenticación de estudiantes
 * Maneja la autenticación JWT para el modelo Estudiante
 */
class StudentAuthMiddleware
{
    /**
     * Handle an incoming request and verify JWT token for students.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $estudiante = null;

            // Verificar que el token esté presente
            $token = $request->bearerToken() ?? $request->header('Authorization');
            if (!$token) {
                $authHeader = $request->header('Authorization');
                if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
                    $token = substr($authHeader, 7);
                }
            }

            if (!$token) {
                Log::warning('StudentAuthMiddleware: No token provided', [
                    'url' => $request->url()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado'
                ], 401);
            }

            // Obtener el payload del JWT
            try {
                JWTAuth::setToken($token);
                $payload = JWTAuth::parseToken()->getPayload();

                if (!$payload) {
                    throw new \Exception('Payload vacío');
                }

                $userRole = $payload->get('rol');
                $sub = $payload->get('sub');

                Log::info('StudentAuthMiddleware: Payload obtenido', [
                    'rol' => $userRole,
                    'sub' => $sub
                ]);

                // Verificar que el rol sea ESTUDIANTE
                if ($userRole !== 'ESTUDIANTE') {
                    Log::warning('StudentAuthMiddleware: Rol incorrecto', [
                        'rol' => $userRole,
                        'esperado' => 'ESTUDIANTE'
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Esta ruta es solo para estudiantes'
                    ], 403);
                }

                // Obtener el estudiante usando el ID del token
                // Para estudiantes, el 'sub' es el id (devuelto por getJWTIdentifier)
                $estudiante = \App\Models\Estudiante::find($sub);

                if (!$estudiante) {
                    Log::warning('StudentAuthMiddleware: Estudiante no encontrado', [
                        'id' => $sub
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Estudiante no encontrado'
                    ], 404);
                }

                Log::info('StudentAuthMiddleware: Estudiante autenticado', [
                    'id' => $estudiante->id,
                    'registro_estudiante' => $estudiante->registro_estudiante
                ]);

            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                Log::warning('StudentAuthMiddleware: Token expirado');
                return response()->json([
                    'success' => false,
                    'message' => 'Token expirado. Por favor, inicia sesión nuevamente.'
                ], 401);
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                Log::warning('StudentAuthMiddleware: Token inválido', [
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido. Por favor, inicia sesión nuevamente.'
                ], 401);
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                Log::error('StudentAuthMiddleware: Error JWT', [
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error de autenticación',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 401);
            } catch (\Exception $e) {
                Log::error('StudentAuthMiddleware: Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error de autenticación',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 401);
            }

            // Agregar el estudiante autenticado al request
            $request->merge(['auth_user' => $estudiante]);
            $request->merge(['auth_estudiante' => $estudiante]); // Alias para claridad

            return $next($request);

        } catch (\Exception $e) {
            Log::error('StudentAuthMiddleware: Error general', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

