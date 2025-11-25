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
        Schema::table('usuario', function (Blueprint $table) {
            // Verificar si la columna persona_id ya existe (puede haber sido creada en la migración base)
            if (!Schema::hasColumn('usuario', 'persona_id')) {
                $table->unsignedBigInteger('persona_id')->nullable()->after('usuario_id');
                $table->index('persona_id');
            }
        });

        // Agregar foreign key usando SQL directo para evitar errores si ya existe
        // Verificar si la foreign key ya existe consultando la base de datos
        try {
            $db = Schema::getConnection();
            $foreignKeyExists = $db->selectOne("
                SELECT 1 
                FROM information_schema.table_constraints 
                WHERE constraint_name = 'usuario_persona_id_foreign' 
                AND table_schema = 'public'
                AND table_name = 'usuario'
            ");

            if (!$foreignKeyExists) {
                Schema::table('usuario', function (Blueprint $table) {
                    $table->foreign('persona_id')->references('id')->on('persona')->onDelete('set null');
                });
            }
        } catch (\Exception $e) {
            // Si falla la verificación, intentar agregar la foreign key de todas formas
            // Laravel manejará el error si ya existe
            try {
                Schema::table('usuario', function (Blueprint $table) {
                    $table->foreign('persona_id')->references('id')->on('persona')->onDelete('set null');
                });
            } catch (\Exception $e2) {
                // Ignorar si la foreign key ya existe
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropForeign(['persona_id']);
            $table->dropIndex(['persona_id']);
            $table->dropColumn('persona_id');
        });
    }
};
