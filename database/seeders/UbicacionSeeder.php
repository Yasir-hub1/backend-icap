<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UbicacionSeeder extends Seeder
{
    public function run(): void
    {
        // Países
        $paises = [
            ['nombre_pais' => 'Bolivia', 'codigo_iso' => 'BOL', 'codigo_telefono' => '+591'],
            ['nombre_pais' => 'Perú', 'codigo_iso' => 'PER', 'codigo_telefono' => '+51'],
            ['nombre_pais' => 'Ecuador', 'codigo_iso' => 'ECU', 'codigo_telefono' => '+593'],
        ];
        DB::table('pais')->insert($paises);

        // Provincias
        $provincias = [
            ['nombre_provincia' => 'La Paz', 'codigo_provincia' => 'LP', 'pais_id' => 1],
            ['nombre_provincia' => 'Cochabamba', 'codigo_provincia' => 'CB', 'pais_id' => 1],
            ['nombre_provincia' => 'Santa Cruz', 'codigo_provincia' => 'SC', 'pais_id' => 1],
        ];
        DB::table('provincia')->insert($provincias);

        // Ciudades
        $ciudades = [
            ['nombre_ciudad' => 'La Paz', 'codigo_postal' => '0001', 'provincia_id' => 1],
            ['nombre_ciudad' => 'El Alto', 'codigo_postal' => '0002', 'provincia_id' => 1],
            ['nombre_ciudad' => 'Cochabamba', 'codigo_postal' => '0003', 'provincia_id' => 2],
        ];
        DB::table('ciudad')->insert($ciudades);
    }
}

