<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Docente;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AutenticacionAdminController extends Controller
{
    /**
     * Admin/Teacher login
     */
    public function login(Request $request)
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
            // Search by CI or registro_docente
            $docente = Docente::where('ci', $request->ci)
                             ->orWhere('registro_docente', $request->ci)
                             ->first();

            if (!$docente || !Hash::check($request->password, $docente->clave)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], 401);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($docente);

            // Determine role
            $rol = $docente->rol ?? 'DOCENTE';

            // Log to Bitacora
            Bitacora::create([
                'tabla' => 'Docente',
                'codTable' => json_encode(['docente_id' => $docente->id, 'rol' => $rol]),
                'transaccion' => $rol === 'ADMIN' ? 'LOGIN_ADMIN' : 'LOGIN_DOCENTE',
                'Usuario_id' => $docente->id
            ]);

            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60,
                'user' => [
                    'id' => $docente->id,
                    'ci' => $docente->ci,
                    'nombre' => $docente->nombre,
                    'apellido' => $docente->apellido,
                    'registro_docente' => $docente->registro_docente,
                    'cargo' => $docente->cargo,
                    'area_especializacion' => $docente->area_de_especializacion,
                    'rol' => $rol
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
     * Get authenticated user
     */
    public function me()
    {
        try {
            $user = auth()->user();

            $rol = $user->rol ?? 'DOCENTE';

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'ci' => $user->ci,
                    'nombre' => $user->nombre,
                    'apellido' => $user->apellido,
                    'celular' => $user->celular,
                    'registro_docente' => $user->registro_docente,
                    'cargo' => $user->cargo,
                    'area_especializacion' => $user->area_de_especializacion,
                    'modalidad_contratacion' => $user->modalidad_de_contratacion,
                    'rol' => $rol
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
     * Logout
     */
    public function logout()
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
     * Refresh token
     */
    public function refresh()
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
