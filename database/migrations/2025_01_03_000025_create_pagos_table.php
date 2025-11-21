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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->nullable();
            $table->decimal('monto', 10, 2)->nullable();
            $table->string('token', 255)->nullable();
            $table->unsignedBigInteger('cuota_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('cuota_id')->references('id')->on('cuotas')->onDelete('set null');

            // Índices
            $table->index('cuota_id');
            $table->index('fecha');
            $table->index('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};

