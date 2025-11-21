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

            return response()->json([
                'success' => true,
                'data' => $permisos
            ]);

        } catch (\Exception $e) {
            Log::error('Error al listar permisos: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar permisos'
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

            return response()->json([
                'success' => true,
                'data' => $permisos
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener permisos agrupados: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar permisos agrupados'
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
