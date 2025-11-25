<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\CodigoHelper;

class EstudianteSeeder extends Seeder
{
    public function run(): void
    {
        // Primero crear las personas (sin usuario_id - la relación es inversa)
        $personas = [
            [
                'ci' => '4567890',
                'nombre' => 'Ana',
                'apellido' => 'López',
                'celular' => '70456789',
                'sexo' => 'F',
                'fecha_nacimiento' => '1995-03-25',
                'direccion' => 'Zona Sur, Calle 5 N° 123',
                'fotografia' => null,
            ],
            [
                'ci' => '5678901',
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'celular' => '70567890',
                'sexo' => 'M',
                'fecha_nacimiento' => '1998-07-12',
                'direccion' => 'Zona Norte, Av. Libertad N° 456',
                'fotografia' => null,
            ],
            [
                'ci' => '6789012',
                'nombre' => 'Laura',
                'apellido' => 'Martínez',
                'celular' => '70678901',
                'sexo' => 'F',
                'fecha_nacimiento' => '1996-11-08',
                'direccion' => 'Zona Central, Calle Potosí N° 789',
                'fotografia' => null,
            ],
        ];
        
        $personaIds = [];
        foreach ($personas as $persona) {
            $id = DB::table('persona')->insertGetId($persona);
            $personaIds[] = $id;
        }

        // Crear estudiantes con códigos únicos de 5 dígitos (sin usuario_id - la relación es inversa)
        $estudiantes = [
            [
                'id' => $personaIds[0],
                'ci' => '4567890',
                'nombre' => 'Ana',
                'apellido' => 'López',
                'celular' => '70456789',
                'sexo' => 'F',
                'fecha_nacimiento' => '1995-03-25',
                'direccion' => 'Zona Sur, Calle 5 N° 123',
                'fotografia' => null,
                'registro_estudiante' => CodigoHelper::generarCodigoEstudiante(),
                'provincia' => 'La Paz',
                'estado_id' => 1,
            ],
            [
                'id' => $personaIds[1],
                'ci' => '5678901',
                'nombre' => 'Juan',
                'apellido' => 'Pérez',
                'celular' => '70567890',
                'sexo' => 'M',
                'fecha_nacimiento' => '1998-07-12',
                'direccion' => 'Zona Norte, Av. Libertad N° 456',
                'fotografia' => null,
                'registro_estudiante' => CodigoHelper::generarCodigoEstudiante(),
                'provincia' => 'La Paz',
                'estado_id' => 1,
            ],
            [
                'id' => $personaIds[2],
                'ci' => '6789012',
                'nombre' => 'Laura',
                'apellido' => 'Martínez',
                'celular' => '70678901',
                'sexo' => 'F',
                'fecha_nacimiento' => '1996-11-08',
                'direccion' => 'Zona Central, Calle Potosí N° 789',
                'fotografia' => null,
                'registro_estudiante' => CodigoHelper::generarCodigoEstudiante(),
                'provincia' => 'Cochabamba',
                'estado_id' => 2,
            ],
        ];
        DB::table('estudiante')->insert($estudiantes);
    }
}

