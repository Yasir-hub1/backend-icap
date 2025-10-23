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
            $table->id();
            $table->unsignedBigInteger('Estudiante_id');
            $table->unsignedBigInteger('Grupo_id');
            $table->decimal('nota', 5, 2)->nullable();
            $table->string('estado', 50)->default('inscrito'); // inscrito, en_curso, aprobado, reprobado, retirado
            $table->timestamps();

            // Foreign keys
            $table->foreign('Estudiante_id')->references('id')->on('Estudiante')->onDelete('cascade');
            $table->foreign('Grupo_id')->references('id')->on('Grupo')->onDelete('cascade');

            // Unique constraint
            $table->unique(['Estudiante_id', 'Grupo_id']);

            // Indexes
            $table->index('Estudiante_id');
            $table->index('Grupo_id');
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
