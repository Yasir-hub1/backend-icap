<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgramaAcademicoSeeder extends Seeder
{
    public function run(): void
    {
        // Ramas Académicas
        $ramas = [
            ['nombre' => 'Ciencias de la Educación'],
            ['nombre' => 'Ingeniería y Tecnología'],
            ['nombre' => 'Ciencias Sociales'],
        ];
        DB::table('rama_academica')->insert($ramas);

        // Versiones
        $versiones = [
            ['nombre' => 'Versión 2024', 'año' => 2024],
            ['nombre' => 'Versión 2025', 'año' => 2025],
            ['nombre' => 'Versión 2026', 'año' => 2026],
        ];
        DB::table('version')->insert($versiones);

        // Programas (ya existe TipoProgramaSeeder, asumimos que hay tipos)
        // Crear 3 programas
        $programas = [
            [
                'nombre' => 'Maestría en Educación Superior',
                'duracion_meses' => 24,
                'total_modulos' => 12,
                'costo' => 15000.00,
                'rama_academica_id' => 1,
                'version_id' => 1,
                'tipo_programa_id' => 1,
                'institucion_id' => 1,
            ],
            [
                'nombre' => 'Especialización en Tecnologías de la Información',
                'duracion_meses' => 18,
                'total_modulos' => 9,
                'costo' => 12000.00,
                'rama_academica_id' => 2,
                'version_id' => 1,
                'tipo_programa_id' => 2,
                'institucion_id' => 2,
            ],
            [
                'nombre' => 'Diplomado en Gestión de Proyectos',
                'duracion_meses' => 12,
                'total_modulos' => 6,
                'costo' => 8000.00,
                'rama_academica_id' => 3,
                'version_id' => 2,
                'tipo_programa_id' => 3,
                'institucion_id' => 3,
            ],
        ];
        DB::table('programa')->insert($programas);

        // Módulos
        $modulos = [
            [
                'nombre' => 'Metodología de la Investigación',
                'credito' => 4,
                'horas_academicas' => 60,
            ],
            [
                'nombre' => 'Didáctica General',
                'credito' => 3,
                'horas_academicas' => 45,
            ],
            [
                'nombre' => 'Tecnologías Educativas',
                'credito' => 3,
                'horas_academicas' => 45,
            ],
        ];
        DB::table('modulo')->insert($modulos);

        // Programa - Módulo (relación many-to-many)
        $programaModulos = [
            ['programa_id' => 1, 'modulo_id' => 1, 'edicion' => 1, 'estado' => 1],
            ['programa_id' => 1, 'modulo_id' => 2, 'edicion' => 1, 'estado' => 1],
            ['programa_id' => 2, 'modulo_id' => 3, 'edicion' => 1, 'estado' => 1],
        ];
        DB::table('programa_modulo')->insert($programaModulos);

        // Programa - Subprograma (relación many-to-many)
        // El programa 1 tiene como subprograma al programa 2
        $programaSubprogramas = [
            ['programa_id' => 1, 'subprograma_id' => 2],
        ];
        DB::table('programa_subprograma')->insert($programaSubprogramas);
    }
}

