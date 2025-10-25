<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EstadoEstudiante;

class EstadoEstudianteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $estadosEstudiante = [
            [
                'nombre_estado' => 'pre-inscrito',

            ],
            [
                'nombre_estado' => 'inscrito',

            ],
            [
                'nombre_estado' => 'validado',

            ],
            [
                'nombre_estado' => 'Rechazado',

            ]
        ];

        foreach ($estadosEstudiante as $estado) {
            EstadoEstudiante::updateOrCreate(
                ['nombre_estado' => $estado['nombre_estado']],
                $estado
            );
        }

        $this->command->info('✅ Estados de estudiante creados exitosamente');
    }
}
