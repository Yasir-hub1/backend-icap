<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConvenioSeeder extends Seeder
{
    public function run(): void
    {
        // Tipos de Convenio
        $tiposConvenio = [
            [
                'nombre_tipo' => 'Acuerdo Académico',
                'descripcion' => 'Convenios para intercambio y cooperación académica',
            ],
            [
                'nombre_tipo' => 'Convenio de Prácticas',
                'descripcion' => 'Convenios para prácticas profesionales de estudiantes',
            ],
            [
                'nombre_tipo' => 'Convenio de Investigación',
                'descripcion' => 'Convenios para proyectos de investigación y desarrollo',
            ],
        ];
        DB::table('tipo_convenio')->insert($tiposConvenio);

        // Convenios
        $convenios = [
            [
                'numero_convenio' => 'CONV-2024-001',
                'objeto_convenio' => 'Convenio marco de cooperación académica entre instituciones',
                'fecha_ini' => '2024-01-15',
                'fecha_fin' => '2026-01-15',
                'fecha_firma' => '2024-01-15',
                'moneda' => 'BOB',
                'observaciones' => 'Convenio activo para intercambio académico',
                'tipo_convenio_id' => 1,
            ],
            [
                'numero_convenio' => 'CONV-2024-002',
                'objeto_convenio' => 'Convenio para prácticas profesionales de estudiantes',
                'fecha_ini' => '2024-02-20',
                'fecha_fin' => '2025-02-20',
                'fecha_firma' => '2024-02-20',
                'moneda' => 'BOB',
                'observaciones' => 'Convenio para prácticas en empresas',
                'tipo_convenio_id' => 2,
            ],
            [
                'numero_convenio' => 'CONV-2024-003',
                'objeto_convenio' => 'Convenio de investigación y desarrollo tecnológico',
                'fecha_ini' => '2024-03-10',
                'fecha_fin' => '2027-03-10',
                'fecha_firma' => '2024-03-10',
                'moneda' => 'USD',
                'observaciones' => 'Convenio de largo plazo para proyectos de investigación',
                'tipo_convenio_id' => 3,
            ],
        ];
        DB::table('convenio')->insert($convenios);

        // Instituciones - Convenios (relación many-to-many)
        $institucionConvenios = [
            [
                'institucion_id' => 1,
                'convenio_id' => 1,
                'porcentaje_participacion' => 50.00,
                'monto_asignado' => 50000.00,
                'estado' => 'Activo',
            ],
            [
                'institucion_id' => 2,
                'convenio_id' => 2,
                'porcentaje_participacion' => 60.00,
                'monto_asignado' => 30000.00,
                'estado' => 'Activo',
            ],
            [
                'institucion_id' => 3,
                'convenio_id' => 3,
                'porcentaje_participacion' => 40.00,
                'monto_asignado' => 40000.00,
                'estado' => 'Activo',
            ],
        ];
        DB::table('institucion_convenio')->insert($institucionConvenios);
    }
}

