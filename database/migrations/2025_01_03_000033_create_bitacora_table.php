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
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id('bitacora_id');
            $table->date('fecha');
            $table->string('tabla', 100);
            $table->string('codTabla', 50);
            $table->text('transaccion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            // Foreign key
            $table->foreign('usuario_id')->references('usuario_id')->on('usuario')->onDelete('set null');

            // Índices
            $table->index('usuario_id');
            $table->index('tabla');
            $table->index('fecha');
            $table->index('codTabla');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};

