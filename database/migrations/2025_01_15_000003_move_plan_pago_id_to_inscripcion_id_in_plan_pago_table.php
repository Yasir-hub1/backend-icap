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
        // Asegurar que inscripcion.id tenga una restricción única si no existe
        DB::statement('
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = \'inscripcion_id_unique\'
                    AND conrelid = \'inscripcion\'::regclass
                ) THEN
                    ALTER TABLE inscripcion ADD CONSTRAINT inscripcion_id_unique UNIQUE (id);
                END IF;
            END $$;
        ');

        Schema::table('inscripcion', function (Blueprint $table) {
            // Eliminar la foreign key antigua
            $table->dropForeign(['plan_pago_id']);
            // Eliminar el índice
            $table->dropIndex(['plan_pago_id']);
        });

        // Crear nueva columna inscripcion_id en plan_pago
        DB::statement('ALTER TABLE plan_pago ADD COLUMN inscripcion_id BIGINT');

        // Mapear plan_pago_id de inscripcion a inscripcion_id en plan_pago
        DB::statement('
            UPDATE plan_pago pp
            SET inscripcion_id = i.id
            FROM inscripcion i
            WHERE i.plan_pago_id = pp.id
        ');

        // Eliminar la columna plan_pago_id de inscripcion
        DB::statement('ALTER TABLE inscripcion DROP COLUMN plan_pago_id');

        // Agregar foreign key e índice en plan_pago
        Schema::table('plan_pago', function (Blueprint $table) {
            $table->foreign('inscripcion_id')->references('id')->on('inscripcion')->onDelete('cascade');
            $table->index('inscripcion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_pago', function (Blueprint $table) {
            // Eliminar foreign key e índice de inscripcion_id
            $table->dropForeign(['inscripcion_id']);
            $table->dropIndex(['inscripcion_id']);
        });

        // Crear columna plan_pago_id en inscripcion
        DB::statement('ALTER TABLE inscripcion ADD COLUMN plan_pago_id BIGINT');

        // Mapear inscripcion_id de plan_pago de vuelta a plan_pago_id en inscripcion
        DB::statement('
            UPDATE inscripcion i
            SET plan_pago_id = pp.id
            FROM plan_pago pp
            WHERE pp.inscripcion_id = i.id
        ');

        // Eliminar la columna inscripcion_id de plan_pago
        DB::statement('ALTER TABLE plan_pago DROP COLUMN inscripcion_id');

        // Agregar foreign key e índice de plan_pago_id en inscripcion
        Schema::table('inscripcion', function (Blueprint $table) {
            $table->foreign('plan_pago_id')->references('id')->on('plan_pago')->onDelete('set null');
            $table->index('plan_pago_id');
        });
    }
};

