<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            // Obtener el usuario autenticado del JWT
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado'
                ], 401);
            }

            // Obtener el rol del usuario desde el JWT custom claim
            $payload = auth()->payload();
            $userRole = $payload->get('rol');

            // Verificar si el rol del usuario está en los roles permitidos
            if (!in_array($userRole, $roles)) {
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

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}
