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
        Schema::create('ciudad', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_ciudad', 100);
            $table->string('codigo_postal', 20)->nullable();
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('provincia_id')->references('id')->on('provincia')->onDelete('set null');

            // Índices
            $table->index('nombre_ciudad');
            $table->index('provincia_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ciudad');
    }
};

