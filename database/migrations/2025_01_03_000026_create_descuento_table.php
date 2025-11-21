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
        Schema::create('descuento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->decimal('descuento', 5, 2)->nullable();
            $table->unsignedBigInteger('inscripcion_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('inscripcion_id')->references('id')->on('inscripcion')->onDelete('set null');

            // Índices
            $table->index('inscripcion_id');
            $table->index('nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('descuento');
    }
};

