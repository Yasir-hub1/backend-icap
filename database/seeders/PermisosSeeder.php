<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;
use App\Models\Modulo;

class PermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permisos = [
            // Módulo: Estudiantes
            [
                'nombre_permiso' => 'estudiantes_ver',
                'descripcion' => 'Ver lista de estudiantes',
                'modulo' => 'Estudiantes',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_crear',
                'descripcion' => 'Crear nuevos estudiantes',
                'modulo' => 'Estudiantes',
                'accion' => 'Crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_editar',
                'descripcion' => 'Editar información de estudiantes',
                'modulo' => 'Estudiantes',
                'accion' => 'Editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_eliminar',
                'descripcion' => 'Eliminar estudiantes',
                'modulo' => 'Estudiantes',
                'accion' => 'Eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'estudiantes_activar',
                'descripcion' => 'Activar/Desactivar estudiantes',
                'modulo' => 'Estudiantes',
                'accion' => 'Activar',
                'activo' => true
            ],

            // Módulo: Docentes
            [
                'nombre_permiso' => 'docentes_ver',
                'descripcion' => 'Ver lista de docentes',
                'modulo' => 'Docentes',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'docentes_crear',
                'descripcion' => 'Crear nuevos docentes',
                'modulo' => 'Docentes',
                'accion' => 'Crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'docentes_editar',
                'descripcion' => 'Editar información de docentes',
                'modulo' => 'Docentes',
                'accion' => 'Editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'docentes_eliminar',
                'descripcion' => 'Eliminar docentes',
                'modulo' => 'Docentes',
                'accion' => 'Eliminar',
                'activo' => true
            ],

            // Módulo: Programas
            [
                'nombre_permiso' => 'programas_ver',
                'descripcion' => 'Ver programas académicos',
                'modulo' => 'Programas',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'programas_crear',
                'descripcion' => 'Crear programas académicos',
                'modulo' => 'Programas',
                'accion' => 'Crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'programas_editar',
                'descripcion' => 'Editar programas académicos',
                'modulo' => 'Programas',
                'accion' => 'Editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'programas_eliminar',
                'descripcion' => 'Eliminar programas académicos',
                'modulo' => 'Programas',
                'accion' => 'Eliminar',
                'activo' => true
            ],

            // Módulo: Grupos
            [
                'nombre_permiso' => 'grupos_ver',
                'descripcion' => 'Ver grupos',
                'modulo' => 'Grupos',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_crear',
                'descripcion' => 'Crear grupos',
                'modulo' => 'Grupos',
                'accion' => 'Crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_editar',
                'descripcion' => 'Editar grupos',
                'modulo' => 'Grupos',
                'accion' => 'Editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_eliminar',
                'descripcion' => 'Eliminar grupos',
                'modulo' => 'Grupos',
                'accion' => 'Eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'grupos_asignar_estudiantes',
                'descripcion' => 'Asignar estudiantes a grupos',
                'modulo' => 'Grupos',
                'accion' => 'Asignar',
                'activo' => true
            ],

            // Módulo: Pagos
            [
                'nombre_permiso' => 'pagos_ver',
                'descripcion' => 'Ver pagos',
                'modulo' => 'Pagos',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'pagos_verificar',
                'descripcion' => 'Verificar pagos',
                'modulo' => 'Pagos',
                'accion' => 'Verificar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'pagos_aprobar',
                'descripcion' => 'Aprobar pagos',
                'modulo' => 'Pagos',
                'accion' => 'Aprobar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'pagos_rechazar',
                'descripcion' => 'Rechazar pagos',
                'modulo' => 'Pagos',
                'accion' => 'Rechazar',
                'activo' => true
            ],

            // Módulo: Documentos
            [
                'nombre_permiso' => 'documentos_ver',
                'descripcion' => 'Ver documentos',
                'modulo' => 'Documentos',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'documentos_validar',
                'descripcion' => 'Validar documentos',
                'modulo' => 'Documentos',
                'accion' => 'Validar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'documentos_aprobar',
                'descripcion' => 'Aprobar documentos',
                'modulo' => 'Documentos',
                'accion' => 'Aprobar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'documentos_rechazar',
                'descripcion' => 'Rechazar documentos',
                'modulo' => 'Documentos',
                'accion' => 'Rechazar',
                'activo' => true
            ],

            // Módulo: Notas
            [
                'nombre_permiso' => 'notas_ver',
                'descripcion' => 'Ver notas',
                'modulo' => 'Notas',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'notas_crear',
                'descripcion' => 'Registrar notas',
                'modulo' => 'Notas',
                'accion' => 'Crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'notas_editar',
                'descripcion' => 'Editar notas',
                'modulo' => 'Notas',
                'accion' => 'Editar',
                'activo' => true
            ],

            // Módulo: Reportes
            [
                'nombre_permiso' => 'reportes_ver',
                'descripcion' => 'Ver reportes',
                'modulo' => 'Reportes',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'reportes_generar',
                'descripcion' => 'Generar reportes',
                'modulo' => 'Reportes',
                'accion' => 'Generar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'reportes_exportar',
                'descripcion' => 'Exportar reportes',
                'modulo' => 'Reportes',
                'accion' => 'Exportar',
                'activo' => true
            ],

            // Módulo: Roles y Permisos
            [
                'nombre_permiso' => 'roles_ver',
                'descripcion' => 'Ver roles',
                'modulo' => 'Roles',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'roles_crear',
                'descripcion' => 'Crear roles',
                'modulo' => 'Roles',
                'accion' => 'Crear',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'roles_editar',
                'descripcion' => 'Editar roles',
                'modulo' => 'Roles',
                'accion' => 'Editar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'roles_eliminar',
                'descripcion' => 'Eliminar roles',
                'modulo' => 'Roles',
                'accion' => 'Eliminar',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'permisos_asignar',
                'descripcion' => 'Asignar permisos a roles',
                'modulo' => 'Roles',
                'accion' => 'Asignar',
                'activo' => true
            ],

            // Módulo: Dashboard
            [
                'nombre_permiso' => 'dashboard_admin',
                'descripcion' => 'Acceder al dashboard de administrador',
                'modulo' => 'Dashboard',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'dashboard_docente',
                'descripcion' => 'Acceder al dashboard de docente',
                'modulo' => 'Dashboard',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'dashboard_estudiante',
                'descripcion' => 'Acceder al dashboard de estudiante',
                'modulo' => 'Dashboard',
                'accion' => 'Ver',
                'activo' => true
            ],

            // Módulo: Configuración
            [
                'nombre_permiso' => 'configuracion_ver',
                'descripcion' => 'Ver configuración del sistema',
                'modulo' => 'Configuracion',
                'accion' => 'Ver',
                'activo' => true
            ],
            [
                'nombre_permiso' => 'configuracion_editar',
                'descripcion' => 'Editar configuración del sistema',
                'modulo' => 'Configuracion',
                'accion' => 'Editar',
                'activo' => true
            ],
        ];

        foreach ($permisos as $permiso) {
            Permiso::updateOrCreate(
                ['nombre_permiso' => $permiso['nombre_permiso']],
                $permiso
            );
        }

        $this->command->info('✅ Permisos creados correctamente');
    }
}
