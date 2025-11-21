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
        Schema::create('institucion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('direccion', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('sitio_web', 255)->nullable();
            $table->date('fecha_fundacion')->nullable();
            $table->string('estado', 50)->nullable();
            $table->unsignedBigInteger('ciudad_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('ciudad_id')->references('id')->on('ciudad')->onDelete('set null');

            // Índices
            $table->index('nombre');
            $table->index('ciudad_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institucion');
    }
};

