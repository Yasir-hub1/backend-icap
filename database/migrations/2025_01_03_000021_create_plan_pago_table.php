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
        Schema::create('plan_pago', function (Blueprint $table) {
            $table->id();
            $table->integer('total_cuotas')->nullable();
            $table->decimal('monto_total', 10, 2)->nullable();
            $table->timestamps();

            // Índices
            $table->index('total_cuotas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_pago');
    }
};

