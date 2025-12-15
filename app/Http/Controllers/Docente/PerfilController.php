<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerfilController extends Controller
{
    /**
     * Cambiar contraseña del docente
     */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password_actual' => 'required|string',
            'password_nuevo' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'
            ],
            'password_nuevo_confirmacion' => 'required|same:password_nuevo'
        ], [
            'password_actual.required' => 'La contraseña actual es obligatoria',
            'password_nuevo.required' => 'La nueva contraseña es obligatoria',
            'password_nuevo.min' => 'La nueva contraseña debe tener al menos 8 caracteres',
            'password_nuevo.regex' => 'La nueva contraseña debe contener al menos una mayúscula, una minúscula y un número',
            'password_nuevo_confirmacion.required' => 'La confirmación de contraseña es obligatoria',
            'password_nuevo_confirmacion.same' => 'Las contraseñas no coinciden'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // El middleware auth:api pasa un Usuario en auth_user
            $usuario = $request->auth_user;

            if (!$usuario instanceof Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado correctamente'
                ], 401);
            }

            // Verificar contraseña actual
            if (!Hash::check($request->password_actual, $usuario->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña actual es incorrecta'
                ], 422);
            }

            // Verificar que la nueva contraseña sea diferente a la actual
            if (Hash::check($request->password_nuevo, $usuario->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La nueva contraseña debe ser diferente a la actual'
                ], 422);
            }

            DB::beginTransaction();

            // Actualizar contraseña y marcar que ya no debe cambiarla
            $usuario->password = Hash::make($request->password_nuevo);
            $usuario->debe_cambiar_password = false;
            $usuario->save();

            DB::commit();

            Log::info('Contraseña cambiada exitosamente', [
                'usuario_id' => $usuario->usuario_id,
                'email' => $usuario->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contraseña cambiada exitosamente'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar contraseña: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar contraseña: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambiar email del docente
     */
    public function cambiarEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email_nuevo' => 'required|email|max:255|unique:usuario,email',
            'password' => 'required|string'
        ], [
            'email_nuevo.required' => 'El nuevo email es obligatorio',
            'email_nuevo.email' => 'El email debe tener un formato válido',
            'email_nuevo.unique' => 'El email ya está registrado en el sistema',
            'password.required' => 'La contraseña es obligatoria para confirmar el cambio'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // El middleware auth:api pasa un Usuario en auth_user
            $usuario = $request->auth_user;

            if (!$usuario instanceof Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado correctamente'
                ], 401);
            }

            // Verificar contraseña
            if (!Hash::check($request->password, $usuario->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La contraseña es incorrecta'
                ], 422);
            }

            DB::beginTransaction();

            $emailAnterior = $usuario->email;
            $usuario->email = trim(strtolower($request->email_nuevo));
            $usuario->save();

            DB::commit();

            Log::info('Email cambiado exitosamente', [
                'usuario_id' => $usuario->usuario_id,
                'email_anterior' => $emailAnterior,
                'email_nuevo' => $usuario->email
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email cambiado exitosamente',
                'data' => [
                    'email' => $usuario->email
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cambiar email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener información del perfil del docente
     */
    public function obtenerPerfil(Request $request): JsonResponse
    {
        try {
            // El middleware auth:api pasa un Usuario en auth_user
            $usuario = $request->auth_user;

            if (!$usuario instanceof Usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado correctamente'
                ], 401);
            }

            // Cargar relación con persona si no está cargada
            if (!$usuario->relationLoaded('persona')) {
                $usuario->load('persona');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'usuario_id' => $usuario->usuario_id,
                    'email' => $usuario->email,
                    'debe_cambiar_password' => $usuario->debe_cambiar_password ?? false,
                    'persona' => $usuario->persona ? [
                        'id' => $usuario->persona->id,
                        'nombre' => $usuario->persona->nombre,
                        'apellido' => $usuario->persona->apellido,
                        'ci' => $usuario->persona->ci,
                        'celular' => $usuario->persona->celular
                    ] : null
                ],
                'message' => 'Perfil obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener perfil: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener perfil: ' . $e->getMessage()
            ], 500);
        }
    }
}

