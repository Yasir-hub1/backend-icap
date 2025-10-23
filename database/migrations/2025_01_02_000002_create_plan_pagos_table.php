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
        Schema::create('plan_pagos', function (Blueprint $table) {
            $table->id();
            $table->decimal('monto_total', 10, 2);
            $table->integer('total_cuotas');
            $table->unsignedBigInteger('Inscripcion_id');
            $table->timestamps();

            // Foreign key
            $table->foreign('Inscripcion_id')->references('id')->on('Inscripcion')->onDelete('cascade');

            // Index
            $table->index('Inscripcion_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_pagos');
    }
};
