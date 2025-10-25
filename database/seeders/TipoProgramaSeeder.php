<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoPrograma;

class TipoProgramaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposPrograma = [
            [
                'nombre' => 'Cursos de capacitación (menos de 800 horas)',

            ],
            [
                'nombre' => 'Cursos de actualización',

            ],
            [
                'nombre' => 'Cursos de entrenamiento',

            ],
            [
                'nombre' => 'Cursos de reforzamiento',

            ],
            [
                'nombre' => 'Cursos específicos para empresas',

            ],
            [
                'nombre' => 'Cursos abiertos',

            ]
        ];

        foreach ($tiposPrograma as $tipo) {
            TipoPrograma::updateOrCreate(
                ['nombre' => $tipo['nombre']],
                $tipo
            );
        }

        $this->command->info('✅ Tipos de programa creados exitosamente');
    }
}
