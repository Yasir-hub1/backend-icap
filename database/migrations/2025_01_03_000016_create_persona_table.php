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
        Schema::create('persona', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 20)->unique();
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('celular', 20)->nullable();
            $table->char('sexo', 1)->nullable(); // Según script SQL
            $table->date('fecha_nacimiento')->nullable();
            $table->text('direccion')->nullable();
            $table->string('fotografia', 500)->nullable();
            // NO incluir usuario_id aquí - la relación es unidireccional: usuario tiene persona_id
            // Esto evita relaciones circulares y mantiene la coherencia del diseño
            // La relación es: usuario.persona_id -> persona.id (usuario pertenece a persona)
            $table->timestamps();

            // Indexes
            $table->index('ci');
            $table->index(['nombre', 'apellido']);
            $table->index('fecha_nacimiento');
        });
        
        // NOTA: La relación persona <-> usuario es unidireccional:
        // - usuario.persona_id -> persona.id (usuario pertenece a persona)
        // - NO hay persona.usuario_id (evita relación circular)
        // Esta estructura permite que:
        //   1. Una persona puede tener 0 o 1 usuario (a través de usuario.persona_id)
        //   2. Un usuario pertenece a una persona (usuario.persona_id)
        //   3. Estudiante y Docente heredan de Persona (INHERITS)
        //   4. Usuario se relaciona con Persona (y por herencia, con Estudiante/Docente)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persona');
    }
};
