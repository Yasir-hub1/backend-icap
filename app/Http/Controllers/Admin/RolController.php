<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RolController extends Controller
{
    /**
     * Listar todos los roles con sus permisos
     */
    public function listar(Request $request)
    {
        try {
            // Eager loading de permisos y contar usuarios
            $roles = Rol::with('permisos')
                ->withCount('usuarios')
                ->orderBy('nombre_rol')
                ->get();

            // Formatear la respuesta para incluir todos los datos necesarios
            $rolesFormatted = $roles->map(function ($rol) {
                return [
                    'id' => $rol->rol_id,
                    'rol_id' => $rol->rol_id,
                    'nombre_rol' => $rol->nombre_rol,
                    'descripcion' => $rol->descripcion,
                    'activo' => $rol->activo,
                    'usuarios_count' => $rol->usuarios_count,
                    'permisos' => $rol->permisos->map(function ($permiso) {
                        return [
                            'id' => $permiso->permiso_id,
                            'permiso_id' => $permiso->permiso_id,
                            'nombre_permiso' => $permiso->nombre_permiso,
                            'descripcion' => $permiso->descripcion,
                            'modulo' => $permiso->modulo,
                            'accion' => $permiso->accion,
                            'activo' => $permiso->activo
                        ];
                    })->toArray(),
                    'created_at' => $rol->created_at,
                    'updated_at' => $rol->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $rolesFormatted
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar roles: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar roles',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Crear nuevo rol
     */
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre_rol' => 'required|string|max:50|unique:roles,nombre_rol',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'boolean'
        ], [
            'nombre_rol.required' => 'El nombre del rol es requerido',
            'nombre_rol.unique' => 'Ya existe un rol con este nombre',
            'nombre_rol.max' => 'El nombre no puede exceder 50 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rol = Rol::create([
                'nombre_rol' => $request->nombre_rol,
                'descripcion' => $request->descripcion,
                'activo' => $request->activo ?? true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rol creado exitosamente',
                'data' => $rol->load('permisos')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear rol: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear rol'
            ], 500);
        }
    }

    /**
     * Obtener rol específico
     */
    public function obtener($id)
    {
        try {
            $rol = Rol::with(['permisos', 'usuarios'])
                ->withCount('usuarios')
                ->find($id);

            if (!$rol) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rol no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $rol
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener rol: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar rol'
            ], 500);
        }
    }

    /**
     * Actualizar rol
     */
    public function actualizar(Request $request, $id)
    {
        $rol = Rol::find($id);

        if (!$rol) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre_rol' => 'required|string|max:50|unique:roles,nombre_rol,' . $id . ',rol_id',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'boolean'
        ], [
            'nombre_rol.required' => 'El nombre del rol es requerido',
            'nombre_rol.unique' => 'Ya existe un rol con este nombre',
            'nombre_rol.max' => 'El nombre no puede exceder 50 caracteres'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rol->update([
                'nombre_rol' => $request->nombre_rol,
                'descripcion' => $request->descripcion,
                'activo' => $request->activo ?? $rol->activo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rol actualizado exitosamente',
                'data' => $rol->load('permisos')
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar rol: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar rol'
            ], 500);
        }
    }

    /**
     * Eliminar rol
     */
    public function eliminar($id)
    {
        $rol = Rol::find($id);

        if (!$rol) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado'
            ], 404);
        }

        // Verificar si el rol tiene usuarios asignados
        if ($rol->usuarios()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el rol porque tiene usuarios asignados'
            ], 400);
        }

        // No permitir eliminar roles del sistema (ADMIN, DOCENTE, ESTUDIANTE)
        $rolesProtegidos = ['ADMIN', 'Administrador', 'DOCENTE', 'Docente', 'ESTUDIANTE', 'Estudiante'];
        if (in_array($rol->nombre_rol, $rolesProtegidos)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un rol del sistema. Solo se pueden eliminar roles personalizados.'
            ], 400);
        }

        try {
            $rol->delete();

            return response()->json([
                'success' => true,
                'message' => 'Rol eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar rol: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar rol'
            ], 500);
        }
    }

    /**
     * Actualizar permisos del rol
     */
    public function actualizarPermisos(Request $request, $id)
    {
        $rol = Rol::find($id);

        if (!$rol) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'permisos' => 'required|array',
            'permisos.*' => 'integer|exists:permisos,permiso_id'
        ], [
            'permisos.required' => 'Los permisos son requeridos',
            'permisos.array' => 'Los permisos deben ser un array',
            'permisos.*.integer' => 'Cada permiso debe ser un ID válido',
            'permisos.*.exists' => 'Uno o más permisos no existen'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Sincronizar permisos del rol
            $permisosData = [];
            foreach ($request->permisos as $permisoId) {
                $permisosData[$permisoId] = ['activo' => true];
            }

            $rol->permisos()->sync($permisosData);

            return response()->json([
                'success' => true,
                'message' => 'Permisos actualizados exitosamente',
                'data' => $rol->load('permisos')
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar permisos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar permisos'
            ], 500);
        }
    }
}
