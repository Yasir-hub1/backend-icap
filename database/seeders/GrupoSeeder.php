<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GrupoSeeder extends Seeder
{
    public function run(): void
    {
        // Verificar si la migración de docente_id ya se ejecutó
        $docenteIdColumn = DB::selectOne(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'grupo' AND column_name = 'docente_id'"
        );

        // Obtener IDs y códigos de docentes
        $docentes = DB::table('docente')->orderBy('id')->limit(3)->get();

        if ($docentes->count() < 3) {
            throw new \Exception('Se necesitan al menos 3 docentes creados antes de ejecutar GrupoSeeder');
        }

        if ($docenteIdColumn) {
            // La migración ya se ejecutó, usar id de persona
            $docenteIds = $docentes->pluck('id')->toArray();
        } else {
            // La migración no se ejecutó, usar registro_docente
            $docenteRegistros = $docentes->pluck('registro_docente')->toArray();
        }

        // Obtener IDs y códigos de estudiantes (los primeros 3 estudiantes creados)
        $estudiantes = DB::table('estudiante')->orderBy('id')->limit(3)->get();

        if ($estudiantes->count() < 3) {
            throw new \Exception('Se necesitan al menos 3 estudiantes creados antes de ejecutar GrupoSeeder');
        }

        $estudianteIds = $estudiantes->pluck('id')->toArray();
        $estudianteRegistros = $estudiantes->pluck('registro_estudiante')->toArray();

        // Grupos
        $grupos = [];
        if ($docenteIdColumn) {
            // Usar docente_id
            $grupos = [
                [
                    'fecha_ini' => '2025-02-01',
                    'fecha_fin' => '2025-07-31',
                    'programa_id' => 1,
                    'modulo_id' => 1,
                    'docente_id' => $docenteIds[0] ?? 1,
                ],
                [
                    'fecha_ini' => '2025-03-01',
                    'fecha_fin' => '2025-08-31',
                    'programa_id' => 2,
                    'modulo_id' => 2,
                    'docente_id' => $docenteIds[1] ?? 2,
                ],
                [
                    'fecha_ini' => '2025-04-01',
                    'fecha_fin' => '2025-09-30',
                    'programa_id' => 3,
                    'modulo_id' => 3,
                    'docente_id' => $docenteIds[2] ?? 3,
                ],
            ];
        } else {
            // Usar registro_docente
            $grupos = [
                [
                    'fecha_ini' => '2025-02-01',
                    'fecha_fin' => '2025-07-31',
                    'programa_id' => 1,
                    'modulo_id' => 1,
                    'registro_docente' => $docenteRegistros[0],
                ],
                [
                    'fecha_ini' => '2025-03-01',
                    'fecha_fin' => '2025-08-31',
                    'programa_id' => 2,
                    'modulo_id' => 2,
                    'registro_docente' => $docenteRegistros[1],
                ],
                [
                    'fecha_ini' => '2025-04-01',
                    'fecha_fin' => '2025-09-30',
                    'programa_id' => 3,
                    'modulo_id' => 3,
                    'registro_docente' => $docenteRegistros[2],
                ],
            ];
        }

        $grupoIds = [];
        foreach ($grupos as $grupo) {
            // La tabla grupo usa grupo_id como primary key, no id
            // Insertar y luego obtener el grupo_id
            if ($docenteIdColumn) {
                DB::table('grupo')->insert([
                    'fecha_ini' => $grupo['fecha_ini'],
                    'fecha_fin' => $grupo['fecha_fin'],
                    'programa_id' => $grupo['programa_id'],
                    'modulo_id' => $grupo['modulo_id'],
                    'docente_id' => $grupo['docente_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                // Obtener el grupo_id recién insertado
                $grupoId = DB::table('grupo')
                    ->where('programa_id', $grupo['programa_id'])
                    ->where('modulo_id', $grupo['modulo_id'])
                    ->where('docente_id', $grupo['docente_id'])
                    ->where('fecha_ini', $grupo['fecha_ini'])
                    ->value('grupo_id');
            } else {
                DB::table('grupo')->insert([
                    'fecha_ini' => $grupo['fecha_ini'],
                    'fecha_fin' => $grupo['fecha_fin'],
                    'programa_id' => $grupo['programa_id'],
                    'modulo_id' => $grupo['modulo_id'],
                    'registro_docente' => $grupo['registro_docente'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                // Obtener el grupo_id recién insertado
                $grupoId = DB::table('grupo')
                    ->where('programa_id', $grupo['programa_id'])
                    ->where('modulo_id', $grupo['modulo_id'])
                    ->where('registro_docente', $grupo['registro_docente'])
                    ->where('fecha_ini', $grupo['fecha_ini'])
                    ->value('grupo_id');
            }
            $grupoIds[] = $grupoId;
        }

        // Grupo - Horario (relación many-to-many)
        $grupoHorarios = [
            ['grupo_id' => $grupoIds[0], 'horario_id' => 1, 'aula' => 'A-101'],
            ['grupo_id' => $grupoIds[1], 'horario_id' => 2, 'aula' => 'B-205'],
            ['grupo_id' => $grupoIds[2], 'horario_id' => 3, 'aula' => 'C-301'],
        ];
        DB::table('grupo_horario')->insert($grupoHorarios);

        // Grupo - Estudiante (relación many-to-many)
        // Verificar si la migración ya se ejecutó (usa estudiante_id) o no (usa estudiante_registro)
        $columnExists = DB::selectOne(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'grupo_estudiante' AND column_name = 'estudiante_id'"
        );

        if ($columnExists) {
            // La migración ya se ejecutó, usar estudiante_id
            $grupoEstudiantes = [
                ['grupo_id' => $grupoIds[0], 'estudiante_id' => $estudianteIds[0], 'nota' => 85.50, 'estado' => 'Aprobado'],
                ['grupo_id' => $grupoIds[0], 'estudiante_id' => $estudianteIds[1], 'nota' => 92.00, 'estado' => 'Aprobado'],
                ['grupo_id' => $grupoIds[1], 'estudiante_id' => $estudianteIds[1], 'nota' => 78.50, 'estado' => 'Aprobado'],
                ['grupo_id' => $grupoIds[2], 'estudiante_id' => $estudianteIds[2], 'nota' => 88.75, 'estado' => 'Aprobado'],
            ];
        } else {
            // La migración no se ha ejecutado, usar estudiante_registro
            $grupoEstudiantes = [
                ['grupo_id' => $grupoIds[0], 'estudiante_registro' => $estudianteRegistros[0], 'nota' => 85.50, 'estado' => 'Aprobado'],
                ['grupo_id' => $grupoIds[0], 'estudiante_registro' => $estudianteRegistros[1], 'nota' => 92.00, 'estado' => 'Aprobado'],
                ['grupo_id' => $grupoIds[1], 'estudiante_registro' => $estudianteRegistros[1], 'nota' => 78.50, 'estado' => 'Aprobado'],
                ['grupo_id' => $grupoIds[2], 'estudiante_registro' => $estudianteRegistros[2], 'nota' => 88.75, 'estado' => 'Aprobado'],
            ];
        }
        DB::table('grupo_estudiante')->insert($grupoEstudiantes);
    }
}

