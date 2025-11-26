<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Models\Programa;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\PlanPagos;
use App\Models\Cuota;
use App\Models\Bitacora;
use App\Models\Descuento;
use App\Models\Horario;
use App\Traits\EnviaNotificaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InscripcionController extends Controller
{
    use EnviaNotificaciones;
    /**
     * Listar programas activos disponibles para inscripción
     */
    public function programasDisponibles(Request $request)
    {
        try {
            $authUser = $request->auth_user;
            // El auth_user ya es una instancia de Estudiante desde el middleware
            // Usar el id directamente (que es el id de persona, ya que estudiante hereda de persona)
            $estudiante = $authUser instanceof Estudiante ? $authUser : Estudiante::findOrFail($authUser->id);

            // Validar que el estudiante tenga documentos aprobados
            if ($estudiante->estado_id < 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe tener los documentos aprobados para poder inscribirse',
                    'estado_actual' => $estudiante->estado_id,
                    'estado_requerido' => 4
                ], 400);
            }

            // Obtener programas activos con grupos disponibles
            $programas = Programa::with([
                'institucion',
                'ramaAcademica',
                'tipoPrograma',
                'grupos' => function ($query) {
                    $query->where('fecha_fin', '>=', now())
                          ->with(['horarios', 'docente', 'modulo'])
                          ->withCount('estudiantes');
                }
            ])
            ->whereHas('institucion', function ($q) {
                $q->where('estado', 1);
            })
            ->get()
            ->map(function ($programa) {
                // Filtrar grupos con cupos disponibles
                $gruposDisponibles = $programa->grupos->filter(function ($grupo) {
                    return $grupo->estudiantes_count < 30; // Cupo máximo
                })->map(function ($grupo) {
                    return [
                        'id' => $grupo->grupo_id,
                        'modulo' => $grupo->modulo ? $grupo->modulo->nombre : null,
                        'docente' => $grupo->docente ? $grupo->docente->nombre . ' ' . $grupo->docente->apellido : null,
                        'fecha_ini' => $grupo->fecha_ini,
                        'fecha_fin' => $grupo->fecha_fin,
                        'cupos_disponibles' => 30 - $grupo->estudiantes_count,
                        'horarios' => $grupo->horarios->map(function ($horario) {
                            return [
                                'id' => $horario->horario_id,
                                'dias' => $horario->dias,
                                'hora_ini' => $horario->hora_ini ? Carbon::parse($horario->hora_ini)->format('H:i') : null,
                                'hora_fin' => $horario->hora_fin ? Carbon::parse($horario->hora_fin)->format('H:i') : null,
                                'aula' => $horario->pivot->aula ?? null
                            ];
                        })
                    ];
                });

                return [
                    'id' => $programa->id,
                    'nombre' => $programa->nombre,
                    'costo' => $programa->costo,
                    'duracion_meses' => $programa->duracion_meses,
                    'institucion' => $programa->institucion ? $programa->institucion->nombre : null,
                    'rama_academica' => $programa->ramaAcademica ? $programa->ramaAcademica->nombre : null,
                    'tipo_programa' => $programa->tipoPrograma ? $programa->tipoPrograma->nombre : null,
                    'grupos_disponibles' => $gruposDisponibles->values(),
                    'total_grupos' => $gruposDisponibles->count()
                ];
            })
            ->filter(function ($programa) {
                return $programa['total_grupos'] > 0; // Solo programas con grupos disponibles
            })
            ->values();

            return response()->json([
                'success' => true,
                'message' => 'Programas disponibles obtenidos exitosamente',
                'data' => $programas
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programas disponibles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar conflictos de horario antes de inscribir
     */
    public function verificarHorarios(Request $request)
    {
        $request->validate([
            'grupo_id' => 'required|exists:grupo,grupo_id'
        ]);

        try {
            $authUser = $request->auth_user;
            // El auth_user ya es una instancia de Estudiante desde el middleware
            $estudiante = $authUser instanceof Estudiante ? $authUser : Estudiante::findOrFail($authUser->id);

            $grupoNuevo = Grupo::with(['horarios', 'programa', 'modulo'])->findOrFail($request->grupo_id);

            // Obtener todos los grupos en los que el estudiante ya está inscrito
            // La tabla grupo_estudiante usa estudiante_id (id de Estudiante, no registro_estudiante)
            $gruposInscritos = Grupo::whereHas('estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiante_id', $estudiante->id);
            })
            ->with(['horarios', 'programa', 'modulo'])
            ->get();

            $conflictos = [];

            foreach ($gruposInscritos as $grupoInscrito) {
                foreach ($grupoInscrito->horarios as $horarioInscrito) {
                    foreach ($grupoNuevo->horarios as $horarioNuevo) {
                        // Verificar si hay conflicto de días
                        $diasInscrito = explode(',', str_replace(' ', '', strtoupper($horarioInscrito->dias ?? '')));
                        $diasNuevo = explode(',', str_replace(' ', '', strtoupper($horarioNuevo->dias ?? '')));
                        $diasComunes = array_intersect($diasInscrito, $diasNuevo);

                        if (!empty($diasComunes)) {
                            // Verificar si hay conflicto de horas
                            $horaIniInscrito = $horarioInscrito->hora_ini ? Carbon::parse($horarioInscrito->hora_ini)->format('H:i:s') : null;
                            $horaFinInscrito = $horarioInscrito->hora_fin ? Carbon::parse($horarioInscrito->hora_fin)->format('H:i:s') : null;
                            $horaIniNuevo = $horarioNuevo->hora_ini ? Carbon::parse($horarioNuevo->hora_ini)->format('H:i:s') : null;
                            $horaFinNuevo = $horarioNuevo->hora_fin ? Carbon::parse($horarioNuevo->hora_fin)->format('H:i:s') : null;

                            if ($horaIniInscrito && $horaFinInscrito && $horaIniNuevo && $horaFinNuevo) {
                                // Verificar solapamiento de horarios
                                if (($horaIniNuevo >= $horaIniInscrito && $horaIniNuevo < $horaFinInscrito) ||
                                    ($horaFinNuevo > $horaIniInscrito && $horaFinNuevo <= $horaFinInscrito) ||
                                    ($horaIniNuevo <= $horaIniInscrito && $horaFinNuevo >= $horaFinInscrito)) {
                                    $conflictos[] = [
                                        'grupo_conflicto' => [
                                            'id' => $grupoInscrito->grupo_id,
                                            'modulo' => $grupoInscrito->modulo ? $grupoInscrito->modulo->nombre : null,
                                            'programa' => $grupoInscrito->programa ? $grupoInscrito->programa->nombre : null
                                        ],
                                        'horario_conflicto' => [
                                            'dias' => $horarioInscrito->dias ?? implode(', ', $diasComunes),
                                            'hora_ini' => $horarioInscrito->hora_ini ? Carbon::parse($horarioInscrito->hora_ini)->format('H:i') : $horaIniInscrito,
                                            'hora_fin' => $horarioInscrito->hora_fin ? Carbon::parse($horarioInscrito->hora_fin)->format('H:i') : $horaFinInscrito
                                        ],
                                        'horario_nuevo' => [
                                            'dias' => $horarioNuevo->dias,
                                            'hora_ini' => $horarioNuevo->hora_ini ? Carbon::parse($horarioNuevo->hora_ini)->format('H:i') : $horaIniNuevo,
                                            'hora_fin' => $horarioNuevo->hora_fin ? Carbon::parse($horarioNuevo->hora_fin)->format('H:i') : $horaFinNuevo
                                        ]
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'tiene_conflictos' => !empty($conflictos),
                'conflictos' => $conflictos,
                'message' => empty($conflictos)
                    ? 'No hay conflictos de horario'
                    : 'Se encontraron conflictos de horario'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar horarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar inscripción a un programa
     */
    public function crear(Request $request)
    {
        return $this->store($request);
    }

    public function store(Request $request)
    {
        $request->validate([
            'programa_id' => 'required|exists:programa,id',
            'grupo_id' => 'required|exists:grupo,grupo_id',
            'numero_cuotas' => 'required|integer|min:1|max:12'
        ]);

        DB::beginTransaction();
        try {
            $authUser = $request->auth_user;
            // El auth_user ya es una instancia de Estudiante desde el middleware
            // Usar el id directamente (que es el id de persona, ya que estudiante hereda de persona)
            $estudiante = $authUser instanceof Estudiante ? $authUser : Estudiante::findOrFail($authUser->id);

            // Validar estado_id >= 4 (documentos aprobados)
            if ($estudiante->estado_id < 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe tener los documentos aprobados para poder inscribirse',
                    'estado_actual' => $estudiante->estado_id,
                    'estado_requerido' => 4
                ], 400);
            }

            $programa = Programa::with(['institucion'])->findOrFail($request->programa_id);

            // Validar que el programa esté activo
            if (!$programa->institucion || $programa->institucion->estado != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'El programa no está disponible actualmente'
                ], 400);
            }

            // Validar grupo
            $grupo = Grupo::with(['estudiantes', 'horarios', 'programa'])->findOrFail($request->grupo_id);

            // Validar que el grupo sea del programa seleccionado
            if ($grupo->programa_id != $programa->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El grupo seleccionado no pertenece al programa'
                ], 400);
            }

            // Validar cupos disponibles
            $cupoMaximo = 30;
            $estudiantesInscritos = $grupo->estudiantes()->count();
            if ($estudiantesInscritos >= $cupoMaximo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El grupo seleccionado no tiene cupos disponibles',
                    'cupo_maximo' => $cupoMaximo,
                    'inscritos' => $estudiantesInscritos
                ], 400);
            }

            // Verificar conflictos de horario
            // La tabla grupo_estudiante usa estudiante_id (id de Estudiante, no registro_estudiante)
            $gruposInscritos = Grupo::whereHas('estudiantes', function ($query) use ($estudiante) {
                $query->where('estudiante_id', $estudiante->id);
            })
            ->with(['horarios', 'programa', 'modulo'])
            ->get();

            foreach ($gruposInscritos as $grupoInscrito) {
                foreach ($grupoInscrito->horarios as $horarioInscrito) {
                    foreach ($grupo->horarios as $horarioNuevo) {
                        $diasInscrito = explode(',', str_replace(' ', '', strtoupper($horarioInscrito->dias ?? '')));
                        $diasNuevo = explode(',', str_replace(' ', '', strtoupper($horarioNuevo->dias ?? '')));
                        $diasComunes = array_intersect($diasInscrito, $diasNuevo);

                        if (!empty($diasComunes)) {
                            $horaIniInscrito = $horarioInscrito->hora_ini ? Carbon::parse($horarioInscrito->hora_ini)->format('H:i:s') : null;
                            $horaFinInscrito = $horarioInscrito->hora_fin ? Carbon::parse($horarioInscrito->hora_fin)->format('H:i:s') : null;
                            $horaIniNuevo = $horarioNuevo->hora_ini ? Carbon::parse($horarioNuevo->hora_ini)->format('H:i:s') : null;
                            $horaFinNuevo = $horarioNuevo->hora_fin ? Carbon::parse($horarioNuevo->hora_fin)->format('H:i:s') : null;

                            if ($horaIniInscrito && $horaFinInscrito && $horaIniNuevo && $horaFinNuevo) {
                                if (($horaIniNuevo >= $horaIniInscrito && $horaIniNuevo < $horaFinInscrito) ||
                                    ($horaFinNuevo > $horaIniInscrito && $horaFinNuevo <= $horaFinInscrito) ||
                                    ($horaIniNuevo <= $horaIniInscrito && $horaFinNuevo >= $horaFinInscrito)) {
                                    return response()->json([
                                        'success' => false,
                                        'message' => 'El horario del grupo seleccionado entra en conflicto con otros grupos en los que ya está inscrito',
                                        'grupo_conflicto' => [
                                            'id' => $grupoInscrito->grupo_id,
                                            'modulo' => $grupoInscrito->modulo ? $grupoInscrito->modulo->nombre : null,
                                            'programa' => $grupoInscrito->programa ? $grupoInscrito->programa->nombre : null
                                        ]
                                    ], 400);
                                }
                            }
                        }
                    }
                }
            }

            // Verificar que el estudiante no esté ya inscrito en el mismo programa
            $inscripcionExistente = Inscripcion::where('estudiante_id', $estudiante->id)
                ->where('programa_id', $programa->id)
                ->first();

            if ($inscripcionExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya está inscrito en este programa',
                    'inscripcion_id' => $inscripcionExistente->id
                ], 400);
            }

            // Calcular monto total
            $costoBase = $programa->costo ?? 0;
            $montoTotal = $costoBase;

            // Aplicar descuento si existe
            $descuento = null;
            if ($request->descuento_id) {
                $descuento = Descuento::find($request->descuento_id);
                if ($descuento && $descuento->descuento > 0) {
                    $montoDescuento = $costoBase * ($descuento->descuento / 100);
                    $montoTotal -= $montoDescuento;
                }
            }

            $montoTotal = max(0, $montoTotal);

            // Crear registro de inscripción
            $inscripcion = Inscripcion::create([
                'fecha' => now()->toDateString(),
                'estudiante_id' => $estudiante->id,
                'programa_id' => $programa->id
            ]);

            // Generar Plan de Pagos
            $numeroCuotas = $request->numero_cuotas;
            $planPagos = PlanPagos::create([
                'inscripcion_id' => $inscripcion->id,
                'monto_total' => $montoTotal,
                'total_cuotas' => $numeroCuotas
            ]);

            // Asociar descuento si existe
            if ($descuento) {
                $descuento->inscripcion_id = $inscripcion->id;
                $descuento->save();
            }

            // Generar Cuotas mensuales
            $montoCuota = $montoTotal / $numeroCuotas;
            $cuotas = [];
            for ($i = 0; $i < $numeroCuotas; $i++) {
                $fechaIni = now()->addMonths($i)->startOfMonth();
                $fechaFin = now()->addMonths($i)->endOfMonth();

                $cuotas[] = Cuota::create([
                    'fecha_ini' => $fechaIni->toDateString(),
                    'fecha_fin' => $fechaFin->toDateString(),
                    'monto' => round($montoCuota, 2),
                    'plan_pago_id' => $planPagos->id
                ]);
            }

            // Agregar estudiante al grupo
            // La tabla grupo_estudiante usa estudiante_id (id de Estudiante, no registro_estudiante)
            $grupo->estudiantes()->attach($estudiante->id, [
                'nota' => null,
                'estado' => 'ACTIVO'
            ]);

            // Cambiar estado_id del estudiante a 5 (Inscrito)
            $estudiante->estado_id = 5;
            $estudiante->save();

            // Registrar en bitácora
            $usuario = $estudiante->usuario;
            if ($usuario) {
                Bitacora::create([
                    'fecha' => now()->toDateString(),
                    'tabla' => 'Inscripcion',
                    'codTabla' => $inscripcion->id,
                    'transaccion' => "Estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) se inscribió en el programa '{$programa->nombre}'. Monto total: {$montoTotal} BOB, Cuotas: {$numeroCuotas}",
                    'usuario_id' => $usuario->usuario_id
                ]);
            }

            // Enviar notificaciones
            $this->notificarNuevaInscripcion($estudiante, $programa->nombre, $inscripcion->id);
            $this->notificarPlanPagoCreado($estudiante, $montoTotal, $numeroCuotas, $planPagos->id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscripción realizada exitosamente',
                'data' => [
                    'inscripcion' => $inscripcion->load(['programa', 'estudiante', 'descuento']),
                    'plan_pago' => $planPagos,
                    'cuotas' => $cuotas,
                    'grupo' => $grupo->load(['modulo', 'docente', 'horarios']),
                    'resumen' => [
                        'costo_base' => $costoBase,
                        'monto_total' => $montoTotal,
                        'total_cuotas' => $numeroCuotas,
                        'estado_estudiante' => $estudiante->estado_id
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al realizar inscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar mis inscripciones
     */
    public function listar(Request $request)
    {
        return $this->index($request);
    }

    public function index(Request $request)
    {
        try {
            $authUser = $request->auth_user;
            $perPage = $request->input('per_page', 15);

            // El auth_user ya es una instancia de Estudiante desde el middleware
            // Usar el id directamente (que es el id de persona, ya que estudiante hereda de persona)
            $estudiante = $authUser instanceof Estudiante ? $authUser : Estudiante::findOrFail($authUser->id);

            $inscripciones = Inscripcion::with([
                'programa.ramaAcademica',
                'programa.tipoPrograma',
                'programa.institucion',
                'descuento',
                'planPago.cuotas.pagos'
            ])
            ->where('estudiante_id', $estudiante->id)
            ->orderBy('fecha', 'desc')
            ->paginate($perPage);

            // Obtener grupos del estudiante agrupados por programa
            $gruposEstudiante = $estudiante->grupos()
                ->with(['modulo', 'docente', 'horarios' => function($query) {
                    $query->withPivot('aula');
                }])
                ->get()
                ->groupBy('programa_id');

            // Enriquecer con información de estado de pagos y grupos
            $inscripciones->getCollection()->transform(function ($inscripcion) use ($gruposEstudiante) {
                $planPago = $inscripcion->planPago;

                if ($planPago) {
                    $cuotas = $planPago->cuotas;
                    $totalCuotas = $cuotas->count();
                    $cuotasPagadas = $cuotas->filter(function ($cuota) {
                        return $cuota->pagos()->exists();
                    })->count();

                    $inscripcion->estado_pagos = [
                        'total_cuotas' => $totalCuotas,
                        'cuotas_pagadas' => $cuotasPagadas,
                        'cuotas_pendientes' => $totalCuotas - $cuotasPagadas,
                        'monto_total' => $planPago->monto_total,
                        'monto_pagado' => $planPago->monto_pagado ?? 0,
                        'monto_pendiente' => $planPago->monto_pendiente ?? $planPago->monto_total
                    ];
                }

                // Agregar grupos del estudiante para este programa
                $programaId = $inscripcion->programa_id;
                if ($gruposEstudiante->has($programaId)) {
                    $gruposPrograma = $gruposEstudiante->get($programaId);
                    // Tomar el primer grupo (o el más reciente)
                    $grupo = $gruposPrograma->first();
                    if ($grupo) {
                        $inscripcion->grupo = [
                            'id' => $grupo->grupo_id,
                            'modulo' => $grupo->modulo ? [
                                'id' => $grupo->modulo->modulo_id,
                                'nombre' => $grupo->modulo->nombre
                            ] : null,
                            'docente' => $grupo->docente ? [
                                'id' => $grupo->docente->id,
                                'nombre' => $grupo->docente->nombre,
                                'apellido' => $grupo->docente->apellido,
                                'nombre_completo' => "{$grupo->docente->nombre} {$grupo->docente->apellido}"
                            ] : null,
                            'fecha_ini' => $grupo->fecha_ini,
                            'fecha_fin' => $grupo->fecha_fin,
                            'horarios' => $grupo->horarios->map(function ($horario) {
                                return [
                                    'horario_id' => $horario->horario_id,
                                    'dias' => $horario->dias,
                                    'hora_ini' => $horario->hora_ini ? \Carbon\Carbon::parse($horario->hora_ini)->format('H:i') : null,
                                    'hora_fin' => $horario->hora_fin ? \Carbon\Carbon::parse($horario->hora_fin)->format('H:i') : null,
                                    'aula' => $horario->pivot->aula ?? null
                                ];
                            })
                        ];
                    }
                }

                return $inscripcion;
            });

            return response()->json([
                'success' => true,
                'message' => 'Inscripciones obtenidas exitosamente',
                'data' => $inscripciones
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener inscripciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalle de una inscripción
     */
    public function obtener(Request $request, $id)
    {
        return $this->show($request, $id);
    }

    public function show(Request $request, $id)
    {
        try {
            $authUser = $request->auth_user;
            // El auth_user ya es una instancia de Estudiante desde el middleware
            // Usar el id directamente (que es el id de persona, ya que estudiante hereda de persona)
            $estudiante = $authUser instanceof Estudiante ? $authUser : Estudiante::findOrFail($authUser->id);

            $inscripcion = Inscripcion::with([
                'programa.ramaAcademica',
                'programa.tipoPrograma',
                'programa.modulos',
                'descuento',
                'planPago.cuotas' => function ($query) {
                    $query->with('pagos')->orderBy('fecha_ini');
                }
            ])
            ->where('estudiante_id', $estudiante->id)
            ->findOrFail($id);

            // Preparar detalle de cuotas
            $cuotasDetalle = [];
            if ($inscripcion->planPago) {
                $cuotasDetalle = $inscripcion->planPago->cuotas->map(function ($cuota, $index) {
                    return [
                        'id' => $cuota->id,
                        'numero_cuota' => $index + 1,
                        'fecha_inicio' => $cuota->fecha_ini,
                        'fecha_vencimiento' => $cuota->fecha_fin,
                        'monto' => $cuota->monto,
                        'estado' => $cuota->esta_pagada ? 'PAGADA' : ($cuota->esta_vencida ? 'VENCIDA' : 'PENDIENTE'),
                        'pagos' => $cuota->pagos->map(function ($pago) {
                            return [
                                'id' => $pago->id,
                                'fecha' => $pago->fecha,
                                'monto' => $pago->monto,
                                'token' => $pago->token
                            ];
                        })
                    ];
                });
            }

            $data = [
                'inscripcion' => $inscripcion,
                'cuotas' => $cuotasDetalle,
                'resumen_pagos' => [
                    'monto_total' => $inscripcion->planPago->monto_total ?? 0,
                    'monto_pagado' => $inscripcion->planPago->monto_pagado ?? 0,
                    'monto_pendiente' => $inscripcion->planPago->monto_pendiente ?? 0
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Detalle de inscripción obtenido exitosamente',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle de inscripción',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
