<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EstudianteController;
use App\Http\Controllers\Api\ProgramaController;
use App\Http\Controllers\Api\InscripcionController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\DocenteController;
use App\Http\Controllers\Api\GrupoController;
use App\Http\Controllers\Api\InstitucionController;
use App\Http\Controllers\Api\ConvenioController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentoController;
use App\Http\Controllers\Api\PaisController;
use App\Http\Controllers\Api\ProvinciaController;
use App\Http\Controllers\Api\CiudadController;
use App\Http\Controllers\Api\RamaAcademicaController;
use App\Http\Controllers\Api\TipoProgramaController;
use App\Http\Controllers\Api\ModuloController;
use App\Http\Controllers\Api\VersionController;
use App\Http\Controllers\Api\EstadoEstudianteController;
use App\Http\Controllers\Api\TipoConvenioController;
use App\Http\Controllers\Api\TipoDocumentoController;
use App\Http\Controllers\Api\DescuentoController;
use App\Http\Controllers\Api\HorarioController;
use App\Http\Controllers\Api\PlanPagosController;
use App\Http\Controllers\Api\CuotaController;
use App\Http\Controllers\Api\BitacoraController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| RUTAS DE ESTUDIANTES
|--------------------------------------------------------------------------
*/
Route::prefix('estudiantes')->group(function () {
    Route::get('/', [EstudianteController::class, 'index']);
    Route::get('/buscar', [EstudianteController::class, 'buscar']);
    Route::get('/estadisticas', [EstudianteController::class, 'estadisticas']);
    Route::get('/{id}', [EstudianteController::class, 'show']);
    Route::post('/', [EstudianteController::class, 'store']);
    Route::put('/{id}', [EstudianteController::class, 'update']);
    Route::delete('/{id}', [EstudianteController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE PROGRAMAS
|--------------------------------------------------------------------------
*/
Route::prefix('programas')->group(function () {
    Route::get('/', [ProgramaController::class, 'index']);
    Route::get('/datos-formulario', [ProgramaController::class, 'datosFormulario']);
    Route::get('/estadisticas', [ProgramaController::class, 'estadisticas']);
    Route::get('/{id}', [ProgramaController::class, 'show']);
    Route::post('/', [ProgramaController::class, 'store']);
    Route::put('/{id}', [ProgramaController::class, 'update']);
    Route::delete('/{id}', [ProgramaController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE INSCRIPCIONES
|--------------------------------------------------------------------------
*/
Route::prefix('inscripciones')->group(function () {
    Route::get('/', [InscripcionController::class, 'index']);
    Route::get('/datos-formulario', [InscripcionController::class, 'datosFormulario']);
    Route::get('/estadisticas', [InscripcionController::class, 'estadisticas']);
    Route::get('/{id}', [InscripcionController::class, 'show']);
    Route::post('/', [InscripcionController::class, 'store']);
    Route::put('/{id}', [InscripcionController::class, 'update']);
    Route::delete('/{id}', [InscripcionController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE PAGOS
|--------------------------------------------------------------------------
*/
Route::prefix('pagos')->group(function () {
    Route::get('/', [PagoController::class, 'index']);
    Route::get('/cuotas-pendientes', [PagoController::class, 'cuotasPendientes']);
    Route::get('/estadisticas', [PagoController::class, 'estadisticas']);
    Route::get('/{id}', [PagoController::class, 'show']);
    Route::post('/', [PagoController::class, 'store']);
    Route::put('/{id}', [PagoController::class, 'update']);
    Route::delete('/{id}', [PagoController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE DOCENTES
|--------------------------------------------------------------------------
*/
Route::prefix('docentes')->group(function () {
    Route::get('/', [DocenteController::class, 'index']);
    Route::get('/buscar', [DocenteController::class, 'buscar']);
    Route::get('/estadisticas', [DocenteController::class, 'estadisticas']);
    Route::get('/{id}', [DocenteController::class, 'show']);
    Route::post('/', [DocenteController::class, 'store']);
    Route::put('/{id}', [DocenteController::class, 'update']);
    Route::delete('/{id}', [DocenteController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE GRUPOS
|--------------------------------------------------------------------------
*/
Route::prefix('grupos')->group(function () {
    Route::get('/', [GrupoController::class, 'index']);
    Route::get('/activos', [GrupoController::class, 'activos']);
    Route::get('/datos-formulario', [GrupoController::class, 'datosFormulario']);
    Route::get('/estadisticas', [GrupoController::class, 'estadisticas']);
    Route::get('/{id}', [GrupoController::class, 'show']);
    Route::post('/', [GrupoController::class, 'store']);
    Route::put('/{id}', [GrupoController::class, 'update']);
    Route::delete('/{id}', [GrupoController::class, 'destroy']);

    // Rutas específicas de grupos
    Route::post('/{id}/estudiantes', [GrupoController::class, 'agregarEstudiante']);
    Route::delete('/{id}/estudiantes/{estudianteId}', [GrupoController::class, 'removerEstudiante']);
    Route::put('/{id}/estudiantes/{estudianteId}/nota', [GrupoController::class, 'actualizarNota']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE INSTITUCIONES
|--------------------------------------------------------------------------
*/
Route::prefix('instituciones')->group(function () {
    Route::get('/', [InstitucionController::class, 'index']);
    Route::get('/activas', [InstitucionController::class, 'activas']);
    Route::get('/buscar', [InstitucionController::class, 'buscar']);
    Route::get('/estadisticas', [InstitucionController::class, 'estadisticas']);
    Route::get('/{id}', [InstitucionController::class, 'show']);
    Route::post('/', [InstitucionController::class, 'store']);
    Route::put('/{id}', [InstitucionController::class, 'update']);
    Route::delete('/{id}', [InstitucionController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE CONVENIOS
|--------------------------------------------------------------------------
*/
Route::prefix('convenios')->group(function () {
    Route::get('/', [ConvenioController::class, 'index']);
    Route::get('/activos', [ConvenioController::class, 'activos']);
    Route::get('/vencidos', [ConvenioController::class, 'vencidos']);
    Route::get('/datos-formulario', [ConvenioController::class, 'datosFormulario']);
    Route::get('/estadisticas', [ConvenioController::class, 'estadisticas']);
    Route::get('/{id}', [ConvenioController::class, 'show']);
    Route::post('/', [ConvenioController::class, 'store']);
    Route::put('/{id}', [ConvenioController::class, 'update']);
    Route::delete('/{id}', [ConvenioController::class, 'destroy']);

    // Rutas específicas de convenios
    Route::post('/{id}/instituciones', [ConvenioController::class, 'agregarInstitucion']);
    Route::delete('/{id}/instituciones/{institucionId}', [ConvenioController::class, 'removerInstitucion']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE DOCUMENTOS
|--------------------------------------------------------------------------
*/
Route::prefix('documentos')->group(function () {
    Route::get('/', [DocumentoController::class, 'index']);
    Route::get('/estadisticas', [DocumentoController::class, 'estadisticas']);
    Route::get('/{id}', [DocumentoController::class, 'show']);
    Route::post('/', [DocumentoController::class, 'store']);
    Route::post('/subir-archivo', [DocumentoController::class, 'subirArchivo']);
    Route::get('/{id}/descargar', [DocumentoController::class, 'descargar']);
    Route::put('/{id}', [DocumentoController::class, 'update']);
    Route::delete('/{id}', [DocumentoController::class, 'destroy']);
});

// Rutas de catálogos geográficos
Route::prefix('paises')->group(function () {
    Route::get('/', [PaisController::class, 'index']);
    Route::get('/{id}', [PaisController::class, 'show']);
    Route::post('/', [PaisController::class, 'store']);
    Route::put('/{id}', [PaisController::class, 'update']);
    Route::delete('/{id}', [PaisController::class, 'destroy']);
});

Route::prefix('provincias')->group(function () {
    Route::get('/', [ProvinciaController::class, 'index']);
    Route::get('/{id}', [ProvinciaController::class, 'show']);
    Route::post('/', [ProvinciaController::class, 'store']);
    Route::put('/{id}', [ProvinciaController::class, 'update']);
    Route::delete('/{id}', [ProvinciaController::class, 'destroy']);
});

Route::prefix('ciudades')->group(function () {
    Route::get('/', [CiudadController::class, 'index']);
    Route::get('/{id}', [CiudadController::class, 'show']);
    Route::post('/', [CiudadController::class, 'store']);
    Route::put('/{id}', [CiudadController::class, 'update']);
    Route::delete('/{id}', [CiudadController::class, 'destroy']);
});

// Rutas de catálogos académicos
Route::prefix('ramas-academicas')->group(function () {
    Route::get('/', [RamaAcademicaController::class, 'index']);
    Route::get('/{id}', [RamaAcademicaController::class, 'show']);
    Route::post('/', [RamaAcademicaController::class, 'store']);
    Route::put('/{id}', [RamaAcademicaController::class, 'update']);
    Route::delete('/{id}', [RamaAcademicaController::class, 'destroy']);
});

Route::prefix('tipos-programa')->group(function () {
    Route::get('/', [TipoProgramaController::class, 'index']);
    Route::get('/{id}', [TipoProgramaController::class, 'show']);
    Route::post('/', [TipoProgramaController::class, 'store']);
    Route::put('/{id}', [TipoProgramaController::class, 'update']);
    Route::delete('/{id}', [TipoProgramaController::class, 'destroy']);
});

Route::prefix('modulos')->group(function () {
    Route::get('/', [ModuloController::class, 'index']);
    Route::get('/{id}', [ModuloController::class, 'show']);
    Route::post('/', [ModuloController::class, 'store']);
    Route::put('/{id}', [ModuloController::class, 'update']);
    Route::delete('/{id}', [ModuloController::class, 'destroy']);
});

Route::prefix('versiones')->group(function () {
    Route::get('/', [VersionController::class, 'index']);
    Route::get('/{id}', [VersionController::class, 'show']);
    Route::post('/', [VersionController::class, 'store']);
    Route::put('/{id}', [VersionController::class, 'update']);
    Route::delete('/{id}', [VersionController::class, 'destroy']);
});

Route::prefix('estados-estudiante')->group(function () {
    Route::get('/', [EstadoEstudianteController::class, 'index']);
    Route::get('/{id}', [EstadoEstudianteController::class, 'show']);
    Route::post('/', [EstadoEstudianteController::class, 'store']);
    Route::put('/{id}', [EstadoEstudianteController::class, 'update']);
    Route::delete('/{id}', [EstadoEstudianteController::class, 'destroy']);
});

// Rutas de convenios
Route::prefix('tipos-convenio')->group(function () {
    Route::get('/', [TipoConvenioController::class, 'index']);
    Route::get('/{id}', [TipoConvenioController::class, 'show']);
    Route::post('/', [TipoConvenioController::class, 'store']);
    Route::put('/{id}', [TipoConvenioController::class, 'update']);
    Route::delete('/{id}', [TipoConvenioController::class, 'destroy']);
});

// Rutas de documentos
Route::prefix('tipos-documento')->group(function () {
    Route::get('/', [TipoDocumentoController::class, 'index']);
    Route::get('/{id}', [TipoDocumentoController::class, 'show']);
    Route::post('/', [TipoDocumentoController::class, 'store']);
    Route::put('/{id}', [TipoDocumentoController::class, 'update']);
    Route::delete('/{id}', [TipoDocumentoController::class, 'destroy']);
});

// Rutas de descuentos
Route::prefix('descuentos')->group(function () {
    Route::get('/', [DescuentoController::class, 'index']);
    Route::get('/{id}', [DescuentoController::class, 'show']);
    Route::post('/', [DescuentoController::class, 'store']);
    Route::put('/{id}', [DescuentoController::class, 'update']);
    Route::delete('/{id}', [DescuentoController::class, 'destroy']);
});

// Rutas de horarios
Route::prefix('horarios')->group(function () {
    Route::get('/', [HorarioController::class, 'index']);
    Route::get('/{id}', [HorarioController::class, 'show']);
    Route::post('/', [HorarioController::class, 'store']);
    Route::put('/{id}', [HorarioController::class, 'update']);
    Route::delete('/{id}', [HorarioController::class, 'destroy']);
});

// Rutas de planes de pago
Route::prefix('planes-pago')->group(function () {
    Route::get('/', [PlanPagosController::class, 'index']);
    Route::get('/{id}', [PlanPagosController::class, 'show']);
    Route::post('/', [PlanPagosController::class, 'store']);
    Route::put('/{id}', [PlanPagosController::class, 'update']);
    Route::delete('/{id}', [PlanPagosController::class, 'destroy']);
});

// Rutas de cuotas
Route::prefix('cuotas')->group(function () {
    Route::get('/', [CuotaController::class, 'index']);
    Route::get('/{id}', [CuotaController::class, 'show']);
    Route::post('/', [CuotaController::class, 'store']);
    Route::put('/{id}', [CuotaController::class, 'update']);
    Route::delete('/{id}', [CuotaController::class, 'destroy']);
});

// Rutas de bitácora
Route::prefix('bitacora')->group(function () {
    Route::get('/', [BitacoraController::class, 'index']);
    Route::get('/estadisticas', [BitacoraController::class, 'estadisticas']);
    Route::get('/{id}', [BitacoraController::class, 'show']);
    Route::post('/', [BitacoraController::class, 'store']);
    Route::post('/limpiar', [BitacoraController::class, 'limpiar']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE CATÁLOGOS (LEGACY - MANTENER PARA COMPATIBILIDAD)
|--------------------------------------------------------------------------
*/
Route::prefix('catalogos')->group(function () {
    // Países, Provincias, Ciudades
    Route::get('/paises', [CatalogoController::class, 'paises']);
    Route::get('/paises/{id}/provincias', [CatalogoController::class, 'provincias']);
    Route::get('/provincias/{id}/ciudades', [CatalogoController::class, 'ciudades']);

    // Tipos y ramas académicas
    Route::get('/tipos-programa', [CatalogoController::class, 'tiposPrograma']);
    Route::get('/ramas-academicas', [CatalogoController::class, 'ramasAcademicas']);
    Route::get('/modulos', [CatalogoController::class, 'modulos']);

    // Estados y tipos
    Route::get('/estados-estudiante', [CatalogoController::class, 'estadosEstudiante']);
    Route::get('/tipos-convenio', [CatalogoController::class, 'tiposConvenio']);
    Route::get('/tipos-documento', [CatalogoController::class, 'tiposDocumento']);
    Route::get('/descuentos', [CatalogoController::class, 'descuentos']);
    Route::get('/versiones', [CatalogoController::class, 'versiones']);
    Route::get('/horarios', [CatalogoController::class, 'horarios']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE REPORTES
|--------------------------------------------------------------------------
*/
Route::prefix('reportes')->group(function () {
    Route::get('/estudiantes-por-programa', [ReporteController::class, 'estudiantesPorPrograma']);
    Route::get('/pagos-por-periodo', [ReporteController::class, 'pagosPorPeriodo']);
    Route::get('/ingresos-por-institucion', [ReporteController::class, 'ingresosPorInstitucion']);
    Route::get('/estadisticas-generales', [ReporteController::class, 'estadisticasGenerales']);
    Route::get('/exportar/{tipo}', [ReporteController::class, 'exportar']);
});

/*
|--------------------------------------------------------------------------
| RUTAS DE DASHBOARD
|--------------------------------------------------------------------------
*/
Route::prefix('dashboard')->group(function () {
    Route::get('/resumen', [DashboardController::class, 'resumen']);
    Route::get('/graficos', [DashboardController::class, 'graficos']);
    Route::get('/alertas', [DashboardController::class, 'alertas']);
    Route::get('/actividad-reciente', [DashboardController::class, 'actividadReciente']);
});
