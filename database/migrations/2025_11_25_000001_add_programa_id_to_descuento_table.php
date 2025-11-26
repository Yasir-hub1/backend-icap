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
        Schema::table('descuento', function (Blueprint $table) {
            // Agregar programa_id si no existe
            if (!Schema::hasColumn('descuento', 'programa_id')) {
                $table->unsignedBigInteger('programa_id')->nullable()->after('id');
                $table->foreign('programa_id')->references('id')->on('programa')->onDelete('cascade');
                $table->index('programa_id');
            }
            
            // Agregar fecha_inicio si no existe
            if (!Schema::hasColumn('descuento', 'fecha_inicio')) {
                $table->date('fecha_inicio')->nullable()->after('descuento');
            }
            
            // Agregar fecha_fin si no existe
            if (!Schema::hasColumn('descuento', 'fecha_fin')) {
                $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('descuento', function (Blueprint $table) {
            if (Schema::hasColumn('descuento', 'programa_id')) {
                $table->dropForeign(['programa_id']);
                $table->dropIndex(['programa_id']);
                $table->dropColumn('programa_id');
            }
            if (Schema::hasColumn('descuento', 'fecha_inicio')) {
                $table->dropColumn('fecha_inicio');
            }
            if (Schema::hasColumn('descuento', 'fecha_fin')) {
                $table->dropColumn('fecha_fin');
            }
        });
    }
};

