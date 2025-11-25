<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentoSeeder extends Seeder
{
    public function run(): void
    {
        // Tipos de Documento
        $tiposDocumento = [
            ['nombre_entidad' => 'Carnet de Identidad - Anverso'],
            ['nombre_entidad' => 'Carnet de Identidad - Reverso'],
            ['nombre_entidad' => 'Certificado de Nacimiento'],
            ['nombre_entidad' => 'Título de Bachiller'],

        ];
        DB::table('tipo_documento')->insert($tiposDocumento);

        // Documentos
        // Asumimos que las personas tienen id 1, 2, 3 (estudiantes)
        $documentos = [
            [
                'persona_id' => 1, // Ana López
                'tipo_documento_id' => 1,
                'nombre_documento' => 'Cédula de Identidad - Ana López',
                'version' => '1.0',
                'path_documento' => '/documentos/estudiantes/ci_ana_lopez.pdf',
                'estado' => 'aprobado',
                'observaciones' => 'Documento verificado correctamente',
                'convenio_id' => null,
            ],
            [
                'persona_id' => 1,
                'tipo_documento_id' => 2,
                'nombre_documento' => 'Título de Bachiller - Ana López',
                'version' => '1.0',
                'path_documento' => '/documentos/estudiantes/titulo_ana_lopez.pdf',
                'estado' => 'aprobado',
                'observaciones' => null,
                'convenio_id' => null,
            ],
            [
                'persona_id' => 2, // Juan Pérez
                'tipo_documento_id' => 1,
                'nombre_documento' => 'Cédula de Identidad - Juan Pérez',
                'version' => '1.0',
                'path_documento' => '/documentos/estudiantes/ci_juan_perez.pdf',
                'estado' => 'pendiente',
                'observaciones' => 'Pendiente de revisión',
                'convenio_id' => null,
            ],
        ];
        DB::table('documento')->insert($documentos);
    }
}

