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
        Schema::create('programa_modulo', function (Blueprint $table) {
            $table->integer('edicion')->nullable();
            $table->integer('estado')->nullable();
            $table->unsignedBigInteger('programa_id');
            $table->unsignedBigInteger('modulo_id');
            $table->timestamps();

            // Primary key compuesta
            $table->primary(['programa_id', 'modulo_id']);

            // Foreign keys
            $table->foreign('programa_id')->references('id')->on('programa')->onDelete('cascade');
            $table->foreign('modulo_id')->references('modulo_id')->on('modulo')->onDelete('cascade');

            // Índices
            $table->index('programa_id');
            $table->index('modulo_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programa_modulo');
    }
};

