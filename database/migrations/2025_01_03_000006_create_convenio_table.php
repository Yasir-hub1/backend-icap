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
        Schema::create('convenio', function (Blueprint $table) {
            $table->id('convenio_id');
            $table->integer('numero_convenio');
            $table->text('objeto_convenio')->nullable();
            $table->date('fecha_ini')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->date('fecha_firma')->nullable();
            $table->string('moneda', 20)->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('tipo_convenio_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('tipo_convenio_id')->references('tipo_convenio_id')->on('tipo_convenio')->onDelete('set null');

            // Índices
            $table->index('numero_convenio');
            $table->index('tipo_convenio_id');
            $table->index('fecha_ini');
            $table->index('fecha_fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('convenio');
    }
};

