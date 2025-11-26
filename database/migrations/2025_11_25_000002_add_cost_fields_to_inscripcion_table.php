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
        Schema::table('inscripcion', function (Blueprint $table) {
            // Agregar campos de costo si no existen
            if (!Schema::hasColumn('inscripcion', 'costo_base')) {
                $table->decimal('costo_base', 10, 2)->nullable()->after('programa_id');
            }
            if (!Schema::hasColumn('inscripcion', 'costo_final')) {
                $table->decimal('costo_final', 10, 2)->nullable()->after('costo_base');
            }
            if (!Schema::hasColumn('inscripcion', 'descuento_id')) {
                $table->unsignedBigInteger('descuento_id')->nullable()->after('grupo_id');
                $table->foreign('descuento_id')->references('id')->on('descuento')->onDelete('set null');
                $table->index('descuento_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscripcion', function (Blueprint $table) {
            if (Schema::hasColumn('inscripcion', 'descuento_id')) {
                $table->dropForeign(['descuento_id']);
                $table->dropIndex(['descuento_id']);
                $table->dropColumn('descuento_id');
            }
            if (Schema::hasColumn('inscripcion', 'costo_base')) {
                $table->dropColumn('costo_base');
            }
            if (Schema::hasColumn('inscripcion', 'costo_final')) {
                $table->dropColumn('costo_final');
            }
        });
    }
};

