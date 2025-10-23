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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('mensaje');
            $table->enum('tipo', ['info', 'success', 'warning', 'error', 'documento', 'pago', 'academico', 'sistema']);
            $table->boolean('leida')->default(false);
            $table->unsignedBigInteger('usuario_id');
            $table->enum('usuario_tipo', ['student', 'teacher', 'admin']);
            $table->json('datos_adicionales')->nullable();
            $table->timestamp('fecha_envio')->useCurrent();
            $table->timestamp('fecha_lectura')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['usuario_id', 'usuario_tipo']);
            $table->index(['leida', 'fecha_envio']);
            $table->index('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
