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
        // Actualizar tabla Estudiante para heredar de Persona
        Schema::table('Estudiante', function (Blueprint $table) {
            // Agregar foreign key a Persona
            $table->unsignedBigInteger('Persona_id')->after('id');
            $table->foreign('Persona_id')->references('id')->on('Persona')->onDelete('cascade');
            $table->index('Persona_id');

            // Remover columnas que ahora están en Persona
            $table->dropColumn([
                'ci',
                'nombre',
                'apellido',
                'celular',
                'fecha_nacimiento',
                'direccion',
                'fotografia'
            ]);
        });

        // Actualizar tabla Docente para heredar de Persona
        Schema::table('Docente', function (Blueprint $table) {
            // Agregar foreign key a Persona
            $table->unsignedBigInteger('Persona_id')->after('id');
            $table->foreign('Persona_id')->references('id')->on('Persona')->onDelete('cascade');
            $table->index('Persona_id');

            // Remover columnas que ahora están en Persona
            $table->dropColumn([
                'ci',
                'nombre',
                'apellido',
                'celular',
                'fecha_nacimiento',
                'direccion',
                'fotografia'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir cambios en Estudiante
        Schema::table('Estudiante', function (Blueprint $table) {
            $table->dropForeign(['Persona_id']);
            $table->dropIndex(['Persona_id']);
            $table->dropColumn('Persona_id');

            // Restaurar columnas
            $table->string('ci', 20);
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('celular', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('direccion')->nullable();
            $table->string('fotografia', 500)->nullable();
        });

        // Revertir cambios en Docente
        Schema::table('Docente', function (Blueprint $table) {
            $table->dropForeign(['Persona_id']);
            $table->dropIndex(['Persona_id']);
            $table->dropColumn('Persona_id');

            // Restaurar columnas
            $table->string('ci', 20);
            $table->string('nombre', 100);
            $table->string('apellido', 100);
            $table->string('celular', 20)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->text('direccion')->nullable();
            $table->string('fotografia', 500)->nullable();
        });
    }
};
