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
        Schema::table('pagos', function (Blueprint $table) {
            $table->boolean('verificado')->default(false)->after('token');
            $table->timestamp('fecha_verificacion')->nullable()->after('verificado');
            $table->unsignedBigInteger('verificado_por')->nullable()->after('fecha_verificacion');
            $table->text('observaciones')->nullable()->after('verificado_por');
            $table->string('metodo', 50)->nullable()->after('observaciones');

            // Foreign key
            $table->foreign('verificado_por')->references('usuario_id')->on('usuario')->onDelete('set null');

            // Índices
            $table->index('verificado');
            $table->index('fecha_verificacion');
            $table->index('metodo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['verificado_por']);
            $table->dropIndex(['verificado']);
            $table->dropIndex(['fecha_verificacion']);
            $table->dropIndex(['metodo']);
            $table->dropColumn(['verificado', 'fecha_verificacion', 'verificado_por', 'observaciones', 'metodo']);
        });
    }
};
