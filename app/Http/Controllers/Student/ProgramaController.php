<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Programa;
use App\Models\Convenio;
use App\Models\Estudiante;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgramaController extends Controller
{
    /**
     * Listado de programas activos con convenios vigentes aplicables al estudiante
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $authUser = $request->auth_user;
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $ramaId = $request->input('rama_id');
            $tipoId = $request->input('tipo_id');

            // Obtener información del estudiante para filtrar por institución/convenio
            $estudiante = Estudiante::find($authUser->id);

            $programas = Programa::with([
                'ramaAcademica',
                'tipoPrograma',
                'institucion',
                'modulos',
                'grupos' => function ($query) {
                    $query->where('fecha_fin', '>=', now())
                          ->with(['docente', 'horario']);
                }
            ])
            ->activos()
            ->when($search, function ($query) use ($search) {
                $query->where('nombre', 'ILIKE', "%{$search}%");
            })
            ->when($ramaId, function ($query) use ($ramaId) {
                $query->where('Rama_academica_id', $ramaId);
            })
            ->when($tipoId, function ($query) use ($tipoId) {
                $query->where('Tipo_programa_id', $tipoId);
            })
            // Filtrar programas con convenios vigentes
            ->whereHas('institucion.convenios', function ($query) {
                $query->where('estado', 1)
                      ->where('fecha_fin', '>=', now());
            })
            ->orderBy('nombre')
            ->paginate($perPage);

            // Enriquecer con información de convenios aplicables
            $programas->getCollection()->transform(function ($programa) use ($estudiante) {
                // Obtener convenios vigentes de la institución del programa
                $conveniosAplicables = Convenio::whereHas('instituciones', function ($query) use ($programa) {
                    $query->where('Institucion_id', $programa->Institucion_id);
                })
                ->where('estado', 1)
                ->where('fecha_fin', '>=', now())
                ->get(['id', 'numero_convenio', 'objeto_convenio', 'fecha_fin']);

                $programa->convenios_aplicables = $conveniosAplicables;
                $programa->grupos_activos = $programa->grupos->count();
                $programa->cupos_disponibles = $programa->grupos->sum(function ($grupo) {
                    // Asumiendo un cupo máximo de 30 por grupo
                    return max(0, 30 - $grupo->estudiantes()->count());
                });

                return $programa;
            });

            return response()->json([
                'success' => true,
                'message' => 'Programas activos obtenidos exitosamente',
                'data' => $programas
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener programas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detalle de un programa con módulos, horarios, docentes y costo total
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            $authUser = $request->auth_user;
            $estudiante = Estudiante::find($authUser->id);

            $programa = Programa::with([
                'ramaAcademica',
                'tipoPrograma',
                'institucion.convenios' => function ($query) {
                    $query->where('estado', 1)
                          ->where('fecha_fin', '>=', now());
                },
                'modulos',
                'grupos' => function ($query) {
                    $query->where('fecha_fin', '>=', now())
                          ->with([
                              'docente:id,nombre,apellido,area_de_especializacion',
                              'horario',
                              'estudiantes:id'
                          ]);
                },
                'version'
            ])->findOrFail($id);

            // Validar que el programa esté activo
            if (!$programa->institucion || $programa->institucion->estado != 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este programa no está disponible actualmente'
                ], 404);
            }

            // Calcular costo con descuentos aplicables
            $costoBase = $programa->costo;
            $descuentos = [];
            $costoFinal = $costoBase;

            // Obtener convenios aplicables y calcular descuentos
            foreach ($programa->institucion->convenios as $convenio) {
                $pivot = $convenio->pivot;
                if ($pivot && $pivot->porcentaje_participacion > 0) {
                    $descuento = $costoBase * ($pivot->porcentaje_participacion / 100);
                    $descuentos[] = [
                        'convenio' => $convenio->numero_convenio,
                        'objeto' => $convenio->objeto_convenio,
                        'porcentaje' => $pivot->porcentaje_participacion,
                        'monto_descuento' => $descuento
                    ];
                    $costoFinal -= $descuento;
                }
            }

            // Preparar información de grupos
            $gruposActivos = $programa->grupos->map(function ($grupo) {
                return [
                    'id' => $grupo->id,
                    'fecha_inicio' => $grupo->fecha_ini,
                    'fecha_fin' => $grupo->fecha_fin,
                    'docente' => $grupo->docente ? [
                        'nombre' => $grupo->docente->nombre . ' ' . $grupo->docente->apellido,
                        'especializacion' => $grupo->docente->area_de_especializacion
                    ] : null,
                    'horario' => $grupo->horario ? [
                        'dia' => $grupo->horario->dia,
                        'hora_inicio' => $grupo->horario->hora_ini,
                        'hora_fin' => $grupo->horario->hora_fin,
                        'aula' => $grupo->horario->aula
                    ] : null,
                    'estudiantes_inscritos' => $grupo->estudiantes->count(),
                    'cupos_disponibles' => max(0, 30 - $grupo->estudiantes->count())
                ];
            });

            $data = [
                'id' => $programa->id,
                'nombre' => $programa->nombre,
                'duracion_meses' => $programa->duracion_meses,
                'total_modulos' => $programa->total_modulos,
                'rama_academica' => $programa->ramaAcademica,
                'tipo_programa' => $programa->tipoPrograma,
                'institucion' => [
                    'id' => $programa->institucion->id,
                    'nombre' => $programa->institucion->nombre,
                    'estado' => $programa->institucion->estado
                ],
                'modulos' => $programa->modulos,
                'grupos_activos' => $gruposActivos,
                'costos' => [
                    'costo_base' => $costoBase,
                    'descuentos' => $descuentos,
                    'costo_final' => max(0, $costoFinal),
                    'moneda' => 'BOB'
                ],
                'convenios_aplicables' => $programa->institucion->convenios,
                'version' => $programa->version
            ];

            return response()->json([
                'success' => true,
                'message' => 'Detalle del programa obtenido exitosamente',
                'data' => $data
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalle del programa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
