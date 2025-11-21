<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;
use App\Models\Permiso;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear rol ADMINISTRADOR con todos los permisos
        $adminRol = Rol::updateOrCreate(
            ['nombre_rol' => 'ADMIN'],
            [
                'descripcion' => 'Administrador del sistema con acceso completo a todas las funcionalidades',
                'activo' => true
            ]
        );

        // Asignar todos los permisos al administrador
        $todosLosPermisos = Permiso::where('activo', true)->pluck('permiso_id')->toArray();
        $permisosData = [];
        foreach ($todosLosPermisos as $permisoId) {
            $permisosData[$permisoId] = ['activo' => true];
        }
        $adminRol->permisos()->sync($permisosData);

        $this->command->info('✅ Rol ADMIN creado con ' . count($todosLosPermisos) . ' permisos');

        // 2. Crear rol DOCENTE
        $docenteRol = Rol::updateOrCreate(
            ['nombre_rol' => 'DOCENTE'],
            [
                'descripcion' => 'Docente con acceso a gestión de grupos, notas y visualización de estudiantes',
                'activo' => true
            ]
        );

        // Permisos para docentes
        $permisosDocente = Permiso::whereIn('nombre_permiso', [
            // Dashboard
            'dashboard_docente',

            // Grupos (solo ver y consultar sus grupos)
            'grupos_ver',

            // Estudiantes (solo ver los de sus grupos)
            'estudiantes_ver',

            // Notas (crear y editar notas de sus grupos)
            'notas_ver',
            'notas_crear',
            'notas_editar',

            // Reportes (solo de sus grupos)
            'reportes_ver',
            'reportes_generar',
        ])->pluck('permiso_id')->toArray();

        $permisosDocenteData = [];
        foreach ($permisosDocente as $permisoId) {
            $permisosDocenteData[$permisoId] = ['activo' => true];
        }
        $docenteRol->permisos()->sync($permisosDocenteData);

        $this->command->info('✅ Rol DOCENTE creado con ' . count($permisosDocente) . ' permisos');

        // 3. Crear rol ESTUDIANTE
        $estudianteRol = Rol::updateOrCreate(
            ['nombre_rol' => 'ESTUDIANTE'],
            [
                'descripcion' => 'Estudiante con acceso a su portal personal, programas, pagos y documentos',
                'activo' => true
            ]
        );

        // Permisos para estudiantes (acceso limitado a sus propios datos)
        $permisosEstudiante = Permiso::whereIn('nombre_permiso', [
            // Dashboard
            'dashboard_estudiante',

            // Ver sus propios programas
            'programas_ver',

            // Ver sus propios pagos
            'pagos_ver',

            // Ver sus propios documentos
            'documentos_ver',

            // Ver sus propias notas
            'notas_ver',
        ])->pluck('permiso_id')->toArray();

        $permisosEstudianteData = [];
        foreach ($permisosEstudiante as $permisoId) {
            $permisosEstudianteData[$permisoId] = ['activo' => true];
        }
        $estudianteRol->permisos()->sync($permisosEstudianteData);

        $this->command->info('✅ Rol ESTUDIANTE creado con ' . count($permisosEstudiante) . ' permisos');

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('   🎉 ROLES Y PERMISOS CONFIGURADOS EXITOSAMENTE');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('Roles creados:');
        $this->command->info('  ✓ ADMIN       - ' . count($todosLosPermisos) . ' permisos (acceso total)');
        $this->command->info('  ✓ DOCENTE     - ' . count($permisosDocente) . ' permisos (grupos, notas)');
        $this->command->info('  ✓ ESTUDIANTE  - ' . count($permisosEstudiante) . ' permisos (portal personal)');
        $this->command->info('');
    }
}
