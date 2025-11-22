<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;
use Illuminate\Support\Facades\DB;

class PermisoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permisos = [
            // Módulo: Documentos
            [
                'nombre_permiso' => 'documentos_ver',
                'descripcion' => 'Ver documentos y tipos de documento',
                'modulo' => 'documentos',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'documentos_crear',
                'descripcion' => 'Crear tipos de documento',
                'modulo' => 'documentos',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'documentos_editar',
                'descripcion' => 'Editar documentos y aprobar/rechazar validaciones',
                'modulo' => 'documentos',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'documentos_eliminar',
                'descripcion' => 'Eliminar tipos de documento',
                'modulo' => 'documentos',
                'accion' => 'eliminar',
                'activo' => true
            ],

            // Módulo: Pagos
            [
                'nombre_permiso' => 'pagos_ver',
                'descripcion' => 'Ver planes de pago, descuentos, pagos y verificaciones',
                'modulo' => 'pagos',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'pagos_crear',
                'descripcion' => 'Crear planes de pago, descuentos y registrar pagos',
                'modulo' => 'pagos',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'pagos_editar',
                'descripcion' => 'Editar planes de pago, descuentos, actualizar pagos, aprobar/rechazar pagos y aplicar penalidades',
                'modulo' => 'pagos',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'pagos_eliminar',
                'descripcion' => 'Eliminar planes de pago, descuentos y pagos',
                'modulo' => 'pagos',
                'accion' => 'eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'pagos_exportar',
                'descripcion' => 'Exportar reportes de pagos',
                'modulo' => 'pagos',
                'accion' => 'exportar',
                'activo' => true
            ],

            // Módulo: Estudiantes
            [
                'nombre_permiso' => 'estudiantes_ver',
                'descripcion' => 'Ver lista de estudiantes y estadísticas',
                'modulo' => 'estudiantes',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_ver_detalle',
                'descripcion' => 'Ver detalle completo de un estudiante y sus documentos',
                'modulo' => 'estudiantes',
                'accion' => 'ver_detalle',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_crear',
                'descripcion' => 'Crear nuevos estudiantes',
                'modulo' => 'estudiantes',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_editar',
                'descripcion' => 'Editar información de estudiantes',
                'modulo' => 'estudiantes',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_eliminar',
                'descripcion' => 'Eliminar estudiantes',
                'modulo' => 'estudiantes',
                'accion' => 'eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_activar',
                'descripcion' => 'Activar o desactivar estudiantes',
                'modulo' => 'estudiantes',
                'accion' => 'activar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_exportar',
                'descripcion' => 'Exportar reportes de estudiantes',
                'modulo' => 'estudiantes',
                'accion' => 'exportar',
                'activo' => true
            ],

            // Módulo: Inscripciones
            [
                'nombre_permiso' => 'inscripciones_ver',
                'descripcion' => 'Ver lista de inscripciones y estadísticas',
                'modulo' => 'inscripciones',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'inscripciones_ver_detalle',
                'descripcion' => 'Ver detalle completo de una inscripción',
                'modulo' => 'inscripciones',
                'accion' => 'ver_detalle',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'inscripciones_exportar',
                'descripcion' => 'Exportar reportes de inscripciones',
                'modulo' => 'inscripciones',
                'accion' => 'exportar',
                'activo' => true
            ],

            // Módulo: Docentes
            [
                'nombre_permiso' => 'docentes_ver',
                'descripcion' => 'Ver lista de docentes',
                'modulo' => 'docentes',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'docentes_crear',
                'descripcion' => 'Crear nuevos docentes',
                'modulo' => 'docentes',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'docentes_editar',
                'descripcion' => 'Editar información de docentes',
                'modulo' => 'docentes',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'docentes_eliminar',
                'descripcion' => 'Eliminar docentes',
                'modulo' => 'docentes',
                'accion' => 'eliminar',
                'activo' => true
            ],

            // Módulo: Grupos
            [
                'nombre_permiso' => 'grupos_ver',
                'descripcion' => 'Ver grupos, horarios y estadísticas de rendimiento',
                'modulo' => 'grupos',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_crear',
                'descripcion' => 'Crear grupos y horarios',
                'modulo' => 'grupos',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_editar',
                'descripcion' => 'Editar grupos y horarios',
                'modulo' => 'grupos',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_eliminar',
                'descripcion' => 'Eliminar grupos y horarios',
                'modulo' => 'grupos',
                'accion' => 'eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_exportar',
                'descripcion' => 'Exportar reportes de grupos y rendimiento',
                'modulo' => 'grupos',
                'accion' => 'exportar',
                'activo' => true
            ],

            // Módulo: Programas
            [
                'nombre_permiso' => 'programas_ver',
                'descripcion' => 'Ver programas, módulos, ramas académicas, tipos de programa, versiones y estadísticas',
                'modulo' => 'programas',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'programas_crear',
                'descripcion' => 'Crear programas, módulos, ramas académicas, tipos de programa y versiones',
                'modulo' => 'programas',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'programas_editar',
                'descripcion' => 'Editar programas, módulos, ramas académicas, tipos de programa y versiones',
                'modulo' => 'programas',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'programas_eliminar',
                'descripcion' => 'Eliminar programas, módulos, ramas académicas, tipos de programa y versiones',
                'modulo' => 'programas',
                'accion' => 'eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'programas_exportar',
                'descripcion' => 'Exportar reportes de programas',
                'modulo' => 'programas',
                'accion' => 'exportar',
                'activo' => true
            ],

            // Módulo: Convenios
            [
                'nombre_permiso' => 'convenios_ver',
                'descripcion' => 'Ver convenios, tipos de convenio y reportes',
                'modulo' => 'convenios',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'convenios_crear',
                'descripcion' => 'Crear convenios y tipos de convenio',
                'modulo' => 'convenios',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'convenios_editar',
                'descripcion' => 'Editar convenios, tipos de convenio y gestionar instituciones',
                'modulo' => 'convenios',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'convenios_eliminar',
                'descripcion' => 'Eliminar convenios y tipos de convenio',
                'modulo' => 'convenios',
                'accion' => 'eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'convenios_exportar',
                'descripcion' => 'Exportar reportes de convenios',
                'modulo' => 'convenios',
                'accion' => 'exportar',
                'activo' => true
            ],

            // Módulo: Configuración
            [
                'nombre_permiso' => 'configuracion_ver',
                'descripcion' => 'Ver configuración del sistema: países, provincias, ciudades, instituciones y reportes',
                'modulo' => 'configuracion',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'configuracion_crear',
                'descripcion' => 'Crear países, provincias, ciudades e instituciones',
                'modulo' => 'configuracion',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'configuracion_editar',
                'descripcion' => 'Editar países, provincias, ciudades e instituciones',
                'modulo' => 'configuracion',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'configuracion_eliminar',
                'descripcion' => 'Eliminar países, provincias, ciudades e instituciones',
                'modulo' => 'configuracion',
                'accion' => 'eliminar',
                'activo' => true
            ],

            // Módulo: Usuarios
            [
                'nombre_permiso' => 'usuarios_ver',
                'descripcion' => 'Ver usuarios, bitácora y reportes de actividad',
                'modulo' => 'usuarios',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'usuarios_crear',
                'descripcion' => 'Crear usuarios del sistema',
                'modulo' => 'usuarios',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'usuarios_editar',
                'descripcion' => 'Editar usuarios del sistema',
                'modulo' => 'usuarios',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'usuarios_eliminar',
                'descripcion' => 'Eliminar usuarios del sistema',
                'modulo' => 'usuarios',
                'accion' => 'eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'usuarios_exportar',
                'descripcion' => 'Exportar reportes de usuarios y actividad',
                'modulo' => 'usuarios',
                'accion' => 'exportar',
                'activo' => true
            ],

            // Módulo: Roles
            [
                'nombre_permiso' => 'roles_ver',
                'descripcion' => 'Ver roles y permisos',
                'modulo' => 'roles',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'roles_crear',
                'descripcion' => 'Crear nuevos roles',
                'modulo' => 'roles',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'roles_editar',
                'descripcion' => 'Editar roles',
                'modulo' => 'roles',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'roles_eliminar',
                'descripcion' => 'Eliminar roles',
                'modulo' => 'roles',
                'accion' => 'eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'roles_asignar_permisos',
                'descripcion' => 'Asignar o revocar permisos a roles',
                'modulo' => 'roles',
                'accion' => 'asignar_permisos',
                'activo' => true
            ],

            // Módulo: Bitácora
            [
                'nombre_permiso' => 'bitacora_ver',
                'descripcion' => 'Ver registros de bitácora y actividad del sistema',
                'modulo' => 'bitacora',
                'accion' => 'ver',
                'activo' => true
            ],

            // Módulo: Notificaciones
            [
                'nombre_permiso' => 'notificaciones_ver',
                'descripcion' => 'Ver notificaciones del sistema',
                'modulo' => 'notificaciones',
                'accion' => 'ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'notificaciones_crear',
                'descripcion' => 'Crear y enviar notificaciones',
                'modulo' => 'notificaciones',
                'accion' => 'crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'notificaciones_editar',
                'descripcion' => 'Editar notificaciones',
                'modulo' => 'notificaciones',
                'accion' => 'editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'notificaciones_eliminar',
                'descripcion' => 'Eliminar notificaciones',
                'modulo' => 'notificaciones',
                'accion' => 'eliminar',
                'activo' => true
            ],
        ];

        foreach ($permisos as $permiso) {
            Permiso::updateOrCreate(
                ['nombre_permiso' => $permiso['nombre_permiso']],
                $permiso
            );
        }

        $this->command->info('Permisos creados exitosamente: ' . count($permisos));
    }
}

