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
            CREATE TABLE "docente" (
                id BIGINT PRIMARY KEY,
                registro_docente VARCHAR(200) UNIQUE,
                cargo VARCHAR(100),
                area_de_especializacion VARCHAR(200),
                modalidad_de_contratacion VARCHAR(100)
            ) INHERITS ("persona")
        ');

        // Crear índices
        Schema::table('docente', function (Blueprint $table) {
            $table->index('registro_docente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('docente');
    }
};

