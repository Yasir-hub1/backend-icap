<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class CodigoHelper
{
    /**
     * Genera un código único de 5 dígitos para estudiantes
     *
     * @return string Código de 5 dígitos (00001-99999)
     */
    public static function generarCodigoEstudiante(): string
    {
        do {
            // Generar número aleatorio de 5 dígitos (10000-99999)
            $codigo = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);

            // Verificar que no exista
            $existe = DB::table('estudiante')
                ->where('registro_estudiante', $codigo)
                ->exists();
        } while ($existe);

        return $codigo;
    }

    /**
     * Genera un código único de 5 dígitos para docentes
     *
     * @return string Código de 5 dígitos (00001-99999)
     */
    public static function generarCodigoDocente(): string
    {
        do {
            // Generar número aleatorio de 5 dígitos (10000-99999)
            $codigo = str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);

            // Verificar que no exista
            $existe = DB::table('docente')
                ->where('registro_docente', $codigo)
                ->exists();
        } while ($existe);

        return $codigo;
    }
}

