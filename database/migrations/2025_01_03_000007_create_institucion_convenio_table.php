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
        Schema::create('institucion_convenio', function (Blueprint $table) {
            $table->decimal('porcentaje_participacion', 5, 2)->nullable();
            $table->decimal('monto_asignado', 10, 2)->nullable();
            $table->string('estado', 50)->nullable();
            $table->unsignedBigInteger('institucion_id');
            $table->unsignedBigInteger('convenio_id');
            $table->timestamps();

            // Primary key compuesta
            $table->primary(['institucion_id', 'convenio_id']);

            // Foreign keys
            $table->foreign('institucion_id')->references('id')->on('institucion')->onDelete('cascade');
            $table->foreign('convenio_id')->references('convenio_id')->on('convenio')->onDelete('cascade');

            // Índices
            $table->index('institucion_id');
            $table->index('convenio_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institucion_convenio');
    }
};

