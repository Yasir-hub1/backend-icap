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
        Schema::table('Usuario', function (Blueprint $table) {
            // Agregar columna Persona_id
            $table->unsignedBigInteger('Persona_id')->nullable()->after('id');

            // Agregar foreign key
            $table->foreign('Persona_id')->references('id')->on('Persona')->onDelete('set null');

            // Agregar índice
            $table->index('Persona_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Usuario', function (Blueprint $table) {
            $table->dropForeign(['Persona_id']);
            $table->dropIndex(['Persona_id']);
            $table->dropColumn('Persona_id');
        });
    }
};
