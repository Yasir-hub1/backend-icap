<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Bitacora;
use App\Models\Notificacion;
use App\Helpers\CodigoHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
            'sexo' => 'required|in:M,F',
            'email' => 'required|email|max:255|unique:usuario,email',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
            'password_confirmation' => 'required|same:password'
        ], [
            'ci.unique' => 'El CI ya está registrado en el sistema',
            'email.unique' => 'El email ya está registrado en el sistema',
            'email.email' => 'El email debe tener un formato válido',
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
            // Generar código único de 5 dígitos para el estudiante
            $registroEstudiante = CodigoHelper::generarCodigoEstudiante();

            // SOLUCIÓN CORRECTA: Con PostgreSQL INHERITS, debemos seguir el patrón del seeder
            // 1. Insertar primero en persona para obtener el ID
            // 2. Luego insertar en estudiante con ese mismo ID
            // 3. Finalmente crear el usuario con ese ID
            // Esto evita problemas de visibilidad de foreign keys con INHERITS dentro de transacciones
            DB::beginTransaction();

            try {
                // PASO 1: Insertar primero en persona para obtener el ID
                // Esto garantiza que el ID existe en persona antes de crear el usuario
                $personaId = DB::table('persona')->insertGetId([
                    'ci' => $request->ci,
                    'nombre' => $request->nombre,
                    'apellido' => $request->apellido,
                    'celular' => $request->celular,
                    'sexo' => $request->sexo,
                    'fecha_nacimiento' => $request->fecha_nacimiento,
                    'direccion' => $request->direccion,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // PASO 2: Insertar en estudiante con el mismo ID de persona
                // Con INHERITS, esto crea el registro en estudiante que hereda de persona
                DB::table('estudiante')->insert([
                    'id' => $personaId, // Usar el mismo ID de persona
                    'ci' => $request->ci,
                    'nombre' => $request->nombre,
                    'apellido' => $request->apellido,
                    'celular' => $request->celular,
                    'sexo' => $request->sexo,
                    'fecha_nacimiento' => $request->fecha_nacimiento,
                    'direccion' => $request->direccion,
                    'provincia' => $request->provincia,
                    'registro_estudiante' => $registroEstudiante,
                    'estado_id' => 1, // Estado inicial: Pre-registrado
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Cargar el modelo Estudiante con el id generado
                $estudiante = Estudiante::find($personaId);
                if (!$estudiante) {
                    DB::rollBack();
                    throw new \Exception('Error: No se pudo cargar el estudiante después de la inserción.');
                }

                Log::info('Estudiante creado', [
                    'id' => $estudiante->id,
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'ci' => $estudiante->ci
                ]);

                // Obtener el rol ESTUDIANTE
                $rolEstudiante = \App\Models\Rol::where('nombre_rol', 'ESTUDIANTE')->first();

                if (!$rolEstudiante) {
                    DB::rollBack();
                    Log::error('Rol ESTUDIANTE no encontrado en la base de datos');
                    return response()->json([
                        'success' => false,
                        'message' => 'Error de configuración: Rol ESTUDIANTE no encontrado'
                    ], 500);
                }

                // PASO 3: Create usuario with email and password
                // Ahora el persona_id existe en persona, así que la foreign key funcionará correctamente
                $usuarioResultado = DB::selectOne("
                    INSERT INTO usuario (email, password, persona_id, rol_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?)
                    RETURNING usuario_id
                ", [
                    trim(strtolower($request->email)), // Usar el email proporcionado, normalizado
                    Hash::make($request->password),
                    $personaId, // Usar el ID de persona que ya existe
                    $rolEstudiante->rol_id,
                    now(),
                    now()
                ]);

                if (!$usuarioResultado || !isset($usuarioResultado->usuario_id)) {
                    DB::rollBack();
                    throw new \Exception('Error: No se pudo crear el usuario o recuperar su ID.');
                }

                // Commit la transacción completa
                DB::commit();

                // Cargar el modelo Usuario después del commit
                $usuario = \App\Models\Usuario::find($usuarioResultado->usuario_id);
                if (!$usuario) {
                    throw new \Exception('Error: No se pudo cargar el usuario después de la creación.');
                }

                Log::info('Usuario creado', [
                    'usuario_id' => $usuario->usuario_id,
                    'rol_id' => $usuario->rol_id,
                    'rol_nombre' => $rolEstudiante->nombre_rol
                ]);

            } catch (\Exception $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                Log::error('Error al insertar estudiante y crear usuario', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'ci' => $request->ci,
                    'registro_estudiante' => $registroEstudiante
                ]);
                throw $e;
            }

            // Log to Bitacora
            Bitacora::create([
                'fecha' => now()->toDateString(),
                'tabla' => 'Estudiante',
                'codTabla' => $estudiante->registro_estudiante,
                'transaccion' => 'REGISTRO_NUEVO_ESTUDIANTE',
                'usuario_id' => $usuario->usuario_id
            ]);

            // Enviar notificación de bienvenida al estudiante
            // Nota: Notificacion usa registro_estudiante como usuario_id para estudiantes
            // según la relación definida en el modelo Notificacion
            try {
                Notificacion::crearNotificacion(
                    $estudiante->registro_estudiante, // El modelo Notificacion espera registro_estudiante para estudiantes
                    'student',
                    '¡Bienvenido a ICAP UAGRM!',
                    'Tu registro ha sido exitoso. Para completar tu proceso de inscripción, debes subir los siguientes documentos: Fotocopia de cédula de identidad, Certificado de nacimiento, 2 fotografías tamaño 3x3 (fondo gris), y Título de bachiller (solo para técnico medio).',
                    'info',
                    [
                        'estudiante_id' => $estudiante->id, // Usar id para datos adicionales
                        'registro_estudiante' => $estudiante->registro_estudiante,
                        'estado_id' => $estudiante->estado_id,
                        'action' => 'upload_documents'
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('Error enviando notificación de bienvenida', [
                    'error' => $e->getMessage(),
                    'estudiante_id' => $estudiante->id,
                    'registro_estudiante' => $estudiante->registro_estudiante
                ]);
            }

            // Generar token JWT automáticamente después del registro
            // Usar custom claims para asegurar que el token incluya toda la información necesaria
            $customClaims = [
                'rol' => 'ESTUDIANTE',
                'rol_id' => $usuario->rol_id,
                'usuario_id' => $usuario->usuario_id,
                'persona_id' => $estudiante->id,
                'ci' => $estudiante->ci,
                'registro' => $estudiante->registro_estudiante
            ];

            try {
                $token = JWTAuth::customClaims($customClaims)->fromUser($estudiante);
            } catch (\Exception $e) {
                Log::error('Error generando token JWT en registro', [
                    'error' => $e->getMessage(),
                    'estudiante_id' => $estudiante->id,
                    'trace' => $e->getTraceAsString()
                ]);
                // No fallar el registro si el token falla, pero loguear el error
                $token = null;
            }

            // Verificar que el token se generó correctamente
            if ($token) {
                try {
                    $payload = JWTAuth::setToken($token)->getPayload();
                    Log::info('Token generado correctamente en registro', [
                        'sub' => $payload->get('sub'),
                        'rol' => $payload->get('rol'),
                        'estudiante_id' => $estudiante->id,
                        'registro_estudiante' => $estudiante->registro_estudiante
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error verificando token generado en registro', [
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                Log::warning('Token no generado en registro, pero el estudiante fue creado exitosamente');
            }

            $response = [
                'success' => true,
                'message' => 'Registro exitoso. Bienvenido al sistema',
                'user' => [
                    'id' => $estudiante->id, // Usar id real para consistencia con JWT
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'nombre_completo' => trim($estudiante->nombre . ' ' . $estudiante->apellido),
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'estado_id' => $estudiante->estado_id,
                    'provincia' => $estudiante->provincia,
                    'celular' => $estudiante->celular,
                    'email' => $usuario->email,
                    'rol' => 'ESTUDIANTE',
                    'rol_id' => $usuario->rol_id
                ],
                'data' => [
                    'registro_estudiante' => $estudiante->registro_estudiante
                ]
            ];

            // Solo incluir token si se generó correctamente
            if ($token) {
                $response['token'] = $token;
                $response['token_type'] = 'bearer';
                $response['expires_in'] = JWTAuth::factory()->getTTL() * 60;
            }

            return response()->json($response, 201);

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
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Limpiar y normalizar email
            $email = trim(strtolower($request->email));

            // Buscar usuario por email
            $usuario = \App\Models\Usuario::where('email', $email)->first();

            Log::info('Login attempt', [
                'email' => $email,
                'email_original' => $request->email,
                'usuario_found' => $usuario ? true : false,
                'usuario_id' => $usuario ? $usuario->usuario_id : null,
                'persona_id' => $usuario ? $usuario->persona_id : null
            ]);

            if (!$usuario) {
                Log::warning('Usuario no encontrado', [
                    'email' => $email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email o contraseña incorrectos'
                ], 401);
            }

            // Verificar que el usuario tenga rol ESTUDIANTE
            $usuario->load('rol');
            if (!$usuario->rol || $usuario->rol->nombre_rol !== 'ESTUDIANTE') {
                Log::warning('Usuario no es estudiante', [
                    'usuario_id' => $usuario->usuario_id,
                    'rol' => $usuario->rol ? $usuario->rol->nombre_rol : null
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email o contraseña incorrectos'
                ], 401);
            }

            // Verificar contraseña
            if (!Hash::check($request->password, $usuario->password)) {
                Log::warning('Contraseña incorrecta', [
                    'usuario_id' => $usuario->usuario_id,
                    'email' => $email
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email o contraseña incorrectos'
                ], 401);
            }

            // Buscar el estudiante asociado a la persona del usuario
            $estudiante = Estudiante::where('id', $usuario->persona_id)->first();

            if (!$estudiante) {
                Log::warning('Estudiante no encontrado para usuario', [
                    'usuario_id' => $usuario->usuario_id,
                    'persona_id' => $usuario->persona_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Email o contraseña incorrectos'
                ], 401);
            }

            Log::info('Estudiante encontrado', [
                'estudiante_id' => $estudiante->id,
                'registro_estudiante' => $estudiante->registro_estudiante,
                'ci' => $estudiante->ci
            ]);

            // Generate JWT token con custom claims explícitos
            // Esto asegura que el token incluya toda la información necesaria
            $customClaims = [
                'rol' => 'ESTUDIANTE',
                'rol_id' => $usuario->rol_id,
                'usuario_id' => $usuario->usuario_id,
                'persona_id' => $estudiante->id,
                'ci' => $estudiante->ci,
                'registro' => $estudiante->registro_estudiante
            ];

            try {
                $token = JWTAuth::customClaims($customClaims)->fromUser($estudiante);
            } catch (\Exception $e) {
                Log::error('Error generando token JWT', [
                    'error' => $e->getMessage(),
                    'estudiante_id' => $estudiante->id,
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar token de autenticación',
                    'error' => $e->getMessage()
                ], 500);
            }

            // Log to Bitacora
            Bitacora::create([
                'fecha' => now()->toDateString(),
                'tabla' => 'Estudiante',
                'codTabla' => $estudiante->registro_estudiante,
                'transaccion' => 'LOGIN_ESTUDIANTE',
                'usuario_id' => $usuario->usuario_id
            ]);

            // Verificar que el token se generó correctamente
            try {
                $payload = JWTAuth::setToken($token)->getPayload();
                Log::info('Token generado correctamente', [
                    'sub' => $payload->get('sub'),
                    'rol' => $payload->get('rol'),
                    'estudiante_id' => $estudiante->id,
                    'registro_estudiante' => $estudiante->registro_estudiante
                ]);
            } catch (\Exception $e) {
                Log::error('Error verificando token generado', [
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60,
                'user' => [
                    'id' => $estudiante->id, // Usar id real para consistencia con JWT
                    'ci' => $estudiante->ci,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'nombre_completo' => trim($estudiante->nombre . ' ' . $estudiante->apellido),
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'estado_id' => $estudiante->estado_id,
                    'provincia' => $estudiante->provincia,
                    'celular' => $estudiante->celular,
                    'email' => $usuario->email,
                    'rol' => 'ESTUDIANTE',
                    'rol_id' => $usuario->rol_id
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
     * Obtener datos del usuario autenticado (estudiante, admin o docente)
     * Este método puede ser usado por cualquier tipo de usuario autenticado
     */
    public function obtenerPerfil()
    {
        try {
            // Intentar obtener usuario desde auth:api primero
            $user = auth('api')->user();

            // Si no está en auth:api, intentar desde auth_user (agregado por RoleMiddleware)
            if (!$user) {
                $user = request()->auth_user;
            }

            // Si aún no está, intentar obtenerlo desde el token directamente
            if (!$user) {
                try {
                    $payload = JWTAuth::parseToken()->getPayload();
                    $rol = $payload->get('rol');

                    if ($rol === 'ESTUDIANTE') {
                        // El 'sub' en el token JWT para estudiantes es el id (devuelto por getJWTIdentifier)
                        $estudianteId = $payload->get('sub');
                        $user = Estudiante::find($estudianteId);
                    } else {
                        // Para admin/docente, el sub es usuario_id
                        $usuarioId = $payload->get('sub');
                        $user = \App\Models\Usuario::with('rol', 'persona')->find($usuarioId);
                    }
                } catch (\Exception $e) {
                    Log::error('Error obteniendo usuario desde token en obtenerPerfil', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Si es Estudiante
            if ($user instanceof Estudiante) {
                try {
                    $usuario = $user->usuario;
                    $fechaNacimiento = $user->fecha_nacimiento;

                    // Formatear fecha_nacimiento si es un objeto Carbon
                    if ($fechaNacimiento && method_exists($fechaNacimiento, 'format')) {
                        $fechaNacimiento = $fechaNacimiento->format('Y-m-d');
                    } elseif ($fechaNacimiento && is_string($fechaNacimiento)) {
                        // Ya es string, mantenerlo
                    } else {
                        $fechaNacimiento = null;
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $user->id,
                            'ci' => $user->ci,
                            'nombre' => $user->nombre,
                            'apellido' => $user->apellido,
                            'nombre_completo' => trim($user->nombre . ' ' . $user->apellido),
                            'celular' => $user->celular,
                            'fecha_nacimiento' => $fechaNacimiento,
                            'direccion' => $user->direccion,
                            'registro_estudiante' => $user->registro_estudiante,
                            'provincia' => $user->provincia,
                            'estado_id' => $user->estado_id,
                            'fotografia' => $user->fotografia,
                            'email' => $usuario ? $usuario->email : null,
                            'rol' => 'ESTUDIANTE',
                            'rol_id' => $usuario ? $usuario->rol_id : null
                        ]
                    ], 200);
                } catch (\Exception $e) {
                    Log::error('Error procesando datos de estudiante en obtenerPerfil', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            // Si es Usuario (Admin o Docente)
            if ($user instanceof \App\Models\Usuario) {
                try {
                    // Cargar relaciones si no están cargadas
                    if (!$user->relationLoaded('rol')) {
                        $user->load('rol');
                    }
                    if (!$user->relationLoaded('persona')) {
                        $user->load('persona');
                    }

                    $rolNombre = $user->rol ? $user->rol->nombre_rol : 'ADMIN';

                    // Cargar permisos del rol
                    $permisos = [];
                    if ($user->rol) {
                        if (!$user->rol->relationLoaded('permisos')) {
                            $user->rol->load('permisos');
                        }
                        $permisos = $user->rol->permisos->map(function($permiso) {
                            return [
                                'id' => $permiso->permiso_id,
                                'nombre_permiso' => $permiso->nombre_permiso,
                                'modulo' => $permiso->modulo,
                                'accion' => $permiso->accion
                            ];
                        })->toArray();
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'id' => $user->usuario_id,
                            'email' => $user->email,
                            'nombre' => $user->persona->nombre ?? 'Admin',
                            'apellido' => $user->persona->apellido ?? 'Sistema',
                            'nombre_completo' => trim(($user->persona->nombre ?? 'Admin') . ' ' . ($user->persona->apellido ?? 'Sistema')),
                            'ci' => $user->persona->ci ?? null,
                            'rol' => $rolNombre,
                            'rol_id' => $user->rol_id,
                            'permisos' => $permisos
                        ]
                    ], 200);
                } catch (\Exception $e) {
                    Log::error('Error procesando datos de usuario en obtenerPerfil', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Tipo de usuario no reconocido'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error obteniendo perfil', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Cerrar sesión del estudiante
     */
    public function cerrarSesion()
    {
        try {
            // Invalidar el token JWT
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
        } catch (\Exception $e) {
            // Si el token ya es inválido o no existe, igualmente retornar éxito
            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada exitosamente'
            ], 200);
        }
    }

    /**
     * Refrescar token de autenticación
     */
    public function refrescarToken()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => JWTAuth::factory()->getTTL() * 60
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
