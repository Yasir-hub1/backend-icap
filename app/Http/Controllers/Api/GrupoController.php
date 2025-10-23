<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GrupoController extends Controller
{
    /**
     * Listar todos los grupos
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Grupo::with(['programa', 'docente', 'estudiantes']);

            // Filtros
            if ($request->has('programa_id')) {
                $query->where('programa_id', $request->programa_id);
            }

            if ($request->has('docente_id')) {
                $query->where('docente_id', $request->docente_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            $grupos = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $grupos,
                'message' => 'Grupos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un grupo específico
     */
    public function show($id): JsonResponse
    {
        try {
            $grupo = Grupo::with(['programa', 'docente', 'estudiantes', 'horarios'])
                         ->find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $grupo,
                'message' => 'Grupo obtenido exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo grupo
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'nombre' => 'required|string|max:255',
                'programa_id' => 'required|exists:programas,id',
                'docente_id' => 'required|exists:docentes,id',
                'capacidad_maxima' => 'required|integer|min:1',
                'estado' => 'required|in:activo,inactivo,completado'
            ]);

            $grupo = Grupo::create($request->all());
            $grupo->load(['programa', 'docente']);

            return response()->json([
                'success' => true,
                'data' => $grupo,
                'message' => 'Grupo creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un grupo
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $grupo = Grupo::find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $request->validate([
                'nombre' => 'sometimes|string|max:255',
                'programa_id' => 'sometimes|exists:programas,id',
                'docente_id' => 'sometimes|exists:docentes,id',
                'capacidad_maxima' => 'sometimes|integer|min:1',
                'estado' => 'sometimes|in:activo,inactivo,completado'
            ]);

            $grupo->update($request->all());
            $grupo->load(['programa', 'docente']);

            return response()->json([
                'success' => true,
                'data' => $grupo,
                'message' => 'Grupo actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un grupo
     */
    public function destroy($id): JsonResponse
    {
        try {
            $grupo = Grupo::find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $grupo->delete();

            return response()->json([
                'success' => true,
                'message' => 'Grupo eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar grupo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar estudiante a grupo
     */
    public function assignStudent(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'estudiante_id' => 'required|exists:estudiantes,id'
            ]);

            $grupo = Grupo::find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            // Verificar capacidad
            if ($grupo->estudiantes()->count() >= $grupo->capacidad_maxima) {
                return response()->json([
                    'success' => false,
                    'message' => 'El grupo ha alcanzado su capacidad máxima'
                ], 400);
            }

            $grupo->estudiantes()->attach($request->estudiante_id);

            return response()->json([
                'success' => true,
                'message' => 'Estudiante asignado al grupo exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar estudiante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover estudiante de grupo
     */
    public function removeStudent(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'estudiante_id' => 'required|exists:estudiantes,id'
            ]);

            $grupo = Grupo::find($id);

            if (!$grupo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $grupo->estudiantes()->detach($request->estudiante_id);

            return response()->json([
                'success' => true,
                'message' => 'Estudiante removido del grupo exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al remover estudiante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener grupos por docente
     */
    public function getByDocente($docenteId): JsonResponse
    {
        try {
            $grupos = Grupo::with(['programa', 'estudiantes'])
                          ->where('docente_id', $docenteId)
                          ->get();

            return response()->json([
                'success' => true,
                'data' => $grupos,
                'message' => 'Grupos del docente obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos del docente: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener grupos activos
     */
    public function getActive(): JsonResponse
    {
        try {
            $grupos = Grupo::with(['programa', 'docente', 'estudiantes'])
                          ->where('estado', 'activo')
                          ->get();

            return response()->json([
                'success' => true,
                'data' => $grupos,
                'message' => 'Grupos activos obtenidos exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener grupos activos: ' . $e->getMessage()
            ], 500);
        }
    }
}
