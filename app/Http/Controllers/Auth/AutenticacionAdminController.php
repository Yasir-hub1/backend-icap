<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Models\Persona;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class AutenticacionAdminController extends Controller
{
    /**
     * Iniciar sesión para administradores
     */
    public function iniciarSesion(Request $request)
    {
        try {
            Log::info('🔐 Admin Login attempt', $request->only('email', 'ci'));

            // Validar que venga email o ci
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buscar usuario por email o CI
            $usuario = null;
            if ($request->has('email')) {
                $usuario = Usuario::where('email', $request->email)->first();
            } elseif ($request->has('ci')) {
                // Buscar por persona con ese CI
                $persona = Persona::where('ci', $request->ci)->first();
                if ($persona) {
                    $usuario = Usuario::where('persona_id', $persona->persona_id)->first();
                }
            }

            if (!$usuario) {
                Log::warning('🔐 Usuario no encontrado', ['email' => $request->email, 'ci' => $request->ci]);
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], 401);
            }

            // Verificar password
            if (!Hash::check($request->password, $usuario->password)) {
                Log::warning('🔐 Password incorrecto', ['usuario_id' => $usuario->usuario_id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], 401);
            }

            // Cargar relación con rol y permisos ANTES de generar el token
            $usuario->load('rol.permisos', 'persona');

            // Verificar que tenga rol ADMIN o DOCENTE
            $rolNombre = $usuario->rol ? $usuario->rol->nombre_rol : null;
            if (!in_array($rolNombre, ['ADMIN', 'DOCENTE'])) {
                Log::warning('🔐 Rol no autorizado', ['rol' => $rolNombre]);
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Solo administradores y docentes pueden acceder.'
                ], 403);
            }

            // Generar token JWT con custom claims explícitos para asegurar que el rol esté incluido
            // Esto es necesario porque el modelo puede no tener el rol cargado cuando se llama getJWTCustomClaims()
            $customClaims = [
                'rol' => $rolNombre,
                'rol_id' => $usuario->rol_id,
                'usuario_id' => $usuario->usuario_id,
                'persona_id' => $usuario->persona_id,
                'email' => $usuario->email
            ];
            $token = JWTAuth::customClaims($customClaims)->fromUser($usuario);

            // Verificar que el token incluye el rol
            $finalPayload = JWTAuth::setToken($token)->getPayload();
            $tokenRol = $finalPayload->get('rol');

            if (!$tokenRol) {
                Log::error('🔐 ERROR CRÍTICO: Token generado sin rol', [
                    'usuario_id' => $usuario->usuario_id,
                    'rol_id' => $usuario->rol_id,
                    'rol_nombre' => $rolNombre,
                    'payload' => $finalPayload->toArray()
                ]);
            }

            Log::info('✅ Login exitoso', [
                'usuario_id' => $usuario->usuario_id,
                'rol' => $rolNombre,
                'rol_id' => $usuario->rol_id,
                'rol_en_token' => $tokenRol,
                'token_valid' => !empty($tokenRol)
            ]);

            // Cargar permisos del rol
            $permisos = [];
            if ($usuario->rol) {
                $permisos = $usuario->rol->permisos->map(function($permiso) {
                    return [
                        'id' => $permiso->permiso_id,
                        'nombre_permiso' => $permiso->nombre_permiso,
                        'modulo' => $permiso->modulo,
                        'accion' => $permiso->accion,
                        'descripcion' => $permiso->descripcion
                    ];
                })->toArray();
            }

            return response()->json([
                'success' => true,
                'message' => 'Login exitoso',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $usuario->usuario_id,
                    'email' => $usuario->email,
                    'nombre' => $usuario->persona->nombre ?? 'Admin',
                    'apellido' => $usuario->persona->apellido ?? 'Sistema',
                    'ci' => $usuario->persona->ci ?? null,
                    'rol' => $rolNombre,
                    'rol_id' => $usuario->rol_id,
                    'permisos' => $permisos
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en login admin: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error de autenticación'
            ], 500);
        }
    }
}
