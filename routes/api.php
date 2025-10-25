<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CONTROLADORES - AUTENTICACIÓN
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Auth\AutenticacionEstudianteController;
use App\Http\Controllers\Auth\AutenticacionAdminController;

/*
|--------------------------------------------------------------------------
| CONTROLADORES - ESTUDIANTE
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Student\PanelEstudianteController;
use App\Http\Controllers\Student\DocumentoController as EstudianteDocumentoController;
use App\Http\Controllers\Student\ProgramaController as EstudianteProgramaController;
use App\Http\Controllers\Student\InscripcionController as EstudianteInscripcionController;
use App\Http\Controllers\Student\PagoController as EstudiantePagoController;
use App\Http\Controllers\Student\NotaController as EstudianteNotaController;
use App\Http\Controllers\Student\CertificadoController;

/*
|--------------------------------------------------------------------------
| CONTROLADORES - ADMINISTRADOR
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Admin\PanelAdminController;
use App\Http\Controllers\Admin\ValidacionDocumentoController;
use App\Http\Controllers\Admin\VerificacionPagoController;
use App\Http\Controllers\Admin\GrupoController as AdminGrupoController;
use App\Http\Controllers\Admin\ReporteController;

/*
|--------------------------------------------------------------------------
| CONTROLADORES - DOCENTE
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Teacher\GrupoController as DocenteGrupoController;
use App\Http\Controllers\Teacher\NotaController as DocenteNotaController;

/*
|--------------------------------------------------------------------------
| CONTROLADORES - API LEGACY (CATÁLOGOS)
|--------------------------------------------------------------------------
*/
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\NotificacionController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS - AUTENTICACIÓN
|--------------------------------------------------------------------------
| Estas rutas no requieren autenticación
*/
Route::prefix('auth')->group(function () {
    // Estudiante
    Route::post('/estudiante/registrar', [AutenticacionEstudianteController::class, 'registrar']);
    Route::post('/estudiante/login', [AutenticacionEstudianteController::class, 'iniciarSesion']);

    // Admin/Docente
    Route::post('/admin/login', [AutenticacionAdminController::class, 'iniciarSesion']);

    // Logout público (no requiere autenticación)
    Route::post('/logout', [AutenticacionEstudianteController::class, 'cerrarSesion']);

    // Rutas protegidas (requieren JWT)
    Route::middleware('auth:api')->group(function () {
        Route::post('/refresh', [AutenticacionEstudianteController::class, 'refrescarToken']);
        Route::get('/perfil', [AutenticacionEstudianteController::class, 'obtenerPerfil']);
    });
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS - PORTAL ESTUDIANTE
|--------------------------------------------------------------------------
| Requiere autenticación JWT y rol ESTUDIANTE
*/
Route::middleware(['auth:api', 'role:ESTUDIANTE'])->prefix('estudiante')->group(function () {

    // Dashboard
    Route::get('/dashboard', [PanelEstudianteController::class, 'obtenerDashboard']);

    // Gestión de documentos
    Route::prefix('documentos')->group(function () {
        Route::get('/', [EstudianteDocumentoController::class, 'listar']);
        Route::post('/subir', [EstudianteDocumentoController::class, 'subir']);
        Route::get('/{id}', [EstudianteDocumentoController::class, 'obtener']);
    });

    // Catálogo de programas académicos
    Route::prefix('programas')->group(function () {
        Route::get('/', [EstudianteProgramaController::class, 'listar']);
        Route::get('/{id}', [EstudianteProgramaController::class, 'obtener']);
    });

    // Mis inscripciones
    Route::prefix('inscripciones')->group(function () {
        Route::get('/', [EstudianteInscripcionController::class, 'listar']);
        Route::post('/', [EstudianteInscripcionController::class, 'crear']);
        Route::get('/{id}', [EstudianteInscripcionController::class, 'obtener']);
    });

    // Mis pagos y cuotas
    Route::prefix('pagos')->group(function () {
        Route::get('/', [EstudiantePagoController::class, 'listar']);
        Route::post('/', [EstudiantePagoController::class, 'crear']);
        Route::get('/{cuotaId}', [EstudiantePagoController::class, 'obtener']);
        Route::get('/{cuotaId}/info-qr', [EstudiantePagoController::class, 'obtenerInfoQR']);
    });

    // Mis notas
    Route::prefix('notas')->group(function () {
        Route::get('/', [EstudianteNotaController::class, 'listar']);
        Route::get('/{grupoId}', [EstudianteNotaController::class, 'obtener']);
    });

    // Mis certificados
    Route::prefix('certificados')->group(function () {
        Route::get('/', [CertificadoController::class, 'listar']);
        Route::get('/{grupoId}/vista-previa', [CertificadoController::class, 'vistaPrevia']);
        Route::get('/{grupoId}/descargar', [CertificadoController::class, 'descargar']);
    });
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS - PORTAL ADMINISTRADOR
|--------------------------------------------------------------------------
| Requiere autenticación JWT y rol ADMIN
*/
Route::middleware(['auth:api', 'role:ADMIN'])->prefix('admin')->group(function () {

    // Dashboard administrativo
    Route::get('/dashboard', [PanelAdminController::class, 'obtenerDashboard']);
    Route::get('/dashboard/estadisticas-crecimiento', [PanelAdminController::class, 'obtenerEstadisticasCrecimiento']);

    // Validación de documentos
    Route::prefix('documentos/validacion')->group(function () {
        Route::get('/', [ValidacionDocumentoController::class, 'listar']);
        Route::get('/{estudianteId}', [ValidacionDocumentoController::class, 'obtener']);
        Route::post('/{documentoId}/aprobar', [ValidacionDocumentoController::class, 'aprobar']);
        Route::post('/rechazar', [ValidacionDocumentoController::class, 'rechazar']);
        Route::post('/{estudianteId}/aprobar-todos', [ValidacionDocumentoController::class, 'aprobarTodos']);
    });

    // Verificación de pagos
    Route::prefix('pagos/verificacion')->group(function () {
        Route::get('/', [VerificacionPagoController::class, 'listar']);
        Route::get('/{pagoId}', [VerificacionPagoController::class, 'obtener']);
        Route::post('/{pagoId}/aprobar', [VerificacionPagoController::class, 'aprobar']);
        Route::post('/{pagoId}/rechazar', [VerificacionPagoController::class, 'rechazar']);
        Route::get('/resumen/verificados', [VerificacionPagoController::class, 'obtenerResumenVerificados']);
    });

    // Gestión de grupos
    Route::prefix('grupos')->group(function () {
        Route::get('/', [AdminGrupoController::class, 'listar']);
        Route::post('/', [AdminGrupoController::class, 'crear']);
        Route::get('/{grupoId}', [AdminGrupoController::class, 'obtener']);
        Route::put('/{grupoId}', [AdminGrupoController::class, 'actualizar']);
        Route::post('/{grupoId}/asignar-estudiantes', [AdminGrupoController::class, 'asignarEstudiantes']);
        Route::delete('/{grupoId}/estudiantes/{estudianteId}', [AdminGrupoController::class, 'quitarEstudiante']);
        Route::get('/{grupoId}/estudiantes-disponibles', [AdminGrupoController::class, 'obtenerEstudiantesDisponibles']);
    });

    // Reportes administrativos
    Route::prefix('reportes')->group(function () {
        Route::get('/estudiantes', [ReporteController::class, 'estudiantes']);
        Route::get('/pagos', [ReporteController::class, 'pagos']);
        Route::get('/inscripciones', [ReporteController::class, 'inscripciones']);
        Route::get('/rendimiento-academico', [ReporteController::class, 'rendimientoAcademico']);
        Route::get('/documentos', [ReporteController::class, 'documentos']);
    });
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS - PORTAL DOCENTE
|--------------------------------------------------------------------------
| Requiere autenticación JWT y rol DOCENTE
*/
Route::middleware(['auth:api', 'role:DOCENTE'])->prefix('docente')->group(function () {

    // Mis grupos asignados
    Route::prefix('grupos')->group(function () {
        Route::get('/', [DocenteGrupoController::class, 'listar']);
        Route::get('/{grupoId}', [DocenteGrupoController::class, 'obtener']);
    });

    // Registro de notas
    Route::prefix('notas')->group(function () {
        Route::post('/', [DocenteNotaController::class, 'crear']);
        Route::post('/masivo', [DocenteNotaController::class, 'crearMasivo']);
        Route::get('/{grupoId}/estadisticas', [DocenteNotaController::class, 'obtenerEstadisticasGrupo']);
    });
});

/*
|--------------------------------------------------------------------------
| RUTAS COMPARTIDAS - ADMIN Y DOCENTE
|--------------------------------------------------------------------------
| Requiere autenticación JWT y rol ADMIN o DOCENTE
*/
Route::middleware(['auth:api', 'role:ADMIN,DOCENTE'])->prefix('personal')->group(function () {
    // Aquí irían rutas compartidas entre admin y docente
});

/*
|--------------------------------------------------------------------------
| RUTAS LEGACY - API CRUD BÁSICOS
|--------------------------------------------------------------------------
| Mantener para compatibilidad con frontend existente
*/

// Notificaciones
Route::prefix('notificaciones')->group(function () {
    Route::get('/', [NotificacionController::class, 'index']);
    Route::get('/contador', [NotificacionController::class, 'contador']);
    Route::get('/estadisticas', [NotificacionController::class, 'estadisticas']);
    Route::post('/', [NotificacionController::class, 'store']);
    Route::post('/masiva', [NotificacionController::class, 'enviarMasiva']);
    Route::put('/{id}/leida', [NotificacionController::class, 'marcarLeida']);
    Route::put('/todas/leidas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::delete('/{id}', [NotificacionController::class, 'destroy']);
});

// Catálogos geográficos
Route::prefix('catalogos')->group(function () {
    Route::get('/paises', [CatalogoController::class, 'paises']);
    Route::get('/paises/{id}/provincias', [CatalogoController::class, 'provincias']);
    Route::get('/provincias/{id}/ciudades', [CatalogoController::class, 'ciudades']);
    Route::get('/tipos-programa', [CatalogoController::class, 'tiposPrograma']);
    Route::get('/ramas-academicas', [CatalogoController::class, 'ramasAcademicas']);
    Route::get('/modulos', [CatalogoController::class, 'modulos']);
    Route::get('/estados-estudiante', [CatalogoController::class, 'estadosEstudiante']);
    Route::get('/tipos-convenio', [CatalogoController::class, 'tiposConvenio']);
    Route::get('/tipos-documento', [CatalogoController::class, 'tiposDocumento']);
    Route::get('/descuentos', [CatalogoController::class, 'descuentos']);
    Route::get('/versiones', [CatalogoController::class, 'versiones']);
    Route::get('/horarios', [CatalogoController::class, 'horarios']);
});
