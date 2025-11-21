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
        Schema::table('grupo', function (Blueprint $table) {
            // Eliminar la foreign key antigua
            $table->dropForeign(['registro_docente']);
            // Eliminar el índice
            $table->dropIndex(['registro_docente']);
        });

        // Crear nueva columna docente_id
        DB::statement('ALTER TABLE grupo ADD COLUMN docente_id BIGINT');

        // Mapear registro_docente a docente_id usando el id del docente
        DB::statement('
            UPDATE grupo g
            SET docente_id = d.id
            FROM docente d
            WHERE g.registro_docente = d.registro_docente
        ');

        // Eliminar la columna antigua
        DB::statement('ALTER TABLE grupo DROP COLUMN registro_docente');

        Schema::table('grupo', function (Blueprint $table) {
            // Crear la nueva foreign key que apunta a docente.id (que viene de persona)
            $table->foreign('docente_id')->references('id')->on('docente')->onDelete('set null');
            // Crear el índice
            $table->index('docente_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grupo', function (Blueprint $table) {
            // Eliminar la foreign key nueva
            $table->dropForeign(['docente_id']);
            // Eliminar el índice
            $table->dropIndex(['docente_id']);
        });

        // Crear columna registro_docente
        DB::statement('ALTER TABLE grupo ADD COLUMN registro_docente VARCHAR(50)');

        // Mapear docente_id de vuelta a registro_docente
        DB::statement('
            UPDATE grupo g
            SET registro_docente = d.registro_docente
            FROM docente d
            WHERE g.docente_id = d.id
        ');

        // Eliminar la columna docente_id
        DB::statement('ALTER TABLE grupo DROP COLUMN docente_id');

        Schema::table('grupo', function (Blueprint $table) {
            // Restaurar la foreign key antigua
            $table->foreign('registro_docente')->references('registro_docente')->on('docente')->onDelete('set null');
            // Restaurar el índice
            $table->index('registro_docente');
        });
    }
};

