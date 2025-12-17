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

class AutenticacionDocenteController extends Controller
{
    /**
     * Iniciar sesión para docentes
     * Permite login con email o CI + contraseña
     */
    public function iniciarSesion(Request $request)
    {
        try {
            Log::info('🔐 Docente Login attempt', $request->only('email', 'ci'));

            // Validar que venga email o ci, y contraseña
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:6'
            ]);

            // Validar que venga al menos email o ci
            if (!$request->has('email') && !$request->has('ci')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe proporcionar email o CI para iniciar sesión',
                    'errors' => [
                        'credentials' => ['Email o CI requerido']
                    ]
                ], 422);
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buscar usuario por email o CI
            $usuario = null;
            $credencialUsada = null;

            if ($request->has('email') && !empty($request->email)) {
                $credencialUsada = 'email';
                $usuario = Usuario::where('email', trim(strtolower($request->email)))->first();
                Log::info('🔍 Buscando por email', ['email' => $request->email, 'found' => $usuario ? 'yes' : 'no']);
            } elseif ($request->has('ci') && !empty($request->ci)) {
                $credencialUsada = 'ci';
                // Buscar persona por CI
                $persona = Persona::where('ci', $request->ci)->first();
                if ($persona) {
                    $usuario = Usuario::where('persona_id', $persona->id)->first();
                    Log::info('🔍 Buscando por CI', ['ci' => $request->ci, 'persona_id' => $persona->id, 'found' => $usuario ? 'yes' : 'no']);
                }
            }

            if (!$usuario) {
                Log::warning('🔐 Docente no encontrado', [
                    'email' => $request->email,
                    'ci' => $request->ci,
                    'credencial_usada' => $credencialUsada
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas. Verifica tu ' . ($credencialUsada === 'email' ? 'email' : 'CI') . ' y contraseña.'
                ], 401);
            }

            // Verificar password
            if (!Hash::check($request->password, $usuario->password)) {
                Log::warning('🔐 Password incorrecto', [
                    'usuario_id' => $usuario->usuario_id,
                    'email' => $usuario->email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas. Verifica tu contraseña.'
                ], 401);
            }

            // Cargar relación con rol y permisos ANTES de generar el token
            $usuario->load('rol.permisos', 'persona');

            // Verificar que tenga rol DOCENTE específicamente
            $rolNombre = $usuario->rol ? $usuario->rol->nombre_rol : null;
            if ($rolNombre !== 'DOCENTE') {
                Log::warning('🔐 Rol no autorizado para login docente', [
                    'usuario_id' => $usuario->usuario_id,
                    'rol' => $rolNombre
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado. Esta área es exclusiva para docentes. Si eres administrador, usa el portal de administración.'
                ], 403);
            }

            // Generar token JWT con custom claims
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

            Log::info('✅ Login docente exitoso', [
                'usuario_id' => $usuario->usuario_id,
                'email' => $usuario->email,
                'rol' => $rolNombre,
                'rol_id' => $usuario->rol_id,
                'rol_en_token' => $tokenRol,
                'debe_cambiar_password' => $usuario->debe_cambiar_password ?? false
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

            // Verificar si debe cambiar contraseña
            $debeCambiarPassword = $usuario->debe_cambiar_password ?? false;

            return response()->json([
                'success' => true,
                'message' => $debeCambiarPassword
                    ? 'Login exitoso. Debe cambiar su contraseña antes de continuar.'
                    : 'Login exitoso',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'debe_cambiar_password' => $debeCambiarPassword,
                'user' => [
                    'id' => $usuario->usuario_id,
                    'email' => $usuario->email,
                    'nombre' => $usuario->persona->nombre ?? 'Docente',
                    'apellido' => $usuario->persona->apellido ?? '',
                    'ci' => $usuario->persona->ci ?? null,
                    'rol' => $rolNombre,
                    'rol_id' => $usuario->rol_id,
                    'permisos' => $permisos,
                    'debe_cambiar_password' => $debeCambiarPassword
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error en login docente: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => config('app.debug') ? $e->getMessage() : 'Error de autenticación'
            ], 500);
        }
    }
}

