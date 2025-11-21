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
        Schema::create('grupo', function (Blueprint $table) {
            $table->id('grupo_id');
            $table->date('fecha_ini')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->unsignedBigInteger('programa_id')->nullable();
            $table->unsignedBigInteger('modulo_id')->nullable();
            $table->string('registro_docente', 50)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('programa_id')->references('id')->on('programa')->onDelete('set null');
            $table->foreign('modulo_id')->references('modulo_id')->on('modulo')->onDelete('set null');
            $table->foreign('registro_docente')->references('registro_docente')->on('docente')->onDelete('set null');

            // Índices
            $table->index('programa_id');
            $table->index('modulo_id');
            $table->index('registro_docente');
            $table->index('fecha_ini');
            $table->index('fecha_fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo');
    }
};

