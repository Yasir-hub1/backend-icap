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
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('ci');
            $table->index(['nombre', 'apellido']);
            $table->index('fecha_nacimiento');
            $table->index('usuario_id');
        });

        // Agregar foreign key a Usuario
        Schema::table('persona', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('usuario_id')->on('usuario')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persona');
    }
};
