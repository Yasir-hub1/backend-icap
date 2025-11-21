<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InstitucionSeeder extends Seeder
{
    public function run(): void
    {
        $instituciones = [
            [
                'nombre' => 'Universidad Mayor de San Andrés',
                'direccion' => 'Av. Villazón N° 1995',
                'telefono' => '2200000',
                'email' => 'contacto@umsa.edu.bo',
                'sitio_web' => 'https://www.umsa.edu.bo',
                'fecha_fundacion' => '1830-10-25',
                'estado' => '1',
                'ciudad_id' => 1,
            ],
            [
                'nombre' => 'Universidad Católica Boliviana',
                'direccion' => 'Av. 14 de Septiembre N° 4807',
                'telefono' => '2782222',
                'email' => 'info@ucb.edu.bo',
                'sitio_web' => 'https://www.ucb.edu.bo',
                'fecha_fundacion' => '1966-03-15',
                'estado' => '1',
                'ciudad_id' => 1,
            ],
            [
                'nombre' => 'Instituto Tecnológico de Educación Superior',
                'direccion' => 'Av. Arce N° 1234',
                'telefono' => '2201234',
                'email' => 'contacto@ites.edu.bo',
                'sitio_web' => 'https://www.ites.edu.bo',
                'fecha_fundacion' => '2000-01-10',
                'estado' => '1',
                'ciudad_id' => 1,
            ],
        ];
        DB::table('institucion')->insert($instituciones);
    }
}

