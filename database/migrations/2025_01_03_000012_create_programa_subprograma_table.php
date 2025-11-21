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
        Schema::create('programa_subprograma', function (Blueprint $table) {
            $table->unsignedBigInteger('programa_id');
            $table->unsignedBigInteger('subprograma_id');
            $table->timestamps();

            // Primary key compuesta
            $table->primary(['programa_id', 'subprograma_id']);

            // Foreign keys
            $table->foreign('programa_id')->references('id')->on('programa')->onDelete('cascade');
            $table->foreign('subprograma_id')->references('id')->on('programa')->onDelete('cascade');

            // Índices
            $table->index('programa_id');
            $table->index('subprograma_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programa_subprograma');
    }
};

