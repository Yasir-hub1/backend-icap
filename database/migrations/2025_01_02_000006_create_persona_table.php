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
        Schema::create('Persona', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 20)->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('celular', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('direccion')->nullable();
            $table->string('fotografia', 500)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono_fijo', 20)->nullable();
            $table->enum('genero', ['M', 'F', 'O'])->nullable(); // Masculino, Femenino, Otro
            $table->enum('estado_civil', ['soltero', 'casado', 'divorciado', 'viudo', 'union_libre'])->nullable();
            $table->string('nacionalidad', 50)->nullable();
            $table->string('lugar_nacimiento', 100)->nullable();
            $table->timestamps();

            // Indexes
            $table->index('ci');
            $table->index('email');
            $table->index(['nombre', 'apellido']);
            $table->index('fecha_nacimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Persona');
    }
};
