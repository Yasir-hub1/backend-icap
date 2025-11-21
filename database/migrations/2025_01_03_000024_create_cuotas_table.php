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
            $table->date('fecha_ini')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->unsignedBigInteger('plan_pago_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('plan_pago_id')->references('id')->on('plan_pago')->onDelete('set null');

            // Índices
            $table->index('plan_pago_id');
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

