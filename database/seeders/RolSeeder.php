<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Rol;
use App\Models\Permiso;
use Illuminate\Support\Facades\DB;

class RolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear rol ADMIN
        $rolAdmin = Rol::updateOrCreate(
            ['nombre_rol' => 'ADMIN'],
            [
                'descripcion' => 'Administrador del sistema con acceso completo a todas las funcionalidades',
                'activo' => true
            ]
        );

        // Crear rol DOCENTE
        $rolDocente = Rol::updateOrCreate(
            ['nombre_rol' => 'DOCENTE'],
            [
                'descripcion' => 'Docente con acceso a sus grupos asignados y evaluación de estudiantes',
                'activo' => true
            ]
        );

        // Crear rol ESTUDIANTE (si no existe)
        $rolEstudiante = Rol::updateOrCreate(
            ['nombre_rol' => 'ESTUDIANTE'],
            [
                'descripcion' => 'Estudiante con acceso a su información personal, inscripciones y pagos',
                'activo' => true
            ]
        );

        // Asignar TODOS los permisos al rol ADMIN
        $todosLosPermisos = Permiso::where('activo', true)->get();
        
        if ($todosLosPermisos->count() > 0) {
            $permisosAdmin = [];
            foreach ($todosLosPermisos as $permiso) {
                $permisosAdmin[$permiso->permiso_id] = ['activo' => true];
            }
            
            $rolAdmin->permisos()->sync($permisosAdmin);
            $this->command->info("Rol ADMIN creado con {$todosLosPermisos->count()} permisos asignados");
        }

        // Asignar permisos limitados al rol DOCENTE
        $permisosDocente = Permiso::whereIn('nombre_permiso', [
            'grupos_ver',
            'estudiantes_ver',
            'estudiantes_ver_detalle',
            'programas_ver',
            'inscripciones_ver',
            'inscripciones_ver_detalle'
        ])->where('activo', true)->get();

        if ($permisosDocente->count() > 0) {
            $permisosDocenteArray = [];
            foreach ($permisosDocente as $permiso) {
                $permisosDocenteArray[$permiso->permiso_id] = ['activo' => true];
            }
            
            $rolDocente->permisos()->sync($permisosDocenteArray);
            $this->command->info("Rol DOCENTE creado con {$permisosDocente->count()} permisos asignados");
        }

        // El rol ESTUDIANTE no necesita permisos en este sistema ya que tiene sus propias rutas

        $this->command->info('Roles creados exitosamente');
    }
}

