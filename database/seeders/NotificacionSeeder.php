<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificacionSeeder extends Seeder
{
    public function run(): void
    {
        $notificaciones = [
            [
                'titulo' => 'Bienvenida al Sistema',
                'mensaje' => 'Bienvenido al sistema académico. Por favor complete su perfil.',
                'tipo' => 'info',
                'leida' => false,
                'usuario_id' => 1,
                'usuario_tipo' => 'admin',
                'datos_adicionales' => null,
                'fecha_lectura' => null,
            ],
            [
                'titulo' => 'Recordatorio de Pago',
                'mensaje' => 'Recuerde que tiene una cuota pendiente de pago.',
                'tipo' => 'pago',
                'leida' => false,
                'usuario_id' => 1,
                'usuario_tipo' => 'student',
                'datos_adicionales' => json_encode(['cuota_id' => 1, 'monto' => 2500.00]),
                'fecha_lectura' => null,
            ],
            [
                'titulo' => 'Nuevo Grupo Disponible',
                'mensaje' => 'Se ha abierto un nuevo grupo para el programa de Maestría.',
                'tipo' => 'academico',
                'leida' => true,
                'usuario_id' => 2,
                'usuario_tipo' => 'student',
                'datos_adicionales' => json_encode(['grupo_id' => 1, 'programa_id' => 1]),
                'fecha_lectura' => '2025-01-20 15:00:00',
            ],
        ];
        DB::table('notificaciones')->insert($notificaciones);
    }
}

