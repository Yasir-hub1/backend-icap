<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BitacoraSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener códigos reales de los registros creados
        $programa = DB::table('programa')->orderBy('id')->first();
        $estudiante = DB::table('estudiante')->orderBy('id')->first();
        $grupo = DB::table('grupo')->orderBy('grupo_id')->first();

        if (!$programa || !$estudiante || !$grupo) {
            throw new \Exception('Se necesitan programas, estudiantes y grupos creados antes de ejecutar BitacoraSeeder');
        }

        // Obtener el código del estudiante
        $codigoEstudiante = $estudiante->registro_estudiante ?? null;

        // Obtener el código del grupo (usar grupo_id como código)
        $codigoGrupo = $grupo->grupo_id ?? null;

        $bitacoras = [
            [
                'fecha' => '2025-01-10',
                'tabla' => 'programa',
                'codTabla' => (string)($programa->id ?? 'PROG-001'),
                'transaccion' => 'CREATE - Creación de nuevo programa académico: ' . ($programa->nombre ?? 'Maestría en Educación Superior'),
                'usuario_id' => 1,
            ],
            [
                'fecha' => '2025-01-15',
                'tabla' => 'estudiante',
                'codTabla' => $codigoEstudiante ?? 'EST-001',
                'transaccion' => 'UPDATE - Actualización de datos del estudiante ' . ($estudiante->nombre ?? 'Ana') . ' ' . ($estudiante->apellido ?? 'López'),
                'usuario_id' => 1,
            ],
            [
                'fecha' => '2025-01-20',
                'tabla' => 'grupo',
                'codTabla' => (string)($codigoGrupo ?? 'GRUP-001'),
                'transaccion' => 'DELETE - Eliminación de grupo académico',
                'usuario_id' => 1,
            ],
        ];
        DB::table('bitacora')->insert($bitacoras);
    }
}

