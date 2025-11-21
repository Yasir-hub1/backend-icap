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
        Schema::table('usuario', function (Blueprint $table) {
            // Agregar columna persona_id
            $table->unsignedBigInteger('persona_id')->nullable()->after('usuario_id');

            // Agregar foreign key
            $table->foreign('persona_id')->references('id')->on('persona')->onDelete('set null');

            // Agregar índice
            $table->index('persona_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign(['persona_id']);
            $table->dropIndex(['persona_id']);
            $table->dropColumn('persona_id');
        });
    }
};
