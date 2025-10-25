<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class AutenticacionEstudianteController extends Controller
{
    /**
     * Registrar un nuevo estudiante
     */
    public function registrar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ci' => 'required|string|max:20|unique:estudiante,ci',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'required|string|max:20',
            'fecha_nacimiento' => 'required|date|before:-14 years',
            'direccion' => 'required|string|max:300',
            'provincia' => 'required|string|max:100',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            'password_confirmation' => 'required|same:password'
        ], [
            'ci.unique' => 'El CI ya está registrado en el sistema',
            'fecha_nacimiento.before' => 'Debe ser mayor de 14 años',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula y un número'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Primero crear la Persona
            $persona = \App\Models\Persona::create([
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'celular' => $request->celular,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'direccion' => $request->direccion
            ]);

            \Log::info('Persona creada', ['persona_id' => $persona->persona_id]);

            // Luego crear Estudiante referenciando a Persona
            $estudiante = Estudiante::create([
                'persona_id' => $persona->persona_id,
                'ci' => $request->ci,
                'nombre' => $request->nombre,
                'apellido' => $request->apellido,
                'celular' => $request->celular,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'direccion' => $request->direccion,
                'provincia' => $request->provincia
            ]);

            \Log::info('Estudiante creado', [
                'registro_estudiante' => $estudiante->registro_estudiante,
                'persona_id' => $estudiante->persona_id
            ]);

            // Create usuario with password usando persona_id de la persona
            $usuario = \App\Models\Usuario::create([
                'email' => $request->ci . '@estudiante.com', // Usar CI como email temporal
                'password' => Hash::make($request->password),
                'persona_id' => $persona->persona_id
            ]);

            \Log::info('Usuario creado', [
                'usuario_id' => $usuario->usuario_id
            ]);

            // Log to Bitacora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Estudiante',
                'cod_tabla' => $estudiante->registro_estudiante,
                'transaccion' => 'REGISTRO_NUEVO_ESTUDIANTE',
                'usuario_id' => $usuario->usuario_id
            ]);

            // Generar token JWT automáticamente después del registro
            $token = JWTAuth::fromUser($estudiante);

            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso. Bienvenido al sistema',
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $estudiante->id,
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'Estado_id' => $estudiante->Estado_id,
                    'provincia' => $estudiante->provincia,
                    'rol' => 'ESTUDIANTE'
                ],
                'data' => [
                    'registro_estudiante' => $estudiante->registro_estudiante
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar estudiante',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Iniciar sesión de estudiante
     */
    public function iniciarSesion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ci' => 'required|string',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Buscar estudiante por CI
            $estudiante = Estudiante::where('ci', $request->ci)->first();

            \Log::info('Login attempt', [
                'ci' => $request->ci,
                'estudiante_found' => $estudiante ? true : false,
                'estudiante_id' => $estudiante ? $estudiante->registro_estudiante : null,
                'persona_id' => $estudiante ? $estudiante->persona_id : null
            ]);

            if (!$estudiante) {
                return response()->json([
                    'success' => false,
                    'message' => 'CI o contraseña incorrectos'
                ], 401);
            }

            // Buscar el usuario asociado a la persona del estudiante
            $usuario = $estudiante->usuario;

            \Log::info('Usuario found', [
                'usuario_found' => $usuario ? true : false,
                'usuario_id' => $usuario ? $usuario->usuario_id : null,
                'has_password' => $usuario ? !empty($usuario->password) : false
            ]);

            if (!$usuario || !Hash::check($request->password, $usuario->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'CI o contraseña incorrectos'
                ], 401);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($estudiante);

            // Log to Bitacora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Estudiante',
                'cod_tabla' => $estudiante->registro_estudiante,
                'transaccion' => 'LOGIN_ESTUDIANTE',
                'usuario_id' => $usuario->usuario_id
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $estudiante->id,
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'Estado_id' => $estudiante->Estado_id,
                    'provincia' => $estudiante->provincia,
                    'rol' => 'ESTUDIANTE'
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos del estudiante autenticado
     */
    public function obtenerPerfil()
    {
        try {
            $estudiante = auth()->user();

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $estudiante->id,
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'celular' => $estudiante->celular,
                    'fecha_nacimiento' => $estudiante->fecha_nacimiento,
                    'direccion' => $estudiante->direccion,
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'provincia' => $estudiante->provincia,
                    'Estado_id' => $estudiante->Estado_id,
                    'fotografia' => $estudiante->fotografia,
                    'rol' => 'ESTUDIANTE'
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cerrar sesión del estudiante
     */
    public function cerrarSesion()
    {
        try {
            auth()->logout();

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar sesión',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refrescar token de autenticación
     */
    public function refrescarToken()
    {
        try {
            $newToken = auth()->refresh();

            return response()->json([
                'success' => true,
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al refrescar token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
