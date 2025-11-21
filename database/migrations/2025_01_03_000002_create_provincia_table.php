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
        Schema::create('provincia', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_provincia', 100);
            $table->string('codigo_provincia', 10)->nullable();
            $table->unsignedBigInteger('pais_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('pais_id')->references('id')->on('pais')->onDelete('set null');

            // Índices
            $table->index('nombre_provincia');
            $table->index('pais_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provincia');
    }
};

