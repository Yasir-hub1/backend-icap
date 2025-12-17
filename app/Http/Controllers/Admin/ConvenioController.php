<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\TipoConvenio;
use App\Models\Institucion;
use App\Traits\RegistraBitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConvenioController extends Controller
{
    use RegistraBitacora;
    /**
     * Listar convenios con paginación y filtros
     */
    public function listar(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search', '');
            $tipoConvenioId = $request->get('tipo_convenio_id');
            $estado = $request->get('estado');
            $fechaDesde = $request->get('fecha_desde');
            $fechaHasta = $request->get('fecha_hasta');
            $sortBy = $request->get('sort_by', 'fecha_ini');
            $sortDirection = $request->get('sort_direction', 'desc');

            // Validar dirección de ordenamiento
            if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
                $sortDirection = 'desc';
            }

            // Validar columnas permitidas para ordenamiento
            $allowedSortColumns = ['numero_convenio', 'objeto_convenio', 'fecha_ini', 'fecha_fin', 'fecha_firma'];
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'fecha_ini';
            }

            $query = Convenio::with([
                'tipoConvenio:tipo_convenio_id,nombre_tipo',
                'instituciones' => function($q) {
                    $q->select('id', 'nombre')
                      ->withPivot('porcentaje_participacion', 'monto_asignado', 'estado');
                }
            ]);

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('numero_convenio', 'ILIKE', "%{$search}%")
                      ->orWhere('objeto_convenio', 'ILIKE', "%{$search}%");
                });
            }

            if ($tipoConvenioId) {
                $query->where('tipo_convenio_id', $tipoConvenioId);
            }

            if ($estado !== null) {
                $query->where('estado', $estado);
            }

            if ($fechaDesde) {
                $query->where('fecha_ini', '>=', $fechaDesde);
            }

            if ($fechaHasta) {
                $query->where('fecha_fin', '<=', $fechaHasta);
            }

            $convenios = $query->orderBy($sortBy, $sortDirection)
                              ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $convenios->items(),
                    'current_page' => $convenios->currentPage(),
                    'per_page' => $convenios->perPage(),
                    'total' => $convenios->total(),
                    'last_page' => $convenios->lastPage(),
                    'from' => $convenios->firstItem(),
                    'to' => $convenios->lastItem()
                ],
                'message' => 'Convenios obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener convenios. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Obtener convenio por ID
     */
    public function obtener($id): JsonResponse
    {
        try {
            // Convertir a entero si es string
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de convenio inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $convenio = Convenio::with([
                'tipoConvenio',
                'instituciones' => function($query) {
                    $query->withPivot('porcentaje_participacion', 'monto_asignado', 'estado');
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $convenio,
                'message' => 'Convenio obtenido exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Convenio no encontrado'
            ], 404)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Crear nuevo convenio
     */
    public function crear(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'objeto_convenio' => 'required|string|max:500',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'fecha_firma' => 'nullable|date|before_or_equal:today',
            'moneda' => 'nullable|string|max:10',
            'observaciones' => 'nullable|string|max:1000',
            'tipo_convenio_id' => 'required|integer|exists:tipo_convenio,tipo_convenio_id',
            'instituciones' => 'required|array|min:1',
            'instituciones.*.id' => 'required|integer|exists:institucion,id',
            'instituciones.*.porcentaje_participacion' => 'nullable|numeric|min:0|max:100',
            'instituciones.*.monto_asignado' => 'nullable|numeric|min:0'
        ], [
            'objeto_convenio.required' => 'El objeto del convenio es obligatorio',
            'objeto_convenio.max' => 'El objeto del convenio no puede tener más de 500 caracteres',
            'fecha_ini.required' => 'La fecha de inicio es obligatoria',
            'fecha_ini.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.required' => 'La fecha de fin es obligatoria',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'fecha_firma.date' => 'La fecha de firma debe ser una fecha válida',
            'fecha_firma.before_or_equal' => 'La fecha de firma no puede ser posterior a hoy',
            'moneda.max' => 'La moneda no puede tener más de 10 caracteres',
            'observaciones.max' => 'Las observaciones no pueden tener más de 1000 caracteres',
            'tipo_convenio_id.required' => 'El tipo de convenio es obligatorio',
            'tipo_convenio_id.integer' => 'El tipo de convenio debe ser un número válido',
            'tipo_convenio_id.exists' => 'El tipo de convenio seleccionado no existe',
            'instituciones.required' => 'Debe agregar al menos una institución',
            'instituciones.min' => 'Debe agregar al menos una institución',
            'instituciones.*.id.required' => 'Debe seleccionar una institución',
            'instituciones.*.id.integer' => 'El ID de la institución debe ser un número válido',
            'instituciones.*.id.exists' => 'La institución seleccionada no existe',
            'instituciones.*.porcentaje_participacion.numeric' => 'El porcentaje de participación debe ser un número',
            'instituciones.*.porcentaje_participacion.min' => 'El porcentaje de participación no puede ser menor a 0',
            'instituciones.*.porcentaje_participacion.max' => 'El porcentaje de participación no puede ser mayor a 100',
            'instituciones.*.monto_asignado.numeric' => 'El monto asignado debe ser un número',
            'instituciones.*.monto_asignado.min' => 'El monto asignado no puede ser menor a 0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación. Por favor, revisa los campos marcados',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        try {
            DB::beginTransaction();

            // Generar número de convenio automáticamente
            $anioActual = date('Y');
            $siguienteNumero = 1;

            // Buscar el último convenio del año actual
            // Usar ILIKE para PostgreSQL (case-insensitive) y manejar tanto string como integer
            try {
                $ultimoConvenio = Convenio::whereRaw("CAST(numero_convenio AS TEXT) ILIKE ?", ["CONV-{$anioActual}-%"])
                    ->orderBy('convenio_id', 'desc')
                    ->first();
            } catch (\Exception $e) {
                // Si falla, intentar como string directo
                $ultimoConvenio = Convenio::where('numero_convenio', 'ILIKE', "CONV-{$anioActual}-%")
                    ->orderBy('convenio_id', 'desc')
                    ->first();
            }

            if ($ultimoConvenio) {
                // Extraer el número del último convenio (formato: CONV-YYYY-NNN)
                $numeroStr = is_string($ultimoConvenio->numero_convenio)
                    ? $ultimoConvenio->numero_convenio
                    : (string)$ultimoConvenio->numero_convenio;

                if (preg_match('/CONV-\d{4}-(\d+)/', $numeroStr, $matches)) {
                    $siguienteNumero = intval($matches[1]) + 1;
                } else {
                    // Si no sigue el formato, buscar todos los del año y encontrar el mayor
                    try {
                        $conveniosAnio = Convenio::whereRaw("CAST(numero_convenio AS TEXT) ILIKE ?", ["CONV-{$anioActual}-%"])
                            ->get();
                    } catch (\Exception $e) {
                        $conveniosAnio = Convenio::where('numero_convenio', 'ILIKE', "CONV-{$anioActual}-%")
                            ->get();
                    }

                    $maxNumero = 0;
                    foreach ($conveniosAnio as $conv) {
                        $numStr = is_string($conv->numero_convenio)
                            ? $conv->numero_convenio
                            : (string)$conv->numero_convenio;

                        if (preg_match('/CONV-\d{4}-(\d+)/', $numStr, $m)) {
                            $num = intval($m[1]);
                            if ($num > $maxNumero) {
                                $maxNumero = $num;
                            }
                        }
                    }
                    $siguienteNumero = $maxNumero + 1;
                }
            }

            $numeroConvenio = sprintf('CONV-%s-%03d', $anioActual, $siguienteNumero);

            $data = $validator->validated();
            $instituciones = $data['instituciones'];
            unset($data['instituciones']);

            // Agregar número de convenio generado
            $data['numero_convenio'] = $numeroConvenio;

            // Crear convenio
            $convenio = Convenio::create($data);

            // Registrar en bitácora
            $this->registrarCreacion('convenio', $convenio->convenio_id, "Convenio: {$convenio->numero_convenio} - {$convenio->objeto_convenio}");

            // Asociar instituciones
            $institucionesData = [];
            foreach ($instituciones as $institucion) {
                $institucionesData[$institucion['id']] = [
                    'porcentaje_participacion' => $institucion['porcentaje_participacion'] ?? null,
                    'monto_asignado' => $institucion['monto_asignado'] ?? null,
                    'estado' => 1
                ];
            }
            $convenio->instituciones()->attach($institucionesData);

            Cache::forget('convenios_*');
            Cache::forget('catalogos_tipos_convenio');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $convenio->load(['tipoConvenio', 'instituciones']),
                'message' => 'Convenio creado exitosamente'
            ], 201)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear convenio', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);

            $errorMessage = config('app.debug')
                ? 'Error al crear convenio: ' . $e->getMessage()
                : 'Error al crear convenio. Por favor, intenta nuevamente';

            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => config('app.debug') ? [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ] : null
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Actualizar convenio
     */
    public function actualizar(Request $request, $id): JsonResponse
    {
        // Convertir a entero si es string
        $id = (int) $id;

        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ID de convenio inválido'
            ], 400)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }

        $convenio = Convenio::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'numero_convenio' => 'required|string|max:50|unique:convenio,numero_convenio,' . $id . ',convenio_id',
            'objeto_convenio' => 'required|string|max:500',
            'fecha_ini' => 'required|date',
            'fecha_fin' => 'required|date|after:fecha_ini',
            'fecha_firma' => 'nullable|date|before_or_equal:today',
            'moneda' => 'nullable|string|max:10',
            'observaciones' => 'nullable|string|max:1000',
            'tipo_convenio_id' => 'required|integer|exists:tipo_convenio,tipo_convenio_id',
            'instituciones' => 'nullable|array|min:1',
            'instituciones.*.id' => 'required|integer|exists:institucion,id',
            'instituciones.*.porcentaje_participacion' => 'nullable|numeric|min:0|max:100',
            'instituciones.*.monto_asignado' => 'nullable|numeric|min:0'
        ], [
            'numero_convenio.required' => 'El número de convenio es obligatorio',
            'numero_convenio.unique' => 'Este número de convenio ya está registrado',
            'numero_convenio.max' => 'El número de convenio no puede tener más de 50 caracteres',
            'objeto_convenio.required' => 'El objeto del convenio es obligatorio',
            'objeto_convenio.max' => 'El objeto del convenio no puede tener más de 500 caracteres',
            'fecha_ini.required' => 'La fecha de inicio es obligatoria',
            'fecha_ini.date' => 'La fecha de inicio debe ser una fecha válida',
            'fecha_fin.required' => 'La fecha de fin es obligatoria',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'fecha_firma.date' => 'La fecha de firma debe ser una fecha válida',
            'fecha_firma.before_or_equal' => 'La fecha de firma no puede ser posterior a hoy',
            'moneda.max' => 'La moneda no puede tener más de 10 caracteres',
            'observaciones.max' => 'Las observaciones no pueden tener más de 1000 caracteres',
            'tipo_convenio_id.required' => 'El tipo de convenio es obligatorio',
            'tipo_convenio_id.integer' => 'El tipo de convenio debe ser un número válido',
            'tipo_convenio_id.exists' => 'El tipo de convenio seleccionado no existe',
            'instituciones.min' => 'Debe agregar al menos una institución',
            'instituciones.*.id.required' => 'Debe seleccionar una institución',
            'instituciones.*.id.integer' => 'El ID de la institución debe ser un número válido',
            'instituciones.*.id.exists' => 'La institución seleccionada no existe',
            'instituciones.*.porcentaje_participacion.numeric' => 'El porcentaje de participación debe ser un número',
            'instituciones.*.porcentaje_participacion.min' => 'El porcentaje de participación no puede ser menor a 0',
            'instituciones.*.porcentaje_participacion.max' => 'El porcentaje de participación no puede ser mayor a 100',
            'instituciones.*.monto_asignado.numeric' => 'El monto asignado debe ser un número',
            'instituciones.*.monto_asignado.min' => 'El monto asignado no puede ser menor a 0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $instituciones = $data['instituciones'] ?? null;
            unset($data['instituciones']);

            // Actualizar convenio
            $convenio->update($data);

            // Actualizar instituciones si se proporcionaron
            if ($instituciones !== null) {
                $institucionesData = [];
                foreach ($instituciones as $institucion) {
                    $institucionesData[$institucion['id']] = [
                        'porcentaje_participacion' => $institucion['porcentaje_participacion'] ?? null,
                        'monto_asignado' => $institucion['monto_asignado'] ?? null,
                        'estado' => 1
                    ];
                }
                $convenio->instituciones()->sync($institucionesData);
            }

            Cache::forget('convenios_*');

            DB::commit();

            // Registrar en bitácora
            $convenioActualizado = $convenio->fresh();
            $this->registrarEdicion('convenio', $convenioActualizado->convenio_id, "Convenio: {$convenioActualizado->numero_convenio} - {$convenioActualizado->objeto_convenio}");

            return response()->json([
                'success' => true,
                'data' => $convenioActualizado->load(['tipoConvenio', 'instituciones']),
                'message' => 'Convenio actualizado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar convenio. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Eliminar convenio
     */
    public function eliminar($id): JsonResponse
    {
        try {
            // Convertir a entero si es string
            $id = (int) $id;

            if ($id <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID de convenio inválido'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $convenio = Convenio::findOrFail($id);

            // Verificar si tiene documentos asociados
            if ($convenio->documentos()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el convenio porque tiene documentos asociados'
                ], 422);
            }

            DB::beginTransaction();

            // Guardar información antes de eliminar para bitácora
            $convenioInfo = "{$convenio->numero_convenio} - {$convenio->objeto_convenio}";
            $convenioId = $convenio->convenio_id;

            // Desasociar instituciones
            $convenio->instituciones()->detach();

            $convenio->delete();

            Cache::forget('convenios_*');

            DB::commit();

            // Registrar en bitácora
            $this->registrarEliminacion('convenio', $convenioId, "Convenio: {$convenioInfo}");

            return response()->json([
                'success' => true,
                'message' => 'Convenio eliminado exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar convenio. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Agregar institución al convenio
     */
    public function agregarInstitucion(Request $request, int $id): JsonResponse
    {
        $convenio = Convenio::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'institucion_id' => 'required|exists:institucion,id',
            'porcentaje_participacion' => 'nullable|numeric|min:0|max:100',
            'monto_asignado' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Verificar que la institución no esté ya asociada
            if ($convenio->instituciones()->where('id', $data['institucion_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La institución ya está asociada a este convenio'
                ], 422);
            }

            $convenio->instituciones()->attach($data['institucion_id'], [
                'porcentaje_participacion' => $data['porcentaje_participacion'] ?? null,
                'monto_asignado' => $data['monto_asignado'] ?? null,
                'estado' => 1
            ]);

            Cache::forget('convenios_*');

            return response()->json([
                'success' => true,
                'message' => 'Institución agregada al convenio exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar institución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover institución del convenio
     */
    public function removerInstitucion(int $id, int $institucionId): JsonResponse
    {
        try {
            $convenio = Convenio::findOrFail($id);

            if (!$convenio->instituciones()->where('id', $institucionId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La institución no está asociada a este convenio'
                ], 422);
            }

            $convenio->instituciones()->detach($institucionId);

            Cache::forget('convenios_*');

            return response()->json([
                'success' => true,
                'message' => 'Institución removida del convenio exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al remover institución: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener datos para formularios
     */
    public function datosFormulario(): JsonResponse
    {
        try {
            // Generar siguiente número de convenio
            $anioActual = date('Y');
            $siguienteNumero = 1;

            // Buscar el último convenio del año actual
            // Usar ILIKE para PostgreSQL (case-insensitive) y manejar tanto string como integer
            try {
                $ultimoConvenio = Convenio::whereRaw("CAST(numero_convenio AS TEXT) ILIKE ?", ["CONV-{$anioActual}-%"])
                    ->orderBy('convenio_id', 'desc')
                    ->first();
            } catch (\Exception $e) {
                // Si falla, intentar como string directo
                $ultimoConvenio = Convenio::where('numero_convenio', 'ILIKE', "CONV-{$anioActual}-%")
                    ->orderBy('convenio_id', 'desc')
                    ->first();
            }

            if ($ultimoConvenio) {
                // Extraer el número del último convenio (formato: CONV-YYYY-NNN)
                $numeroStr = is_string($ultimoConvenio->numero_convenio)
                    ? $ultimoConvenio->numero_convenio
                    : (string)$ultimoConvenio->numero_convenio;

                if (preg_match('/CONV-\d{4}-(\d+)/', $numeroStr, $matches)) {
                    $siguienteNumero = intval($matches[1]) + 1;
                } else {
                    // Si no sigue el formato, buscar todos los del año y encontrar el mayor
                    try {
                        $conveniosAnio = Convenio::whereRaw("CAST(numero_convenio AS TEXT) ILIKE ?", ["CONV-{$anioActual}-%"])
                            ->get();
                    } catch (\Exception $e) {
                        $conveniosAnio = Convenio::where('numero_convenio', 'ILIKE', "CONV-{$anioActual}-%")
                            ->get();
                    }

                    $maxNumero = 0;
                    foreach ($conveniosAnio as $conv) {
                        $numStr = is_string($conv->numero_convenio)
                            ? $conv->numero_convenio
                            : (string)$conv->numero_convenio;

                        if (preg_match('/CONV-\d{4}-(\d+)/', $numStr, $m)) {
                            $num = intval($m[1]);
                            if ($num > $maxNumero) {
                                $maxNumero = $num;
                            }
                        }
                    }
                    $siguienteNumero = $maxNumero + 1;
                }
            }

            $siguienteNumeroConvenio = sprintf('CONV-%s-%03d', $anioActual, $siguienteNumero);

            $datos = [
                'siguiente_numero_convenio' => $siguienteNumeroConvenio,
                'tipos_convenio' => TipoConvenio::select('tipo_convenio_id as id', 'nombre_tipo', 'descripcion')
                    ->orderBy('nombre_tipo')
                    ->get(),
                'instituciones' => Institucion::select('id', 'nombre', 'ciudad_id')
                    ->where('estado', 1)
                    ->with('ciudad:id,nombre_ciudad')
                    ->orderBy('nombre')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $datos,
                'message' => 'Datos obtenidos exitosamente'
            ], 200)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener datos. Por favor, intenta nuevamente'
            ], 500)->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }
}

