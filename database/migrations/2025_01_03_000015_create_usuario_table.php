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
        Schema::create('usuario', function (Blueprint $table) {
            $table->id('usuario_id');
            $table->string('email', 255)->unique();
            $table->string('password', 255);

            // Relación con persona (persona_id puede ser nullable para usuarios del sistema sin persona)
            // La foreign key se agregará en migración posterior cuando la tabla persona exista
            $table->unsignedBigInteger('persona_id')->nullable()->after('usuario_id');

            // Relación con rol (rol_id puede ser nullable inicialmente)
            // La foreign key se agregará en migración posterior cuando la tabla roles exista
            $table->unsignedBigInteger('rol_id')->nullable()->after('persona_id');

            $table->timestamps();

            // Índices
            $table->index('email');
            $table->index('persona_id');
            $table->index('rol_id');
        });

        // NOTA: Las foreign keys se agregarán en migraciones posteriores:
        // - persona_id FK se agrega en: 2025_01_03_000032_add_persona_id_to_usuario_table.php
        // - rol_id FK se agrega en: 2025_01_03_000037_add_rol_to_usuario_table.php
        // Esto es necesario porque usuario se crea antes que persona y roles
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};

