<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PermisoController extends Controller
{
    /**
     * Listar todos los permisos
     */
    public function listar(Request $request)
    {
        try {
            $permisos = Permiso::activos()
                ->orderBy('modulo')
                ->orderBy('accion')
                ->get();

            // Formatear permisos para la respuesta
            $permisosFormatted = $permisos->map(function ($permiso) {
                return [
                    'id' => $permiso->permiso_id,
                    'permiso_id' => $permiso->permiso_id,
                    'nombre_permiso' => $permiso->nombre_permiso,
                    'descripcion' => $permiso->descripcion,
                    'modulo' => $permiso->modulo,
                    'accion' => $permiso->accion,
                    'activo' => $permiso->activo
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $permisosFormatted
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar permisos: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar permisos',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtener permisos agrupados por módulo
     */
    public function agrupadosPorModulo()
    {
        try {
            $permisos = Permiso::agrupadosPorModulo();

            // Convertir la colección agrupada a un array asociativo para JSON
            $permisosAgrupados = [];
            foreach ($permisos as $modulo => $permisosModulo) {
                $permisosAgrupados[$modulo] = $permisosModulo->map(function ($permiso) {
                    return [
                        'id' => $permiso->permiso_id,
                        'permiso_id' => $permiso->permiso_id,
                        'nombre_permiso' => $permiso->nombre_permiso,
                        'descripcion' => $permiso->descripcion,
                        'modulo' => $permiso->modulo,
                        'accion' => $permiso->accion,
                        'activo' => $permiso->activo
                    ];
                })->toArray();
            }

            return response()->json([
                'success' => true,
                'data' => $permisosAgrupados
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener permisos agrupados: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar permisos agrupados',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtener permisos por módulo
     */
    public function porModulo(Request $request, $modulo)
    {
        try {
            $permisos = Permiso::activos()
                ->porModulo($modulo)
                ->orderBy('accion')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $permisos
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener permisos por módulo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar permisos del módulo'
            ], 500);
        }
    }
}
