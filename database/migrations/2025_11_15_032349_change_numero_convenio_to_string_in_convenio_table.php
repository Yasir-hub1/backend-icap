<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiar el tipo de columna de integer a string
        // En PostgreSQL, primero eliminamos el índice, luego cambiamos el tipo, luego recreamos el índice
        Schema::table('convenio', function (Blueprint $table) {
            // Eliminar índice si existe
            $table->dropIndex(['numero_convenio']);
        });

        // Cambiar el tipo de columna usando SQL directo para PostgreSQL
        DB::statement('ALTER TABLE convenio ALTER COLUMN numero_convenio TYPE VARCHAR(50) USING CAST(numero_convenio AS VARCHAR(50))');

        // Recrear el índice
        Schema::table('convenio', function (Blueprint $table) {
            $table->index('numero_convenio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir el cambio: convertir de string a integer
        Schema::table('convenio', function (Blueprint $table) {
            // Eliminar índice
            $table->dropIndex(['numero_convenio']);
        });

        // Intentar convertir de vuelta a integer (solo si los valores son numéricos)
        // Nota: Esto puede fallar si hay valores no numéricos
        DB::statement('ALTER TABLE convenio ALTER COLUMN numero_convenio TYPE INTEGER USING CAST(REGEXP_REPLACE(numero_convenio, \'[^0-9]\', \'\', \'g\') AS INTEGER)');

        // Recrear el índice
        Schema::table('convenio', function (Blueprint $table) {
            $table->index('numero_convenio');
        });
    }
};
