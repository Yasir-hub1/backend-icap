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
        Schema::create('grupo_horario', function (Blueprint $table) {
            $table->string('aula', 50)->nullable();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('horario_id');
            $table->timestamps();

            // Primary key compuesta
            $table->primary(['grupo_id', 'horario_id']);

            // Foreign keys
            $table->foreign('grupo_id')->references('grupo_id')->on('grupo')->onDelete('cascade');
            $table->foreign('horario_id')->references('horario_id')->on('horario')->onDelete('cascade');

            // Índices
            $table->index('grupo_id');
            $table->index('horario_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo_horario');
    }
};

