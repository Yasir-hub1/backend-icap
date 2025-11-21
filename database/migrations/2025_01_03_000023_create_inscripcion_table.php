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
        Schema::create('inscripcion', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('programa_id')->nullable();
            $table->unsignedBigInteger('plan_pago_id')->nullable();
            $table->string('estudiante_registro', 50)->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('programa_id')->references('id')->on('programa')->onDelete('set null');
            $table->foreign('plan_pago_id')->references('id')->on('plan_pago')->onDelete('set null');
            $table->foreign('estudiante_registro')->references('registro_estudiante')->on('estudiante')->onDelete('set null');

            // Índices
            $table->index('programa_id');
            $table->index('plan_pago_id');
            $table->index('estudiante_registro');
            $table->index('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripcion');
    }
};

