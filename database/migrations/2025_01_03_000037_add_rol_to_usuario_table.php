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
            // Verificar si la columna rol_id ya existe (puede haber sido creada en la migración base)
            if (!Schema::hasColumn('usuario', 'rol_id')) {
                $table->unsignedBigInteger('rol_id')->nullable()->after('persona_id');
                $table->index('rol_id');
            }
        });

        // Agregar foreign key usando SQL directo para evitar errores si ya existe
        // Verificar si la foreign key ya existe consultando la base de datos
        try {
            $db = Schema::getConnection();
            $foreignKeyExists = $db->selectOne("
                SELECT 1 
                FROM information_schema.table_constraints 
                WHERE constraint_name = 'usuario_rol_id_foreign' 
                AND table_schema = 'public'
                AND table_name = 'usuario'
            ");

            if (!$foreignKeyExists) {
                Schema::table('usuario', function (Blueprint $table) {
                    $table->foreign('rol_id')->references('rol_id')->on('roles')->onDelete('set null');
                });
            }
        } catch (\Exception $e) {
            // Si falla la verificación, intentar agregar la foreign key de todas formas
            // Laravel manejará el error si ya existe
            try {
                Schema::table('usuario', function (Blueprint $table) {
                    $table->foreign('rol_id')->references('rol_id')->on('roles')->onDelete('set null');
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
            $table->dropForeign(['rol_id']);
            $table->dropIndex(['rol_id']);
            $table->dropColumn('rol_id');
        });
    }
};
