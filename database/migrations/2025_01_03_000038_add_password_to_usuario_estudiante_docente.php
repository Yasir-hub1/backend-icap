<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Según el script SQL, solo Usuario tiene password
        // Estudiante y Docente NO tienen password (heredan de Persona)
        // Esta migración ya no es necesaria porque Usuario se crea con password en 2025_01_03_000015
        // Se mantiene vacía para no romper el historial de migraciones
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No hay cambios que revertir
    }
};
