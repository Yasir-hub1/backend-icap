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
        // Primero, asegurar que estudiante.id tenga una restricción única
        // En PostgreSQL con INHERITS, las restricciones UNIQUE/PRIMARY KEY no se heredan automáticamente
        // Crear constraint UNIQUE en id si no existe
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

        Schema::table('grupo_estudiante', function (Blueprint $table) {
            // Eliminar la foreign key antigua
            $table->dropForeign(['estudiante_registro']);
            // Eliminar el índice
            $table->dropIndex(['estudiante_registro']);
        });

        // Eliminar la primary key compuesta antigua
        DB::statement('ALTER TABLE grupo_estudiante DROP CONSTRAINT IF EXISTS grupo_estudiante_pkey');

        // Crear nueva columna estudiante_id
        DB::statement('ALTER TABLE grupo_estudiante ADD COLUMN estudiante_id BIGINT');

        // Mapear estudiante_registro a estudiante_id usando el id del estudiante
        DB::statement('
            UPDATE grupo_estudiante ge
            SET estudiante_id = e.id
            FROM estudiante e
            WHERE ge.estudiante_registro = e.registro_estudiante
        ');

        // Eliminar la columna antigua
        DB::statement('ALTER TABLE grupo_estudiante DROP COLUMN estudiante_registro');

        // Crear nueva primary key compuesta
        DB::statement('ALTER TABLE grupo_estudiante ADD PRIMARY KEY (grupo_id, estudiante_id)');

        Schema::table('grupo_estudiante', function (Blueprint $table) {
            // Crear la nueva foreign key que apunta a estudiante.id (que viene de persona)
            $table->foreign('estudiante_id')->references('id')->on('estudiante')->onDelete('cascade');
            // Crear el índice
            $table->index('estudiante_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grupo_estudiante', function (Blueprint $table) {
            // Eliminar la foreign key nueva
            $table->dropForeign(['estudiante_id']);
            // Eliminar el índice
            $table->dropIndex(['estudiante_id']);
        });

        // Eliminar la primary key compuesta nueva
        DB::statement('ALTER TABLE grupo_estudiante DROP CONSTRAINT grupo_estudiante_pkey');

        // Crear columna estudiante_registro
        DB::statement('ALTER TABLE grupo_estudiante ADD COLUMN estudiante_registro VARCHAR(50)');

        // Mapear estudiante_id de vuelta a estudiante_registro
        DB::statement('
            UPDATE grupo_estudiante ge
            SET estudiante_registro = e.registro_estudiante
            FROM estudiante e
            WHERE ge.estudiante_id = e.id
        ');

        // Eliminar la columna estudiante_id
        DB::statement('ALTER TABLE grupo_estudiante DROP COLUMN estudiante_id');

        // Restaurar primary key compuesta antigua
        DB::statement('ALTER TABLE grupo_estudiante ADD PRIMARY KEY (grupo_id, estudiante_registro)');

        Schema::table('grupo_estudiante', function (Blueprint $table) {
            // Restaurar la foreign key antigua
            $table->foreign('estudiante_registro')->references('registro_estudiante')->on('estudiante')->onDelete('cascade');
            // Restaurar el índice
            $table->index('estudiante_registro');
        });
    }
};

