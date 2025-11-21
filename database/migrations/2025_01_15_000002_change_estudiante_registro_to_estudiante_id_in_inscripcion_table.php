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
        // Asegurar que estudiante.id tenga una restricción única si no existe
        DB::statement('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = \'estudiante_id_unique\'
                    AND conrelid = \'estudiante\'::regclass
                ) THEN
                    ALTER TABLE estudiante ADD CONSTRAINT estudiante_id_unique UNIQUE (id);
                END IF;
            END $$;
        ');

        Schema::table('inscripcion', function (Blueprint $table) {
            // Eliminar la foreign key antigua
            $table->dropForeign(['estudiante_registro']);
            // Eliminar el índice
            $table->dropIndex(['estudiante_registro']);
        });

        // Crear nueva columna estudiante_id
        DB::statement('ALTER TABLE inscripcion ADD COLUMN estudiante_id BIGINT');

        // Mapear estudiante_registro a estudiante_id usando el id del estudiante
        DB::statement('
            UPDATE inscripcion i
            SET estudiante_id = e.id
            FROM estudiante e
            WHERE i.estudiante_registro = e.registro_estudiante
        ');

        // Eliminar la columna antigua
        DB::statement('ALTER TABLE inscripcion DROP COLUMN estudiante_registro');

        // Agregar foreign key y índice
        Schema::table('inscripcion', function (Blueprint $table) {
            $table->foreign('estudiante_id')->references('id')->on('estudiante')->onDelete('set null');
            $table->index('estudiante_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscripcion', function (Blueprint $table) {
            // Eliminar foreign key e índice de estudiante_id
            $table->dropForeign(['estudiante_id']);
            $table->dropIndex(['estudiante_id']);
        });

        // Crear columna estudiante_registro
        DB::statement('ALTER TABLE inscripcion ADD COLUMN estudiante_registro VARCHAR(50)');

        // Mapear estudiante_id de vuelta a estudiante_registro
        DB::statement('
            UPDATE inscripcion i
            SET estudiante_registro = e.registro_estudiante
            FROM estudiante e
            WHERE i.estudiante_id = e.id
        ');

        // Eliminar la columna estudiante_id
        DB::statement('ALTER TABLE inscripcion DROP COLUMN estudiante_id');

        // Agregar foreign key e índice de estudiante_registro
        Schema::table('inscripcion', function (Blueprint $table) {
            $table->foreign('estudiante_registro')->references('registro_estudiante')->on('estudiante')->onDelete('set null');
            $table->index('estudiante_registro');
        });
    }
};

