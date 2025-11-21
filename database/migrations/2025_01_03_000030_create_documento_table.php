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
        Schema::create('documento', function (Blueprint $table) {
            $table->id('documento_id');
            $table->string('nombre_documento', 200);
            $table->string('version', 50)->nullable();
            $table->string('path_documento', 500)->nullable();
            $table->string('estado', 50)->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('tipo_documento_id')->nullable();
            $table->unsignedBigInteger('persona_id')->nullable();
            $table->unsignedBigInteger('convenio_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('tipo_documento_id')->references('tipo_documento_id')->on('tipo_documento')->onDelete('set null');
            $table->foreign('persona_id')->references('id')->on('persona')->onDelete('set null');
            $table->foreign('convenio_id')->references('convenio_id')->on('convenio')->onDelete('set null');

            // Índices
            $table->index('tipo_documento_id');
            $table->index('persona_id');
            $table->index('convenio_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documento');
    }
};

