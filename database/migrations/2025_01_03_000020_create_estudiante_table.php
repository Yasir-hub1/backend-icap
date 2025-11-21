<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usar herencia nativa de PostgreSQL
        // INHERITS hereda automáticamente todos los campos de Persona, incluyendo timestamps
        DB::statement('
            CREATE TABLE "estudiante" (
                registro_estudiante VARCHAR(50) PRIMARY KEY,
                provincia VARCHAR(100),
                estado_id BIGINT,
                FOREIGN KEY (estado_id) REFERENCES "estado_estudiante"(id) ON DELETE SET NULL
            ) INHERITS ("persona")
        ');

        // Crear índices
        Schema::table('estudiante', function (Blueprint $table) {
            $table->index('estado_id');
            $table->index('registro_estudiante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudiante');
    }
};

