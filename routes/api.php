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
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\RolController;
use App\Http\Controllers\Admin\PermisoController;

/*
|--------------------------------------------------------------------------
| CONTROLADORES - DOCENTE
|--------------------------------------------------------------------------
*/
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
    // Perfil puede ser accedido por cualquier usuario autenticado (estudiante, admin, docente)
    // Usar middleware personalizado que maneje tanto estudiantes como usuarios admin/docente
    // No usar auth:api porque solo funciona con Usuario, no con Estudiante
    Route::middleware([\App\Http\Middleware\JwtVerifyMiddleware::class])->group(function () {
        Route::get('/perfil', [AutenticacionEstudianteController::class, 'obtenerPerfil']);
    });

    // Refresh token requiere auth:api (solo para admin/docente por ahora)
    Route::middleware('auth:api')->group(function () {
        Route::post('/refresh', [AutenticacionEstudianteController::class, 'refrescarToken']);
    });
});

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS - PAGO FÁCIL CALLBACK
|--------------------------------------------------------------------------
| Estas rutas son llamadas por PagoFácil, no requieren autenticación
*/
use App\Http\Controllers\PaymentController;

Route::prefix('payment')->group(function () {
    Route::post('/callback', [PaymentController::class, 'callback']);
    Route::get('/check-status/{id}', [PaymentController::class, 'checkStatus']);
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS - PORTAL ESTUDIANTE
|--------------------------------------------------------------------------
| Requiere autenticación JWT y rol ESTUDIANTE
*/
// Rutas de estudiante - usar middleware específico para estudiantes
Route::middleware([\App\Http\Middleware\StudentAuthMiddleware::class])->prefix('estudiante')->group(function () {

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
        Route::get('/programas-disponibles', [EstudianteInscripcionController::class, 'programasDisponibles']);
        Route::get('/descuentos-disponibles', [EstudianteInscripcionController::class, 'descuentosDisponibles']);
        Route::get('/reglas-cuotas/{programaId}', [EstudianteInscripcionController::class, 'reglasCuotas']);
        Route::get('/grupos/{grupoId}', [EstudianteInscripcionController::class, 'obtenerGrupo']);
        Route::post('/verificar-horarios', [EstudianteInscripcionController::class, 'verificarHorarios']);
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
        Route::post('/{pagoId}/subir-comprobante-qr', [EstudiantePagoController::class, 'subirComprobanteQR']);
        Route::get('/{pagoId}/consultar-estado-qr', [EstudiantePagoController::class, 'consultarEstadoQR']);
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

    // Gestión de tipos de documento
    Route::prefix('tipos-documento')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TipoDocumentoController::class, 'listar'])->middleware('permission:documentos_ver');
        Route::post('/', [\App\Http\Controllers\Admin\TipoDocumentoController::class, 'crear'])->middleware('permission:documentos_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\TipoDocumentoController::class, 'obtener'])->middleware('permission:documentos_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\TipoDocumentoController::class, 'actualizar'])->middleware('permission:documentos_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\TipoDocumentoController::class, 'eliminar'])->middleware('permission:documentos_eliminar');
    });

    // Validación de documentos
    Route::prefix('documentos/validacion')->group(function () {
        Route::get('/', [ValidacionDocumentoController::class, 'listar'])->middleware('permission:documentos_ver');
        Route::get('/{estudianteId}', [ValidacionDocumentoController::class, 'obtener'])->middleware('permission:documentos_ver');
        Route::post('/{documentoId}/aprobar', [ValidacionDocumentoController::class, 'aprobar'])->middleware('permission:documentos_editar');
        Route::post('/rechazar', [ValidacionDocumentoController::class, 'rechazar'])->middleware('permission:documentos_editar');
        Route::post('/{estudianteId}/aprobar-todos', [ValidacionDocumentoController::class, 'aprobarTodos'])->middleware('permission:documentos_editar');
    });

    // Gestión de planes de pago
    Route::prefix('planes-pago')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PlanPagoController::class, 'listar'])->middleware('permission:pagos_ver');
        Route::get('/datos-formulario', [\App\Http\Controllers\Admin\PlanPagoController::class, 'datosFormulario'])->middleware('permission:pagos_ver');
        Route::post('/', [\App\Http\Controllers\Admin\PlanPagoController::class, 'crear'])->middleware('permission:pagos_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\PlanPagoController::class, 'obtener'])->middleware('permission:pagos_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\PlanPagoController::class, 'actualizar'])->middleware('permission:pagos_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\PlanPagoController::class, 'eliminar'])->middleware('permission:pagos_eliminar');
    });

    // Gestión de descuentos
    Route::prefix('descuentos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\DescuentoController::class, 'listar'])->middleware('permission:pagos_ver');
        Route::get('/inscripciones-sin-descuento', [\App\Http\Controllers\Admin\DescuentoController::class, 'inscripcionesSinDescuento'])->middleware('permission:pagos_ver');
        Route::post('/', [\App\Http\Controllers\Admin\DescuentoController::class, 'crear'])->middleware('permission:pagos_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\DescuentoController::class, 'obtener'])->middleware('permission:pagos_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\DescuentoController::class, 'actualizar'])->middleware('permission:pagos_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\DescuentoController::class, 'eliminar'])->middleware('permission:pagos_eliminar');
    });

    // Gestión de pagos (Admin)
    Route::prefix('pagos/gestion')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\GestionPagoController::class, 'listar'])->middleware('permission:pagos_ver');
        Route::get('/{id}', [\App\Http\Controllers\Admin\GestionPagoController::class, 'obtener'])->middleware('permission:pagos_ver');
        Route::post('/registrar', [\App\Http\Controllers\Admin\GestionPagoController::class, 'registrarPago'])->middleware('permission:pagos_crear');
        Route::put('/{pagoId}', [\App\Http\Controllers\Admin\GestionPagoController::class, 'actualizarPago'])->middleware('permission:pagos_editar');
        Route::delete('/{pagoId}', [\App\Http\Controllers\Admin\GestionPagoController::class, 'eliminarPago'])->middleware('permission:pagos_eliminar');
        Route::post('/aplicar-penalidad', [\App\Http\Controllers\Admin\GestionPagoController::class, 'aplicarPenalidad'])->middleware('permission:pagos_editar');
    });

    // Verificación de pagos
    Route::prefix('pagos/verificacion')->group(function () {
        Route::get('/', [VerificacionPagoController::class, 'listar'])->middleware('permission:pagos_ver');
        Route::get('/{pagoId}', [VerificacionPagoController::class, 'obtener'])->middleware('permission:pagos_ver');
        Route::post('/{pagoId}/aprobar', [VerificacionPagoController::class, 'aprobar'])->middleware('permission:pagos_editar');
        Route::post('/{pagoId}/rechazar', [VerificacionPagoController::class, 'rechazar'])->middleware('permission:pagos_editar');
        Route::get('/resumen/verificados', [VerificacionPagoController::class, 'obtenerResumenVerificados'])->middleware('permission:pagos_ver');
    });

    // Estadísticas de rendimiento académico
    Route::prefix('estadisticas-rendimiento')->group(function () {
        Route::get('/por-grupo', [\App\Http\Controllers\Admin\EstadisticasRendimientoController::class, 'porGrupo'])->middleware('permission:grupos_ver');
        Route::get('/por-docente', [\App\Http\Controllers\Admin\EstadisticasRendimientoController::class, 'porDocente'])->middleware('permission:grupos_ver');
        Route::get('/por-modulo', [\App\Http\Controllers\Admin\EstadisticasRendimientoController::class, 'porModulo'])->middleware('permission:programas_ver');
        Route::get('/resumen-general', [\App\Http\Controllers\Admin\EstadisticasRendimientoController::class, 'resumenGeneral'])->middleware('permission:grupos_ver');
    });

    // Reportes y bitácora
    Route::prefix('reportes')->group(function () {
        Route::get('/convenios-activos', [\App\Http\Controllers\Admin\ReporteController::class, 'conveniosActivos'])->middleware('permission:convenios_ver');
        Route::get('/programas-ofrecidos', [\App\Http\Controllers\Admin\ReporteController::class, 'programasOfrecidos'])->middleware('permission:programas_ver');
        Route::get('/estado-academico-estudiantes', [\App\Http\Controllers\Admin\ReporteController::class, 'estadoAcademicoEstudiantes'])->middleware('permission:estudiantes_ver');
        Route::get('/movimientos-financieros', [\App\Http\Controllers\Admin\ReporteController::class, 'movimientosFinancieros'])->middleware('permission:pagos_ver');
        Route::get('/actividad-usuario', [\App\Http\Controllers\Admin\ReporteController::class, 'actividadPorUsuario'])->middleware('permission:usuarios_ver');
        Route::get('/actividad-institucion', [\App\Http\Controllers\Admin\ReporteController::class, 'actividadPorInstitucion'])->middleware('permission:configuracion_ver');
        Route::get('/datos-formulario', [\App\Http\Controllers\Admin\ReporteController::class, 'datosFormulario'])->middleware('permission:usuarios_ver');
    });

    // Bitácora
    Route::prefix('bitacora')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\BitacoraController::class, 'index'])->middleware('permission:bitacora_ver');
        Route::get('/estadisticas', [\App\Http\Controllers\Api\BitacoraController::class, 'estadisticas'])->middleware('permission:bitacora_ver');
        Route::get('/{id}', [\App\Http\Controllers\Api\BitacoraController::class, 'show'])->middleware('permission:bitacora_ver');
    });

    // Gestión de estudiantes
    Route::prefix('estudiantes')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\EstudianteController::class, 'listar'])->middleware('permission:estudiantes_ver');
        Route::get('/estadisticas', [\App\Http\Controllers\Admin\EstudianteController::class, 'estadisticas'])->middleware('permission:estudiantes_ver');
        Route::get('/estados', [\App\Http\Controllers\Admin\EstudianteController::class, 'obtenerEstados'])->middleware('permission:estudiantes_ver');
        Route::post('/', [\App\Http\Controllers\Admin\EstudianteController::class, 'crear'])->middleware('permission:estudiantes_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\EstudianteController::class, 'obtener'])->middleware('permission:estudiantes_ver_detalle');
        Route::put('/{id}', [\App\Http\Controllers\Admin\EstudianteController::class, 'actualizar'])->middleware('permission:estudiantes_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\EstudianteController::class, 'eliminar'])->middleware('permission:estudiantes_eliminar');
        Route::get('/{id}/documentos', [\App\Http\Controllers\Admin\EstudianteController::class, 'obtenerDocumentos'])->middleware('permission:estudiantes_ver_detalle');
        Route::post('/{id}/activar', [\App\Http\Controllers\Admin\EstudianteController::class, 'activar'])->middleware('permission:estudiantes_activar');
        Route::post('/{id}/desactivar', [\App\Http\Controllers\Admin\EstudianteController::class, 'desactivar'])->middleware('permission:estudiantes_activar');
    });

    // Gestión de inscripciones
    Route::prefix('inscripciones')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InscripcionController::class, 'listar'])->middleware('permission:inscripciones_ver');
        Route::get('/estadisticas', [\App\Http\Controllers\Admin\InscripcionController::class, 'estadisticas'])->middleware('permission:inscripciones_ver');
        Route::get('/{id}', [\App\Http\Controllers\Admin\InscripcionController::class, 'obtener'])->middleware('permission:inscripciones_ver_detalle');
    });

    // Reportes administrativos
    Route::prefix('reportes')->group(function () {
        Route::get('/estudiantes', [ReporteController::class, 'estudiantes']);
        Route::get('/pagos', [ReporteController::class, 'pagos']);
        Route::get('/inscripciones', [ReporteController::class, 'inscripciones']);
        Route::get('/rendimiento-academico', [ReporteController::class, 'rendimientoAcademico']);
        Route::get('/documentos', [ReporteController::class, 'documentos']);
    });

    // Gestión de roles y permisos
    Route::prefix('roles')->group(function () {
        Route::get('/', [RolController::class, 'listar'])->middleware('permission:roles_ver');
        Route::post('/', [RolController::class, 'crear'])->middleware('permission:roles_crear');
        Route::get('/{id}', [RolController::class, 'obtener'])->middleware('permission:roles_ver');
        Route::put('/{id}', [RolController::class, 'actualizar'])->middleware('permission:roles_editar');
        Route::delete('/{id}', [RolController::class, 'eliminar'])->middleware('permission:roles_eliminar');
        Route::post('/{id}/permisos', [RolController::class, 'actualizarPermisos'])->middleware('permission:roles_asignar_permisos');
    });

    // Gestión de permisos
    Route::prefix('permisos')->group(function () {
        Route::get('/', [PermisoController::class, 'listar'])->middleware('permission:roles_ver');
        Route::get('/agrupados', [PermisoController::class, 'agrupadosPorModulo'])->middleware('permission:roles_ver');
        Route::get('/modulo/{modulo}', [PermisoController::class, 'porModulo'])->middleware('permission:roles_ver');
    });

    // Configuración inicial del sistema
    // Gestión de países
    Route::prefix('paises')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PaisController::class, 'listar'])->middleware('permission:configuracion_ver');
        Route::post('/', [\App\Http\Controllers\Admin\PaisController::class, 'crear'])->middleware('permission:configuracion_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\PaisController::class, 'obtener'])->middleware('permission:configuracion_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\PaisController::class, 'actualizar'])->middleware('permission:configuracion_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\PaisController::class, 'eliminar'])->middleware('permission:configuracion_eliminar');
    });

    // Gestión de provincias
    Route::prefix('provincias')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ProvinciaController::class, 'listar'])->middleware('permission:configuracion_ver');
        Route::post('/', [\App\Http\Controllers\Admin\ProvinciaController::class, 'crear'])->middleware('permission:configuracion_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\ProvinciaController::class, 'obtener'])->middleware('permission:configuracion_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\ProvinciaController::class, 'actualizar'])->middleware('permission:configuracion_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\ProvinciaController::class, 'eliminar'])->middleware('permission:configuracion_eliminar');
    });

    // Gestión de ciudades
    Route::prefix('ciudades')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\CiudadController::class, 'listar'])->middleware('permission:configuracion_ver');
        Route::post('/', [\App\Http\Controllers\Admin\CiudadController::class, 'crear'])->middleware('permission:configuracion_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\CiudadController::class, 'obtener'])->middleware('permission:configuracion_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\CiudadController::class, 'actualizar'])->middleware('permission:configuracion_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\CiudadController::class, 'eliminar'])->middleware('permission:configuracion_eliminar');
    });

    // Gestión de instituciones
    Route::prefix('instituciones')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\InstitucionController::class, 'listar'])->middleware('permission:configuracion_ver');
        Route::post('/', [\App\Http\Controllers\Admin\InstitucionController::class, 'crear'])->middleware('permission:configuracion_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\InstitucionController::class, 'obtener'])->middleware('permission:configuracion_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\InstitucionController::class, 'actualizar'])->middleware('permission:configuracion_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\InstitucionController::class, 'eliminar'])->middleware('permission:configuracion_eliminar');
    });

    // Gestión de usuarios del sistema
    Route::prefix('usuarios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\UsuarioController::class, 'listar'])->middleware('permission:usuarios_ver');
        Route::post('/', [\App\Http\Controllers\Admin\UsuarioController::class, 'crear'])->middleware('permission:usuarios_crear');
        Route::get('/roles', [\App\Http\Controllers\Admin\UsuarioController::class, 'obtenerRoles'])->middleware('permission:usuarios_ver');
        Route::get('/{id}', [\App\Http\Controllers\Admin\UsuarioController::class, 'obtener'])->middleware('permission:usuarios_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\UsuarioController::class, 'actualizar'])->middleware('permission:usuarios_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\UsuarioController::class, 'eliminar'])->middleware('permission:usuarios_eliminar');
    });

    // Gestión de convenios institucionales
    // Tipos de convenio
    Route::prefix('tipo-convenios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TipoConvenioController::class, 'listar'])->middleware('permission:convenios_ver');
        Route::post('/', [\App\Http\Controllers\Admin\TipoConvenioController::class, 'crear'])->middleware('permission:convenios_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\TipoConvenioController::class, 'obtener'])->middleware('permission:convenios_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\TipoConvenioController::class, 'actualizar'])->middleware('permission:convenios_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\TipoConvenioController::class, 'eliminar'])->middleware('permission:convenios_eliminar');
    });

    // Convenios
    Route::prefix('convenios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ConvenioController::class, 'listar'])->middleware('permission:convenios_ver');
        Route::post('/', [\App\Http\Controllers\Admin\ConvenioController::class, 'crear'])->middleware('permission:convenios_crear');
        Route::get('/datos-formulario', [\App\Http\Controllers\Admin\ConvenioController::class, 'datosFormulario'])->middleware('permission:convenios_ver');
        Route::get('/{id}', [\App\Http\Controllers\Admin\ConvenioController::class, 'obtener'])->middleware('permission:convenios_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\ConvenioController::class, 'actualizar'])->middleware('permission:convenios_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\ConvenioController::class, 'eliminar'])->middleware('permission:convenios_eliminar');
        Route::post('/{id}/instituciones', [\App\Http\Controllers\Admin\ConvenioController::class, 'agregarInstitucion'])->middleware('permission:convenios_editar');
        Route::delete('/{id}/instituciones/{institucionId}', [\App\Http\Controllers\Admin\ConvenioController::class, 'removerInstitucion'])->middleware('permission:convenios_editar');
    });

    // Gestión de planificación académica
    // Ramas académicas
    Route::prefix('ramas-academicas')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\RamaAcademicaController::class, 'listar'])->middleware('permission:programas_ver');
        Route::post('/', [\App\Http\Controllers\Admin\RamaAcademicaController::class, 'crear'])->middleware('permission:programas_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\RamaAcademicaController::class, 'obtener'])->middleware('permission:programas_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\RamaAcademicaController::class, 'actualizar'])->middleware('permission:programas_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\RamaAcademicaController::class, 'eliminar'])->middleware('permission:programas_eliminar');
    });

    // Versiones
    Route::prefix('versiones')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\VersionController::class, 'listar'])->middleware('permission:programas_ver');
        Route::post('/', [\App\Http\Controllers\Admin\VersionController::class, 'crear'])->middleware('permission:programas_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\VersionController::class, 'obtener'])->middleware('permission:programas_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\VersionController::class, 'actualizar'])->middleware('permission:programas_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\VersionController::class, 'eliminar'])->middleware('permission:programas_eliminar');
    });

    // Tipos de programa
    Route::prefix('tipos-programa')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TipoProgramaController::class, 'listar'])->middleware('permission:programas_ver');
        Route::post('/', [\App\Http\Controllers\Admin\TipoProgramaController::class, 'crear'])->middleware('permission:programas_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\TipoProgramaController::class, 'obtener'])->middleware('permission:programas_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\TipoProgramaController::class, 'actualizar'])->middleware('permission:programas_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\TipoProgramaController::class, 'eliminar'])->middleware('permission:programas_eliminar');
    });

    // Módulos
    Route::prefix('modulos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ModuloController::class, 'listar'])->middleware('permission:programas_ver');
        Route::post('/', [\App\Http\Controllers\Admin\ModuloController::class, 'crear'])->middleware('permission:programas_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\ModuloController::class, 'obtener'])->middleware('permission:programas_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\ModuloController::class, 'actualizar'])->middleware('permission:programas_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\ModuloController::class, 'eliminar'])->middleware('permission:programas_eliminar');
    });

    // Programas
    Route::prefix('programas')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ProgramaController::class, 'listar'])->middleware('permission:programas_ver');
        Route::post('/', [\App\Http\Controllers\Admin\ProgramaController::class, 'crear'])->middleware('permission:programas_crear');
        Route::get('/datos-formulario', [\App\Http\Controllers\Admin\ProgramaController::class, 'datosFormulario'])->middleware('permission:programas_ver');
        Route::get('/{id}', [\App\Http\Controllers\Admin\ProgramaController::class, 'obtener'])->middleware('permission:programas_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\ProgramaController::class, 'actualizar'])->middleware('permission:programas_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\ProgramaController::class, 'eliminar'])->middleware('permission:programas_eliminar');
    });

    // Gestión de asignación de docentes y grupos
    // Docentes
    Route::prefix('docentes')->group(function () {
        Route::get('/siguiente-registro', [\App\Http\Controllers\Admin\DocenteController::class, 'siguienteRegistro'])->middleware('permission:docentes_ver');
        Route::get('/', [\App\Http\Controllers\Admin\DocenteController::class, 'listar'])->middleware('permission:docentes_ver');
        Route::post('/', [\App\Http\Controllers\Admin\DocenteController::class, 'crear'])->middleware('permission:docentes_crear');
        Route::get('/{registro}', [\App\Http\Controllers\Admin\DocenteController::class, 'obtener'])->middleware('permission:docentes_ver');
        Route::put('/{registro}', [\App\Http\Controllers\Admin\DocenteController::class, 'actualizar'])->middleware('permission:docentes_editar');
        Route::delete('/{registro}', [\App\Http\Controllers\Admin\DocenteController::class, 'eliminar'])->middleware('permission:docentes_eliminar');
    });

    // Horarios
    Route::prefix('horarios')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\HorarioController::class, 'listar'])->middleware('permission:grupos_ver');
        Route::post('/', [\App\Http\Controllers\Admin\HorarioController::class, 'crear'])->middleware('permission:grupos_crear');
        Route::get('/{id}', [\App\Http\Controllers\Admin\HorarioController::class, 'obtener'])->middleware('permission:grupos_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\HorarioController::class, 'actualizar'])->middleware('permission:grupos_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\HorarioController::class, 'eliminar'])->middleware('permission:grupos_eliminar');
    });

    // Grupos
    Route::prefix('grupos')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\GrupoController::class, 'listar'])->middleware('permission:grupos_ver');
        Route::post('/', [\App\Http\Controllers\Admin\GrupoController::class, 'crear'])->middleware('permission:grupos_crear');
        // IMPORTANTE: Rutas específicas ANTES de rutas con parámetros dinámicos
        Route::get('/datos-formulario', [\App\Http\Controllers\Admin\GrupoController::class, 'datosFormulario'])->middleware('permission:grupos_ver');
        Route::get('/modulos-por-programa/{programaId}', [\App\Http\Controllers\Admin\GrupoController::class, 'modulosPorPrograma'])->where('programaId', '[0-9]+')->middleware('permission:grupos_ver');
        // Rutas con parámetros dinámicos al final
        Route::get('/{id}', [\App\Http\Controllers\Admin\GrupoController::class, 'obtener'])->where('id', '[0-9]+')->middleware('permission:grupos_ver');
        Route::put('/{id}', [\App\Http\Controllers\Admin\GrupoController::class, 'actualizar'])->where('id', '[0-9]+')->middleware('permission:grupos_editar');
        Route::delete('/{id}', [\App\Http\Controllers\Admin\GrupoController::class, 'eliminar'])->where('id', '[0-9]+')->middleware('permission:grupos_eliminar');
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
        Route::get('/', [\App\Http\Controllers\Docente\GrupoController::class, 'listar']);
        Route::get('/{grupoId}', [\App\Http\Controllers\Docente\GrupoController::class, 'obtener']);
    });

    // Evaluaciones y notas
    Route::prefix('evaluaciones')->group(function () {
        Route::post('/nota', [\App\Http\Controllers\Docente\EvaluacionController::class, 'registrarNota']);
        Route::post('/estado', [\App\Http\Controllers\Docente\EvaluacionController::class, 'actualizarEstado']);
        Route::post('/notas-masivas', [\App\Http\Controllers\Docente\EvaluacionController::class, 'registrarNotasMasivas']);
    });

    // Mantener compatibilidad con rutas antiguas si existen
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

/*
|--------------------------------------------------------------------------
| RUTAS DE NOTIFICACIONES - ACCESIBLES PARA TODOS LOS ROLES
|--------------------------------------------------------------------------
| Las notificaciones son accesibles para estudiantes, docentes y administradores
| Usa JwtVerifyMiddleware que maneja todos los tipos de usuarios (Estudiante, Usuario)
*/
Route::middleware([\App\Http\Middleware\JwtVerifyMiddleware::class])->prefix('notificaciones')->group(function () {
    // Rutas comunes para todos los roles (lectura y gestión de notificaciones propias)
    Route::get('/', [NotificacionController::class, 'index']);
    Route::get('/contador', [NotificacionController::class, 'contador']);
    Route::get('/no-leidas', [NotificacionController::class, 'contador']); // Alias para compatibilidad
    Route::put('/{id}/marcar-leida', [NotificacionController::class, 'marcarLeida']);
    Route::put('/{id}/leida', [NotificacionController::class, 'marcarLeida']); // Alias para compatibilidad
    Route::put('/marcar-todas-leidas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::put('/todas/leidas', [NotificacionController::class, 'marcarTodasLeidas']); // Alias para compatibilidad
});

/*
|--------------------------------------------------------------------------
| RUTAS ADMINISTRATIVAS DE NOTIFICACIONES
|--------------------------------------------------------------------------
| Solo para admin/docente con permisos (crear, eliminar, estadísticas)
| Requiere autenticación con auth:api (solo Usuario, no Estudiante)
*/
Route::middleware(['auth:api'])->prefix('notificaciones')->group(function () {
    Route::get('/estadisticas', [NotificacionController::class, 'estadisticas'])->middleware('permission:notificaciones_ver');
    Route::post('/', [NotificacionController::class, 'store'])->middleware('permission:notificaciones_crear');
    Route::post('/masiva', [NotificacionController::class, 'enviarMasiva'])->middleware('permission:notificaciones_crear');
    Route::delete('/{id}', [NotificacionController::class, 'destroy'])->middleware('permission:notificaciones_eliminar');
});

// Test route for debugging
Route::get('/test-auth', function () {
    return response()->json([
        'message' => 'Test route accessible',
        'user' => auth('api')->user(),
        'authenticated' => auth('api')->check()
    ]);
});

// Test route with JWT middleware
Route::middleware('auth:api')->get('/test-auth-protected', function () {
    return response()->json([
        'message' => 'Protected test route accessible',
        'user' => auth('api')->user(),
        'authenticated' => auth('api')->check()
    ]);
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
