<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\EstadoEstudiante;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class EstudianteController extends Controller
{
    /**
     * Listar estudiantes con paginación y filtros optimizados
     */
    public function index(Request $request): JsonResponse
    {
        $query = Estudiante::with([
            'estado:id,nombre_estado',
            'inscripciones.programa:id,nombre,tipoPrograma:id,nombre',
            'inscripciones.programa.institucion:id,nombre'
        ]);

        // Filtros optimizados
        if ($request->filled('buscar')) {
            $buscar = $request->get('buscar');
            $query->where(function($q) use ($buscar) {
                $q->where('ci', 'ILIKE', "%{$buscar}%")
                  ->orWhere('nombre', 'ILIKE', "%{$buscar}%")
                  ->orWhere('apellido', 'ILIKE', "%{$buscar}%")
                  ->orWhere('registro_estudiante', 'ILIKE', "%{$buscar}%");
            });
        }

        if ($request->filled('estado_id')) {
            $query->where('Estado_id', $request->get('estado_id'));
        }

        if ($request->filled('con_inscripciones')) {
            $query->conInscripciones();
        }

        // Ordenamiento optimizado
        $query->orderBy('apellido')->orderBy('nombre');

        // Paginación con caché para mejor performance
        $cacheKey = 'estudiantes_' . md5(serialize($request->all()));

        $estudiantes = Cache::remember($cacheKey, 300, function() use ($query, $request) {
            return $query->paginate($request->get('per_page', 15));
        });

        return response()->json([
            'success' => true,
            'data' => $estudiantes,
            'message' => 'Estudiantes obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener estudiante específico con todas sus relaciones
     */
    public function show(int $id): JsonResponse
    {
        $estudiante = Estudiante::with([
            'estado:id,nombre_estado',
            'inscripciones' => function($query) {
                $query->with([
                    'programa:id,nombre,duracion_meses,costo',
                    'programa.tipoPrograma:id,nombre',
                    'programa.ramaAcademica:id,nombre',
                    'programa.institucion:id,nombre',
                    'descuento:id,nombre,descuento',
                    'planPagos.cuotas.pagos'
                ])->latest();
            },
            'grupos' => function($query) {
                $query->with([
                    'programa:id,nombre',
                    'docente:id,nombre,apellido',
                    'horario:id,dias,hora_ini,hora_fin'
                ])->where('fecha_fin', '>=', now());
            },
            'documentos.tipoDocumento:id,nombre_entidad'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $estudiante,
            'message' => 'Estudiante obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo estudiante
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'ci' => 'required|string|max:20|unique:Estudiante,ci',
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:300',
            'provincia' => 'nullable|string|max:100',
            'Estado_id' => 'required|exists:Estado_estudiante,id'
        ]);

        DB::beginTransaction();
        try {
            // Generar registro de estudiante único
            $ultimoRegistro = Estudiante::max('registro_estudiante');
            $numeroSiguiente = $ultimoRegistro ? (int)substr($ultimoRegistro, -6) + 1 : 1;
            $registroEstudiante = 'EST' . str_pad($numeroSiguiente, 6, '0', STR_PAD_LEFT);

            $estudiante = Estudiante::create([
                ...$request->validated(),
                'registro_estudiante' => $registroEstudiante
            ]);

            // Limpiar caché
            Cache::forget('estudiantes_*');

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $estudiante->load('estado'),
                'message' => 'Estudiante creado exitosamente'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear estudiante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar estudiante
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $estudiante = Estudiante::findOrFail($id);

        $request->validate([
            'ci' => 'required|string|max:20|unique:Estudiante,ci,' . $id,
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'celular' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'direccion' => 'nullable|string|max:300',
            'provincia' => 'nullable|string|max:100',
            'Estado_id' => 'required|exists:Estado_estudiante,id'
        ]);

        $estudiante->update($request->validated());

        // Limpiar caché
        Cache::forget('estudiantes_*');

        return response()->json([
            'success' => true,
            'data' => $estudiante->load('estado'),
            'message' => 'Estudiante actualizado exitosamente'
        ]);
    }

    /**
     * Eliminar estudiante (soft delete lógico)
     */
    public function destroy(int $id): JsonResponse
    {
        $estudiante = Estudiante::findOrFail($id);

        // Verificar si tiene inscripciones activas
        if ($estudiante->inscripciones()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el estudiante porque tiene inscripciones activas'
            ], 422);
        }

        // Cambiar estado a inactivo en lugar de eliminar
        $estadoInactivo = EstadoEstudiante::where('nombre_estado', 'Inactivo')->first();
        if ($estadoInactivo) {
            $estudiante->update(['Estado_id' => $estadoInactivo->id]);
        }

        // Limpiar caché
        Cache::forget('estudiantes_*');

        return response()->json([
            'success' => true,
            'message' => 'Estudiante desactivado exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de estudiantes
     */
    public function estadisticas(): JsonResponse
    {
        $cacheKey = 'estadisticas_estudiantes';

        $estadisticas = Cache::remember($cacheKey, 600, function() {
            return [
                'total' => Estudiante::count(),
                'activos' => Estudiante::activos()->count(),
                'con_inscripciones' => Estudiante::conInscripciones()->count(),
                'por_estado' => EstadoEstudiante::withCount('estudiantes')->get(),
                'inscripciones_recientes' => Estudiante::whereHas('inscripciones', function($q) {
                    $q->where('fecha', '>=', now()->subDays(30));
                })->count()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas obtenidas exitosamente'
        ]);
    }

    /**
     * Buscar estudiantes para autocompletado
     */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2'
        ]);

        $query = $request->get('q');

        $estudiantes = Estudiante::select('id', 'ci', 'nombre', 'apellido', 'registro_estudiante')
            ->where(function($q) use ($query) {
                $q->where('ci', 'ILIKE', "%{$query}%")
                  ->orWhere('nombre', 'ILIKE', "%{$query}%")
                  ->orWhere('apellido', 'ILIKE', "%{$query}%")
                  ->orWhere('registro_estudiante', 'ILIKE', "%{$query}%");
            })
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $estudiantes,
            'message' => 'Búsqueda completada'
        ]);
    }
}
