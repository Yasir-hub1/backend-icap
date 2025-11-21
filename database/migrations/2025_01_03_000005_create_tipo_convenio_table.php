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
        Schema::create('tipo_convenio', function (Blueprint $table) {
            $table->id('tipo_convenio_id');
            $table->string('nombre_tipo', 100);
            $table->text('descripcion')->nullable();
            $table->timestamps();

            // Índices
            $table->index('nombre_tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipo_convenio');
    }
};

