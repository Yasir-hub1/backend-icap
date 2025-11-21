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
        Schema::create('grupo_estudiante', function (Blueprint $table) {
            $table->decimal('nota', 5, 2)->nullable();
            $table->string('estado', 50)->nullable();
            $table->unsignedBigInteger('grupo_id');
            $table->string('estudiante_registro', 50);
            $table->timestamps();

            // Primary key compuesta
            $table->primary(['grupo_id', 'estudiante_registro']);

            // Foreign keys
            $table->foreign('grupo_id')->references('grupo_id')->on('grupo')->onDelete('cascade');
            $table->foreign('estudiante_registro')->references('registro_estudiante')->on('estudiante')->onDelete('cascade');

            // Índices
            $table->index('grupo_id');
            $table->index('estudiante_registro');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo_estudiante');
    }
};

