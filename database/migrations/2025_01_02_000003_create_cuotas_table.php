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
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_ini');
            $table->date('fecha_fin');
            $table->decimal('monto', 10, 2);
            $table->unsignedBigInteger('plan_pagos_id');
            $table->timestamps();

            // Foreign key
            $table->foreign('plan_pagos_id')->references('id')->on('plan_pagos')->onDelete('cascade');

            // Indexes
            $table->index('plan_pagos_id');
            $table->index('fecha_ini');
            $table->index('fecha_fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
