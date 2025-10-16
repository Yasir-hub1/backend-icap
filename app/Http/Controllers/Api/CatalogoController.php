<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pais;
use App\Models\Provincia;
use App\Models\Ciudad;
use App\Models\TipoPrograma;
use App\Models\RamaAcademica;
use App\Models\Modulo;
use App\Models\EstadoEstudiante;
use App\Models\TipoConvenio;
use App\Models\TipoDocumento;
use App\Models\Descuento;
use App\Models\Version;
use App\Models\Horario;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CatalogoController extends Controller
{
    /**
     * Obtener países
     */
    public function paises(): JsonResponse
    {
        $paises = Cache::remember('catalogos_paises', 3600, function() {
            return Pais::select('id', 'nombre_pais', 'codigo_iso')
                ->activos()
                ->orderBy('nombre_pais')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $paises,
            'message' => 'Países obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener provincias por país
     */
    public function provincias(int $paisId): JsonResponse
    {
        $provincias = Cache::remember("catalogos_provincias_{$paisId}", 3600, function() use ($paisId) {
            return Provincia::select('id', 'nombre_provincia', 'codigo_provincia')
                ->delPais($paisId)
                ->orderBy('nombre_provincia')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $provincias,
            'message' => 'Provincias obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener ciudades por provincia
     */
    public function ciudades(int $provinciaId): JsonResponse
    {
        $ciudades = Cache::remember("catalogos_ciudades_{$provinciaId}", 3600, function() use ($provinciaId) {
            return Ciudad::select('id', 'nombre_ciudad', 'codigo_postal')
                ->deProvincia($provinciaId)
                ->orderBy('nombre_ciudad')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $ciudades,
            'message' => 'Ciudades obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener tipos de programa
     */
    public function tiposPrograma(): JsonResponse
    {
        $tipos = Cache::remember('catalogos_tipos_programa', 3600, function() {
            return TipoPrograma::select('id', 'nombre')
                ->conProgramasActivos()
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos,
            'message' => 'Tipos de programa obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener ramas académicas
     */
    public function ramasAcademicas(): JsonResponse
    {
        $ramas = Cache::remember('catalogos_ramas_academicas', 3600, function() {
            return RamaAcademica::select('id', 'nombre')
                ->conProgramasActivos()
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $ramas,
            'message' => 'Ramas académicas obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener módulos
     */
    public function modulos(): JsonResponse
    {
        $modulos = Cache::remember('catalogos_modulos', 3600, function() {
            return Modulo::select('id', 'nombre', 'credito', 'horas_academicas')
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $modulos,
            'message' => 'Módulos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener estados de estudiante
     */
    public function estadosEstudiante(): JsonResponse
    {
        $estados = Cache::remember('catalogos_estados_estudiante', 3600, function() {
            return EstadoEstudiante::select('id', 'nombre_estado')
                ->orderBy('nombre_estado')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $estados,
            'message' => 'Estados de estudiante obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener tipos de convenio
     */
    public function tiposConvenio(): JsonResponse
    {
        $tipos = Cache::remember('catalogos_tipos_convenio', 3600, function() {
            return TipoConvenio::select('id', 'nombre_tipo', 'descripcion')
                ->conConveniosActivos()
                ->orderBy('nombre_tipo')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos,
            'message' => 'Tipos de convenio obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener tipos de documento
     */
    public function tiposDocumento(): JsonResponse
    {
        $tipos = Cache::remember('catalogos_tipos_documento', 3600, function() {
            return TipoDocumento::select('id', 'nombre_entidad', 'descripcion')
                ->conDocumentosActivos()
                ->orderBy('nombre_entidad')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $tipos,
            'message' => 'Tipos de documento obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener descuentos
     */
    public function descuentos(): JsonResponse
    {
        $descuentos = Cache::remember('catalogos_descuentos', 3600, function() {
            return Descuento::select('id', 'nombre', 'descuento')
                ->activos()
                ->orderBy('nombre')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $descuentos,
            'message' => 'Descuentos obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener versiones
     */
    public function versiones(): JsonResponse
    {
        $versiones = Cache::remember('catalogos_versiones', 3600, function() {
            return Version::select('id', 'nombre', 'anio')
                ->recientes()
                ->orderBy('anio', 'desc')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $versiones,
            'message' => 'Versiones obtenidas exitosamente'
        ]);
    }

    /**
     * Obtener horarios
     */
    public function horarios(): JsonResponse
    {
        $horarios = Cache::remember('catalogos_horarios', 3600, function() {
            return Horario::select('id', 'dias', 'hora_ini', 'hora_fin')
                ->orderBy('hora_ini')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $horarios,
            'message' => 'Horarios obtenidos exitosamente'
        ]);
    }
}
