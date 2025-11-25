<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\DB;

class TipoDocumentoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tipos de documento para estudiantes
        $tiposDocumento = [
            [
                'nombre_entidad' => 'Carnet de Identidad - Anverso',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre_entidad' => 'Carnet de Identidad - Reverso',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre_entidad' => 'Certificado de Nacimiento',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'nombre_entidad' => 'Título de Bachiller',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($tiposDocumento as $tipo) {
            TipoDocumento::updateOrCreate(
                ['nombre_entidad' => $tipo['nombre_entidad']],
                $tipo
            );
        }

        $this->command->info('Tipos de documento creados: ' . count($tiposDocumento));
    }
}

