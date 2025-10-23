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
            $table->timestamp('fecha');
            $table->decimal('monto', 10, 2);
            $table->string('token', 255)->nullable();
            $table->string('metodo', 50)->default('transferencia'); // qr, transferencia
            $table->string('comprobante_path', 500)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('verificado')->default(false);
            $table->timestamp('fecha_verificacion')->nullable();
            $table->unsignedBigInteger('verificado_por')->nullable();
            $table->unsignedBigInteger('cuotas_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('cuotas_id')->references('id')->on('cuotas')->onDelete('cascade');
            $table->foreign('verificado_por')->references('id')->on('Usuario')->onDelete('set null');

            // Indexes
            $table->index('cuotas_id');
            $table->index('verificado');
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
