<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;
use App\Models\Permiso;

class RolesPermisosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear roles
        $roles = [
            [
                'nombre_rol' => 'Administrador',
                'descripcion' => 'Acceso completo al sistema',
                'activo' => true
            ],
            [
                'nombre_rol' => 'Docente',
                'descripcion' => 'Gestión de grupos y calificaciones',
                'activo' => true
            ],
            [
                'nombre_rol' => 'Estudiante',
                'descripcion' => 'Acceso a su información académica',
                'activo' => true
            ]
        ];

        foreach ($roles as $rolData) {
            Rol::updateOrCreate(
                ['nombre_rol' => $rolData['nombre_rol']],
                $rolData
            );
        }

        // Crear permisos por módulo
        $modulos = [
            'estudiantes' => [
                'ver' => 'Ver estudiantes',
                'crear' => 'Crear estudiantes',
                'editar' => 'Editar estudiantes',
                'eliminar' => 'Eliminar estudiantes',
                'activar' => 'Activar estudiantes',
                'desactivar' => 'Desactivar estudiantes'
            ],
            'programas' => [
                'ver' => 'Ver programas',
                'crear' => 'Crear programas',
                'editar' => 'Editar programas',
                'eliminar' => 'Eliminar programas'
            ],
            'grupos' => [
                'ver' => 'Ver grupos',
                'crear' => 'Crear grupos',
                'editar' => 'Editar grupos',
                'eliminar' => 'Eliminar grupos',
                'asignar_estudiantes' => 'Asignar estudiantes a grupos'
            ],
            'pagos' => [
                'ver' => 'Ver pagos',
                'crear' => 'Registrar pagos',
                'editar' => 'Editar pagos',
                'eliminar' => 'Eliminar pagos',
                'verificar' => 'Verificar pagos'
            ],
            'documentos' => [
                'ver' => 'Ver documentos',
                'subir' => 'Subir documentos',
                'editar' => 'Editar documentos',
                'eliminar' => 'Eliminar documentos',
                'validar' => 'Validar documentos'
            ],
            'calificaciones' => [
                'ver' => 'Ver calificaciones',
                'crear' => 'Registrar calificaciones',
                'editar' => 'Editar calificaciones',
                'eliminar' => 'Eliminar calificaciones'
            ],
            'reportes' => [
                'ver' => 'Ver reportes',
                'generar' => 'Generar reportes',
                'exportar' => 'Exportar reportes'
            ],
            'configuracion' => [
                'ver' => 'Ver configuración',
                'editar' => 'Editar configuración',
                'roles' => 'Gestionar roles',
                'permisos' => 'Gestionar permisos'
            ]
        ];

        foreach ($modulos as $modulo => $acciones) {
            foreach ($acciones as $accion => $descripcion) {
                Permiso::updateOrCreate(
                    ['nombre_permiso' => strtolower($modulo) . '_' . strtolower($accion)],
                    [
                        'descripcion' => $descripcion,
                        'modulo' => $modulo,
                        'accion' => $accion,
                        'activo' => true
                    ]
                );
            }
        }

        // Asignar permisos a roles
        $this->asignarPermisosARoles();
    }

    private function asignarPermisosARoles(): void
    {
        $adminRol = Rol::where('nombre_rol', 'Administrador')->first();
        $docenteRol = Rol::where('nombre_rol', 'Docente')->first();
        $estudianteRol = Rol::where('nombre_rol', 'Estudiante')->first();

        // Administrador: todos los permisos
        if ($adminRol) {
            $todosPermisos = Permiso::activos()->pluck('permiso_id')->toArray();
            $adminRol->permisos()->sync(
                array_fill_keys($todosPermisos, ['activo' => true])
            );
        }

        // Docente: permisos limitados
        if ($docenteRol) {
            $permisosDocente = Permiso::activos()
                ->whereIn('modulo', ['grupos', 'calificaciones', 'estudiantes'])
                ->whereIn('accion', ['ver', 'crear', 'editar'])
                ->pluck('permiso_id')
                ->toArray();

            $docenteRol->permisos()->sync(
                array_fill_keys($permisosDocente, ['activo' => true])
            );
        }

        // Estudiante: permisos muy limitados
        if ($estudianteRol) {
            $permisosEstudiante = Permiso::activos()
                ->whereIn('modulo', ['documentos', 'pagos'])
                ->whereIn('accion', ['ver', 'subir'])
                ->pluck('permiso_id')
                ->toArray();

            $estudianteRol->permisos()->sync(
                array_fill_keys($permisosEstudiante, ['activo' => true])
            );
        }
    }
}
