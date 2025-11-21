<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HorarioSeeder extends Seeder
{
    public function run(): void
    {
        $horarios = [
            [
                'dias' => 'Lunes, Miércoles, Viernes',
                'hora_ini' => '08:00:00',
                'hora_fin' => '10:00:00',
            ],
            [
                'dias' => 'Martes, Jueves',
                'hora_ini' => '14:00:00',
                'hora_fin' => '16:30:00',
            ],
            [
                'dias' => 'Sábado',
                'hora_ini' => '09:00:00',
                'hora_fin' => '13:00:00',
            ],
        ];
        DB::table('horario')->insert($horarios);
    }
}

