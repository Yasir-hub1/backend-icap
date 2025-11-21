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
        Schema::create('permisos', function (Blueprint $table) {
            $table->id('permiso_id');
            $table->string('nombre_permiso', 100)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->string('modulo', 50); // estudiantes, programas, pagos, etc.
            $table->string('accion', 50); // crear, editar, eliminar, ver, etc.
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índices
            $table->index('nombre_permiso');
            $table->index('modulo');
            $table->index('accion');
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permisos');
    }
};
