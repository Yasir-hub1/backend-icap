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
        Schema::create('programa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->integer('duracion_meses')->nullable();
            $table->integer('total_modulos')->nullable();
            $table->decimal('costo', 10, 2)->nullable();
            $table->unsignedBigInteger('rama_academica_id')->nullable();
            $table->unsignedBigInteger('version_id')->nullable();
            $table->unsignedBigInteger('tipo_programa_id')->nullable();
            $table->unsignedBigInteger('institucion_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('rama_academica_id')->references('id')->on('rama_academica')->onDelete('set null');
            $table->foreign('version_id')->references('id')->on('version')->onDelete('set null');
            $table->foreign('tipo_programa_id')->references('id')->on('tipo_programa')->onDelete('set null');
            $table->foreign('institucion_id')->references('id')->on('institucion')->onDelete('set null');

            // Índices
            $table->index('nombre');
            $table->index('rama_academica_id');
            $table->index('version_id');
            $table->index('tipo_programa_id');
            $table->index('institucion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programa');
    }
};

