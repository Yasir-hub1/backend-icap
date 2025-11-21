<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\Programa;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Bitacora;
use App\Models\Usuario;
use App\Models\Institucion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReporteController extends Controller
{
    /**
     * Reporte de convenios activos
     */
    public function conveniosActivos(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');
            $tipoConvenioId = $request->get('tipo_convenio_id');

            $query = Convenio::with(['tipoConvenio', 'instituciones'])
                ->activos(); // Usa el scope que filtra por fecha_fin >= now()

            if ($fechaDesde) {
                $query->where('fecha_ini', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->where('fecha_fin', '<=', $fechaHasta);
            }

            if ($tipoConvenioId) {
                $query->where('tipo_convenio_id', $tipoConvenioId);
            }

            $convenios = $query->orderBy('fecha_ini', 'desc')->get();

            $estadisticas = [
                'total_convenios' => $convenios->count(),
                'convenios_por_tipo' => $convenios->groupBy('tipo_convenio_id')->map(function ($group) {
                    return [
                        'tipo' => $group->first()->tipoConvenio->nombre_tipo ?? 'N/A',
                        'cantidad' => $group->count()
                    ];
                })->values(),
                'instituciones_participantes' => $convenios->flatMap(function ($convenio) {
                    return $convenio->instituciones;
                })->unique('id')->count(),
                'monto_total_convenios' => $convenios->sum(function ($convenio) {
                    return $convenio->instituciones->sum(function ($inst) {
                        return $inst->pivot->monto_asignado ?? 0;
                    });
                })
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'convenios' => $convenios->map(function ($convenio) {
                        $montoTotal = $convenio->instituciones->sum(function ($inst) {
                            return $inst->pivot->monto_asignado ?? 0;
                        });
                        $estado = $convenio->fecha_fin && $convenio->fecha_fin >= now()->toDateString() ? 'activo' : 'vencido';

                        return [
                            'id' => $convenio->convenio_id,
                            'numero_convenio' => $convenio->numero_convenio,
                            'tipo' => $convenio->tipoConvenio->nombre_tipo ?? 'N/A',
                            'fecha_inicio' => $convenio->fecha_ini,
                            'fecha_fin' => $convenio->fecha_fin,
                            'monto_total' => $montoTotal,
                            'estado' => $estado,
                            'instituciones' => $convenio->instituciones->map(function ($inst) {
                                return [
                                    'id' => $inst->id,
                                    'nombre' => $inst->nombre,
                                    'porcentaje' => $inst->pivot->porcentaje_participacion ?? 0
                                ];
                            })
                        ];
                    }),
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de convenios activos generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de programas ofrecidos
     */
    public function programasOfrecidos(Request $request): JsonResponse
    {
        try {
            $ramaAcademicaId = $request->get('rama_academica_id');
            $tipoProgramaId = $request->get('tipo_programa_id');
            $institucionId = $request->get('institucion_id');
            $estado = $request->get('estado', 'activo');

            $query = Programa::with(['ramaAcademica', 'tipoPrograma', 'institucion', 'grupos']);

            if ($ramaAcademicaId) {
                $query->where('rama_academica_id', $ramaAcademicaId);
            }

            if ($tipoProgramaId) {
                $query->where('tipo_programa_id', $tipoProgramaId);
            }

            if ($institucionId) {
                $query->where('institucion_id', $institucionId);
            }

            if ($estado === 'activo') {
                $query->whereHas('institucion', function ($q) {
                    $q->where('estado', 'activo');
                });
            }

            $programas = $query->orderBy('nombre')->get();

            $estadisticas = [
                'total_programas' => $programas->count(),
                'programas_por_rama' => $programas->groupBy('rama_academica_id')->map(function ($group) {
                    return [
                        'rama' => $group->first()->ramaAcademica->nombre ?? 'N/A',
                        'cantidad' => $group->count()
                    ];
                })->values(),
                'programas_por_tipo' => $programas->groupBy('tipo_programa_id')->map(function ($group) {
                    return [
                        'tipo' => $group->first()->tipoPrograma->nombre ?? 'N/A',
                        'cantidad' => $group->count()
                    ];
                })->values(),
                'total_inscripciones' => Inscripcion::whereIn('programa_id', $programas->pluck('id'))->count(),
                'total_grupos_activos' => $programas->sum(function ($programa) {
                    return $programa->grupos->where('fecha_fin', '>=', now())->count();
                })
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'programas' => $programas->map(function ($programa) {
                        return [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre,
                            'rama_academica' => $programa->ramaAcademica->nombre ?? 'N/A',
                            'tipo_programa' => $programa->tipoPrograma->nombre ?? 'N/A',
                            'institucion' => $programa->institucion->nombre ?? 'N/A',
                            'costo' => $programa->costo,
                            'duracion_meses' => $programa->duracion_meses,
                            'total_inscripciones' => Inscripcion::where('programa_id', $programa->id)->count(),
                            'grupos_activos' => $programa->grupos->where('fecha_fin', '>=', now())->count(),
                            'grupos_total' => $programa->grupos->count()
                        ];
                    }),
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de programas ofrecidos generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de estado académico de estudiantes
     */
    public function estadoAcademicoEstudiantes(Request $request): JsonResponse
    {
        try {
            $programaId = $request->get('programa_id');
            $estadoId = $request->get('estado_id');
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');

            $query = Estudiante::with(['estadoEstudiante', 'inscripciones.programa', 'grupos']);

            if ($estadoId) {
                $query->where('estado_id', $estadoId);
            }

            if ($fechaDesde) {
                $query->whereHas('inscripciones', function ($q) use ($fechaDesde) {
                    $q->where('fecha', '>=', $fechaDesde);
                });
            }

            if ($fechaHasta) {
                $query->whereHas('inscripciones', function ($q) use ($fechaHasta) {
                    $q->where('fecha', '<=', $fechaHasta);
                });
            }

            $estudiantes = $query->get();

            if ($programaId) {
                $estudiantes = $estudiantes->filter(function ($estudiante) use ($programaId) {
                    return $estudiante->inscripciones->contains(function ($inscripcion) use ($programaId) {
                        return $inscripcion->programa_id == $programaId;
                    });
                });
            }

            // Obtener datos de rendimiento académico
            $estudiantesConRendimiento = $estudiantes->map(function ($estudiante) {
                $grupos = $estudiante->grupos;
                $totalGrupos = $grupos->count();
                $aprobados = $grupos->filter(function ($grupo) {
                    return $grupo->pivot->estado === 'APROBADO';
                })->count();
                $reprobados = $grupos->filter(function ($grupo) {
                    return $grupo->pivot->estado === 'REPROBADO';
                })->count();
                $promedioNotas = $grupos->whereNotNull('pivot.nota')->avg('pivot.nota');

                return [
                    'registro_estudiante' => $estudiante->registro_estudiante,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'ci' => $estudiante->ci,
                    'estado' => $estudiante->estadoEstudiante->nombre_estado ?? 'N/A',
                    'total_inscripciones' => $estudiante->inscripciones->count(),
                    'programas' => $estudiante->inscripciones->map(function ($inscripcion) {
                        return $inscripcion->programa->nombre ?? 'N/A';
                    })->unique()->values(),
                    'rendimiento' => [
                        'total_grupos' => $totalGrupos,
                        'aprobados' => $aprobados,
                        'reprobados' => $reprobados,
                        'promedio_notas' => $promedioNotas ? round($promedioNotas, 2) : null,
                        'tasa_aprobacion' => $totalGrupos > 0 ? round(($aprobados / $totalGrupos) * 100, 2) : 0
                    ]
                ];
            });

            $estadisticas = [
                'total_estudiantes' => $estudiantes->count(),
                'estudiantes_por_estado' => $estudiantes->groupBy('estado_id')->map(function ($group) {
                    return [
                        'estado' => $group->first()->estadoEstudiante->nombre_estado ?? 'N/A',
                        'cantidad' => $group->count()
                    ];
                })->values(),
                'total_inscripciones' => $estudiantes->sum(function ($e) {
                    return $e->inscripciones->count();
                }),
                'promedio_aprobacion' => $estudiantesConRendimiento->filter(function ($e) {
                    return $e['rendimiento']['total_grupos'] > 0;
                })->avg('rendimiento.tasa_aprobacion')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'estudiantes' => $estudiantesConRendimiento->values(),
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de estado académico generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de movimientos financieros
     */
    public function movimientosFinancieros(Request $request): JsonResponse
    {
        try {
            $fechaDesde = $request->get('fecha_desde', now()->startOfMonth()->toDateString());
            $fechaHasta = $request->get('fecha_hasta', now()->toDateString());
            $tipoMovimiento = $request->get('tipo_movimiento'); // 'ingresos', 'egresos', 'todos'

            // Obtener pagos en el rango de fechas
            $pagos = Pago::with(['cuota.planPago.inscripcion.estudiante', 'cuota.planPago.inscripcion.programa'])
                ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                ->orderBy('fecha', 'desc')
                ->get();

            // Obtener planes de pago creados
            $planesPago = DB::table('plan_pago')
                ->whereBetween('created_at', [$fechaDesde, $fechaHasta])
                ->get();

            // Obtener descuentos aplicados
            $descuentos = DB::table('descuento')
                ->whereBetween('created_at', [$fechaDesde, $fechaHasta])
                ->get();

            $ingresos = $pagos->where('verificado', true)->sum('monto');
            $ingresosPendientes = $pagos->where('verificado', false)->sum('monto');
            $totalIngresos = $pagos->sum('monto');

            $estadisticas = [
                'periodo' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta
                ],
                'ingresos' => [
                    'total_verificado' => $ingresos,
                    'total_pendiente' => $ingresosPendientes,
                    'total' => $totalIngresos,
                    'cantidad_pagos' => $pagos->count(),
                    'cantidad_pagos_verificados' => $pagos->where('verificado', true)->count(),
                    'promedio_pago' => $pagos->count() > 0 ? round($pagos->avg('monto'), 2) : 0
                ],
                'planes_pago' => [
                    'total_creados' => $planesPago->count(),
                    'monto_total' => $planesPago->sum('monto_total')
                ],
                'descuentos' => [
                    'total_aplicados' => $descuentos->count(),
                    'monto_descontado' => $descuentos->sum('descuento')
                ],
                'movimientos_por_dia' => $pagos->groupBy(function ($pago) {
                    return Carbon::parse($pago->fecha)->format('Y-m-d');
                })->map(function ($group, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'cantidad' => $group->count(),
                        'monto_total' => $group->sum('monto'),
                        'monto_verificado' => $group->where('verificado', true)->sum('monto')
                    ];
                })->values()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'pagos' => $pagos->map(function ($pago) {
                        return [
                            'id' => $pago->id,
                            'fecha' => $pago->fecha,
                            'monto' => $pago->monto,
                            'verificado' => $pago->verificado,
                            'estudiante' => ($pago->cuota->planPago->inscripcion->estudiante->nombre ?? '') . ' ' . ($pago->cuota->planPago->inscripcion->estudiante->apellido ?? ''),
                            'programa' => $pago->cuota->planPago->inscripcion->programa->nombre ?? 'N/A',
                            'token' => $pago->token
                        ];
                    }),
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de movimientos financieros generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de actividad por usuario
     */
    public function actividadPorUsuario(Request $request): JsonResponse
    {
        try {
            $usuarioId = $request->get('usuario_id');
            $fechaDesde = $request->get('fecha_desde', now()->subDays(30)->toDateString());
            $fechaHasta = $request->get('fecha_hasta', now()->toDateString());

            $query = Bitacora::with(['usuario.persona'])
                ->whereBetween('fecha', [$fechaDesde, $fechaHasta]);

            if ($usuarioId) {
                $query->where('usuario_id', $usuarioId);
            }

            $registros = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->get();

            $actividadPorUsuario = $registros->groupBy('usuario_id')->map(function ($group, $userId) {
                $usuario = $group->first()->usuario;
                $persona = $usuario ? $usuario->persona : null;
                return [
                    'usuario_id' => $userId,
                    'usuario' => $usuario ? [
                        'id' => $usuario->usuario_id,
                        'ci' => $persona ? $persona->ci : 'N/A',
                        'nombre' => $persona ? $persona->nombre : 'N/A',
                        'apellido' => $persona ? $persona->apellido : 'N/A',
                        'email' => $usuario->email ?? 'N/A'
                    ] : null,
                    'total_acciones' => $group->count(),
                    'acciones_por_tabla' => $group->groupBy('tabla')->map(function ($tableGroup) {
                        return [
                            'tabla' => $tableGroup->first()->tabla,
                            'cantidad' => $tableGroup->count()
                        ];
                    })->values(),
                    'ultima_actividad' => $group->first()->fecha
                ];
            })->values();

            $estadisticas = [
                'periodo' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta
                ],
                'total_registros' => $registros->count(),
                'usuarios_activos' => $actividadPorUsuario->count(),
                'acciones_por_tabla' => $registros->groupBy('tabla')->map(function ($group) {
                    return [
                        'tabla' => $group->first()->tabla,
                        'cantidad' => $group->count()
                    ];
                })->values(),
                'acciones_por_dia' => $registros->groupBy('fecha')->map(function ($group, $fecha) {
                    return [
                        'fecha' => $fecha,
                        'cantidad' => $group->count()
                    ];
                })->values()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'actividad_por_usuario' => $actividadPorUsuario,
                    'registros' => $registros->map(function ($registro) {
                        return [
                            'bitacora_id' => $registro->bitacora_id,
                            'fecha' => $registro->fecha,
                            'tabla' => $registro->tabla,
                            'codTabla' => $registro->codTabla,
                            'transaccion' => $registro->transaccion,
                            'usuario' => $registro->usuario && $registro->usuario->persona ? [
                                'id' => $registro->usuario->usuario_id,
                                'nombre' => $registro->usuario->persona->nombre ?? 'N/A',
                                'apellido' => $registro->usuario->persona->apellido ?? 'N/A'
                            ] : null
                        ];
                    }),
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de actividad por usuario generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de actividad por institución
     */
    public function actividadPorInstitucion(Request $request): JsonResponse
    {
        try {
            $institucionId = $request->get('institucion_id');
            $fechaDesde = $request->get('fecha_desde', now()->subDays(30)->toDateString());
            $fechaHasta = $request->get('fecha_hasta', now()->toDateString());

            $query = Institucion::with(['programas.inscripciones', 'convenios']);

            if ($institucionId) {
                $query->where('id', $institucionId);
            }

            $instituciones = $query->get();

            $actividadPorInstitucion = $instituciones->map(function ($institucion) use ($fechaDesde, $fechaHasta) {
                // Obtener inscripciones en el período
                $inscripciones = $institucion->programas->flatMap(function ($programa) use ($fechaDesde, $fechaHasta) {
                    return $programa->inscripciones->filter(function ($inscripcion) use ($fechaDesde, $fechaHasta) {
                        return $inscripcion->fecha >= $fechaDesde && $inscripcion->fecha <= $fechaHasta;
                    });
                });

                // Obtener convenios activos (fecha_fin >= now())
                $conveniosActivos = $institucion->convenios->filter(function ($convenio) {
                    return $convenio->fecha_fin && $convenio->fecha_fin >= now()->toDateString();
                });

                // Obtener programas activos
                $programasActivos = $institucion->programas->filter(function ($programa) {
                    return $programa->institucion && $programa->institucion->estado === 'activo';
                });

                return [
                    'institucion_id' => $institucion->id,
                    'nombre' => $institucion->nombre,
                    'estado' => $institucion->estado,
                    'actividad' => [
                        'total_inscripciones' => $inscripciones->count(),
                        'inscripciones_periodo' => $inscripciones->count(),
                        'convenios_activos' => $conveniosActivos->count(),
                        'programas_activos' => $programasActivos->count(),
                        'total_programas' => $institucion->programas->count()
                    ],
                    'programas' => $programasActivos->map(function ($programa) {
                        return [
                            'id' => $programa->id,
                            'nombre' => $programa->nombre,
                            'inscripciones' => $programa->inscripciones->count()
                        ];
                    })
                ];
            });

            $estadisticas = [
                'periodo' => [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta
                ],
                'total_instituciones' => $instituciones->count(),
                'total_inscripciones' => $instituciones->sum(function ($inst) {
                    return $inst->programas->sum(function ($prog) {
                        return $prog->inscripciones->count();
                    });
                }),
                'total_convenios_activos' => $instituciones->sum(function ($inst) {
                    return $inst->convenios->filter(function ($convenio) {
                        return $convenio->fecha_fin && $convenio->fecha_fin >= now()->toDateString();
                    })->count();
                }),
                'total_programas_activos' => $instituciones->sum(function ($inst) {
                    return $inst->programas->filter(function ($prog) {
                        return $prog->institucion && $prog->institucion->estado === 'activo';
                    })->count();
                })
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'actividad_por_institucion' => $actividadPorInstitucion,
                    'estadisticas' => $estadisticas
                ],
                'message' => 'Reporte de actividad por institución generado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos para filtros de reportes
     */
    public function datosFormulario(): JsonResponse
    {
        try {
            $tiposConvenio = DB::table('tipo_convenio')->select('tipo_convenio_id as id', 'nombre_tipo as nombre')->get();
            $ramasAcademicas = DB::table('rama_academica')->select('id', 'nombre')->get();
            $tiposPrograma = DB::table('tipo_programa')->select('id', 'nombre')->get();
            $estadosEstudiante = DB::table('estado_estudiante')->select('id', 'nombre_estado as nombre')->get();
            $instituciones = DB::table('institucion')->select('id', 'nombre')->get();
            $usuarios = DB::table('usuario')
                ->leftJoin('persona', 'usuario.persona_id', '=', 'persona.id')
                ->select('usuario.usuario_id as id', 'usuario.email', 'persona.ci')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'tipos_convenio' => $tiposConvenio,
                    'ramas_academicas' => $ramasAcademicas,
                    'tipos_programa' => $tiposPrograma,
                    'estados_estudiante' => $estadosEstudiante,
                    'instituciones' => $instituciones,
                    'usuarios' => $usuarios
                ],
                'message' => 'Datos de formulario obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos: ' . $e->getMessage()
            ], 500);
        }
    }
}
