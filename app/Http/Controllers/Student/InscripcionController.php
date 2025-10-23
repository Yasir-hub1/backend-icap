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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InscripcionController extends Controller
{
    /**
     * Registrar inscripción a un programa
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'programa_id' => 'required|exists:Programa,id',
            'grupo_id' => 'nullable|exists:Grupo,id',
            'descuento_id' => 'nullable|exists:Descuento,id',
            'numero_cuotas' => 'required|integer|min:1|max:12',
            'incluir_matricula' => 'boolean'
        ]);

        DB::beginTransaction();
        try {
            $authUser = $request->auth_user;
            $estudiante = Estudiante::findOrFail($authUser->id);

            // Validar Estado_id >= 4 (documentos aprobados)
            if ($estudiante->Estado_id < 4) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debe tener los documentos aprobados para poder inscribirse',
                    'estado_actual' => $estudiante->Estado_id,
                    'estado_requerido' => 4
                ], 400);
            }

            $programa = Programa::with(['institucion.convenios'])->findOrFail($request->programa_id);

            // Validar que el programa esté activo
            if (!$programa->institucion || $programa->institucion->estado != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'El programa no está disponible actualmente'
                ], 400);
            }

            // Si se especifica un grupo, validar cupos disponibles
            $grupo = null;
            if ($request->grupo_id) {
                $grupo = Grupo::with('estudiantes')->findOrFail($request->grupo_id);

                $cupoMaximo = 30; // Cupo máximo por grupo
                $estudiantesInscritos = $grupo->estudiantes()->count();

                if ($estudiantesInscritos >= $cupoMaximo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El grupo seleccionado no tiene cupos disponibles',
                        'cupo_maximo' => $cupoMaximo,
                        'inscritos' => $estudiantesInscritos
                    ], 400);
                }

                // Validar que el grupo sea del programa seleccionado
                if ($grupo->Programa_id != $programa->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El grupo seleccionado no pertenece al programa'
                    ], 400);
                }
            }

            // Verificar que el estudiante no esté ya inscrito en el mismo programa
            $inscripcionExistente = Inscripcion::where('Estudiante_id', $estudiante->id)
                ->where('Programa_id', $programa->id)
                ->first();

            if ($inscripcionExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya está inscrito en este programa',
                    'inscripcion_id' => $inscripcionExistente->id
                ], 400);
            }

            // Crear registro de inscripción
            $inscripcion = Inscripcion::create([
                'fecha' => now(),
                'Programa_id' => $programa->id,
                'Estudiante_id' => $estudiante->id,
                'Descuento_id' => $request->descuento_id
            ]);

            // Calcular monto total con descuentos/convenios
            $costoBase = $programa->costo;
            $montoTotal = $costoBase;

            // Aplicar descuento si existe
            if ($request->descuento_id) {
                $descuento = Descuento::find($request->descuento_id);
                if ($descuento) {
                    $montoDescuento = $costoBase * ($descuento->descuento / 100);
                    $montoTotal -= $montoDescuento;
                }
            }

            // Aplicar convenio si existe
            $convenioAplicado = null;
            foreach ($programa->institucion->convenios as $convenio) {
                if ($convenio->esta_activo && $convenio->pivot && $convenio->pivot->porcentaje_participacion > 0) {
                    $montoConvenio = $costoBase * ($convenio->pivot->porcentaje_participacion / 100);
                    $montoTotal -= $montoConvenio;
                    $convenioAplicado = $convenio;
                    break; // Solo aplicar un convenio
                }
            }

            $montoTotal = max(0, $montoTotal);

            // Generar Plan de Pagos
            $numeroCuotas = $request->numero_cuotas;
            $incluirMatricula = $request->input('incluir_matricula', true);

            $planPagos = PlanPagos::create([
                'monto_total' => $montoTotal,
                'total_cuotas' => $incluirMatricula ? $numeroCuotas + 1 : $numeroCuotas,
                'Inscripcion_id' => $inscripcion->id
            ]);

            // Generar Cuotas
            $cuotas = [];

            if ($incluirMatricula) {
                // Matrícula: 20% del monto total
                $montoMatricula = $montoTotal * 0.20;
                $cuotas[] = Cuota::create([
                    'fecha_ini' => now(),
                    'fecha_fin' => now()->addDays(15), // 15 días para pagar matrícula
                    'monto' => $montoMatricula,
                    'plan_pagos_id' => $planPagos->id
                ]);

                // Resto en cuotas mensuales
                $montoRestante = $montoTotal - $montoMatricula;
                $montoCuota = $montoRestante / $numeroCuotas;
            } else {
                $montoCuota = $montoTotal / $numeroCuotas;
            }

            // Generar cuotas mensuales
            for ($i = 0; $i < $numeroCuotas; $i++) {
                $fechaIni = now()->addMonths($incluirMatricula ? $i + 1 : $i);
                $fechaFin = now()->addMonths($incluirMatricula ? $i + 1 : $i)->endOfMonth();

                $cuotas[] = Cuota::create([
                    'fecha_ini' => $fechaIni,
                    'fecha_fin' => $fechaFin,
                    'monto' => round($montoCuota, 2),
                    'plan_pagos_id' => $planPagos->id
                ]);
            }

            // Si se especificó un grupo, agregar estudiante al grupo
            if ($grupo) {
                $grupo->estudiantes()->attach($estudiante->id, [
                    'nota' => null,
                    'estado' => 'ACTIVO'
                ]);
            }

            // Cambiar Estado_id del estudiante a 5 (Inscrito)
            $estudiante->Estado_id = 5;
            $estudiante->save();

            // Registrar en bitácora
            Bitacora::create([
                'fecha_hora' => now(),
                'tabla' => 'Inscripcion',
                'codTable' => $inscripcion->id,
                'transaccion' => "Estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) se inscribió en el programa '{$programa->nombre}'. Monto total: {$montoTotal} BOB, Cuotas: {$planPagos->total_cuotas}" . ($convenioAplicado ? ", Convenio aplicado: {$convenioAplicado->numero_convenio}" : ''),
                'Usuario_id' => $estudiante->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inscripción realizada exitosamente',
                'data' => [
                    'inscripcion' => $inscripcion->load(['programa', 'estudiante', 'descuento']),
                    'plan_pagos' => $planPagos,
                    'cuotas' => $cuotas,
                    'grupo' => $grupo,
                    'resumen' => [
                        'costo_base' => $costoBase,
                        'monto_total' => $montoTotal,
                        'convenio_aplicado' => $convenioAplicado ? $convenioAplicado->numero_convenio : null,
                        'total_cuotas' => $planPagos->total_cuotas,
                        'estado_estudiante' => $estudiante->Estado_id
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
     * Listar mis inscripciones con estado de pago
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $authUser = $request->auth_user;
            $perPage = $request->input('per_page', 15);

            $inscripciones = Inscripcion::with([
                'programa.ramaAcademica',
                'programa.tipoPrograma',
                'descuento',
                'planPagos.cuotas.pagos'
            ])
            ->where('Estudiante_id', $authUser->id)
            ->orderBy('fecha', 'desc')
            ->paginate($perPage);

            // Enriquecer con información de estado de pagos
            $inscripciones->getCollection()->transform(function ($inscripcion) {
                $planPagos = $inscripcion->planPagos;

                if ($planPagos) {
                    $cuotas = $planPagos->cuotas;
                    $totalCuotas = $cuotas->count();
                    $cuotasPagadas = $cuotas->filter(function ($cuota) {
                        return $cuota->pagos()->where('verificado', true)->exists();
                    })->count();
                    $cuotasPendientes = $totalCuotas - $cuotasPagadas;

                    $inscripcion->estado_pagos = [
                        'total_cuotas' => $totalCuotas,
                        'cuotas_pagadas' => $cuotasPagadas,
                        'cuotas_pendientes' => $cuotasPendientes,
                        'monto_total' => $planPagos->monto_total,
                        'monto_pagado' => $planPagos->monto_pagado,
                        'saldo_pendiente' => $planPagos->saldo_pendiente,
                        'porcentaje_pagado' => $planPagos->porcentaje_pagado
                    ];
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
     * Detalle de una inscripción con plan de pagos y cuotas
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $authUser = $request->auth_user;

            $inscripcion = Inscripcion::with([
                'programa.ramaAcademica',
                'programa.tipoPrograma',
                'programa.modulos',
                'descuento',
                'planPagos.cuotas' => function ($query) {
                    $query->with('pagos')->orderBy('fecha_ini');
                }
            ])
            ->where('Estudiante_id', $authUser->id)
            ->findOrFail($id);

            // Preparar detalle de cuotas con estado de pago
            $cuotasDetalle = [];
            if ($inscripcion->planPagos) {
                $cuotasDetalle = $inscripcion->planPagos->cuotas->map(function ($cuota, $index) {
                    $pagoVerificado = $cuota->pagos()->where('verificado', true)->first();

                    return [
                        'id' => $cuota->id,
                        'numero_cuota' => $index + 1,
                        'fecha_inicio' => $cuota->fecha_ini,
                        'fecha_vencimiento' => $cuota->fecha_fin,
                        'monto' => $cuota->monto,
                        'estado' => $pagoVerificado ? 'PAGADA' : ($cuota->esta_vencida ? 'VENCIDA' : 'PENDIENTE'),
                        'fecha_pago' => $pagoVerificado ? $pagoVerificado->fecha : null,
                        'pago_id' => $pagoVerificado ? $pagoVerificado->id : null
                    ];
                });
            }

            $data = [
                'inscripcion' => $inscripcion,
                'cuotas' => $cuotasDetalle,
                'resumen_pagos' => [
                    'monto_total' => $inscripcion->planPagos->monto_total ?? 0,
                    'monto_pagado' => $inscripcion->planPagos->monto_pagado ?? 0,
                    'saldo_pendiente' => $inscripcion->planPagos->saldo_pendiente ?? 0,
                    'porcentaje_pagado' => $inscripcion->planPagos->porcentaje_pagado ?? 0
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
