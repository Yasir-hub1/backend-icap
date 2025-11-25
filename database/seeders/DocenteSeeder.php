<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Helpers\CodigoHelper;

class DocenteSeeder extends Seeder
{
    public function run(): void
    {
        // Primero crear las personas (sin usuario_id - la relación es inversa)
        $personas = [
            [
                'ci' => '1234567',
                'nombre' => 'Carlos',
                'apellido' => 'Méndez',
                'celular' => '70123456',
                'sexo' => 'M',
                'fecha_nacimiento' => '1980-05-15',
                'direccion' => 'Av. 6 de Agosto N° 1234',
                'fotografia' => null,
            ],
            [
                'ci' => '2345678',
                'nombre' => 'María',
                'apellido' => 'González',
                'celular' => '70234567',
                'sexo' => 'F',
                'fecha_nacimiento' => '1985-08-20',
                'direccion' => 'Calle Comercio N° 567',
                'fotografia' => null,
            ],
            [
                'ci' => '3456789',
                'nombre' => 'Roberto',
                'apellido' => 'Fernández',
                'celular' => '70345678',
                'sexo' => 'M',
                'fecha_nacimiento' => '1978-12-10',
                'direccion' => 'Av. Mariscal Santa Cruz N° 890',
                'fotografia' => null,
            ],
        ];
        
        $personaIds = [];
        foreach ($personas as $persona) {
            $id = DB::table('persona')->insertGetId($persona);
            $personaIds[] = $id;
        }

        // Crear docentes con códigos únicos de 5 dígitos (sin usuario_id - la relación es inversa)
        $docentes = [
            [
                'id' => $personaIds[0],
                'ci' => '1234567',
                'nombre' => 'Carlos',
                'apellido' => 'Méndez',
                'celular' => '70123456',
                'sexo' => 'M',
                'fecha_nacimiento' => '1980-05-15',
                'direccion' => 'Av. 6 de Agosto N° 1234',
                'fotografia' => null,
                'registro_docente' => CodigoHelper::generarCodigoDocente(),
                'cargo' => 'Profesor Titular',
                'area_de_especializacion' => 'Educación Superior',
                'modalidad_de_contratacion' => 'Tiempo Completo',
            ],
            [
                'id' => $personaIds[1],
                'ci' => '2345678',
                'nombre' => 'María',
                'apellido' => 'González',
                'celular' => '70234567',
                'sexo' => 'F',
                'fecha_nacimiento' => '1985-08-20',
                'direccion' => 'Calle Comercio N° 567',
                'fotografia' => null,
                'registro_docente' => CodigoHelper::generarCodigoDocente(),
                'cargo' => 'Profesora Asociada',
                'area_de_especializacion' => 'Tecnologías de la Información',
                'modalidad_de_contratacion' => 'Tiempo Parcial',
            ],
            [
                'id' => $personaIds[2],
                'ci' => '3456789',
                'nombre' => 'Roberto',
                'apellido' => 'Fernández',
                'celular' => '70345678',
                'sexo' => 'M',
                'fecha_nacimiento' => '1978-12-10',
                'direccion' => 'Av. Mariscal Santa Cruz N° 890',
                'fotografia' => null,
                'registro_docente' => CodigoHelper::generarCodigoDocente(),
                'cargo' => 'Profesor Auxiliar',
                'area_de_especializacion' => 'Gestión de Proyectos',
                'modalidad_de_contratacion' => 'Por Horas',
            ],
        ];
        DB::table('docente')->insert($docentes);
    }
}

