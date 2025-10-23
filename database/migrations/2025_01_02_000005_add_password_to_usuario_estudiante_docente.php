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
        // Add password to Usuario table if exists
        if (Schema::hasTable('Usuario') && !Schema::hasColumn('Usuario', 'password')) {
            Schema::table('Usuario', function (Blueprint $table) {
                $table->string('password')->nullable()->after('fotografia');
            });
        }

        // Add password to Estudiante table
        if (Schema::hasTable('Estudiante') && !Schema::hasColumn('Estudiante', 'password')) {
            Schema::table('Estudiante', function (Blueprint $table) {
                $table->string('password')->nullable()->after('provincia');
            });
        }

        // Add password and rol to Docente table
        if (Schema::hasTable('Docente')) {
            if (!Schema::hasColumn('Docente', 'password')) {
                Schema::table('Docente', function (Blueprint $table) {
                    $table->string('password')->nullable()->after('modalidad_de_contratacion');
                });
            }
            if (!Schema::hasColumn('Docente', 'rol')) {
                Schema::table('Docente', function (Blueprint $table) {
                    $table->string('rol', 20)->default('DOCENTE')->after('password');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('Usuario', 'password')) {
            Schema::table('Usuario', function (Blueprint $table) {
                $table->dropColumn('password');
            });
        }

        if (Schema::hasColumn('Estudiante', 'password')) {
            Schema::table('Estudiante', function (Blueprint $table) {
                $table->dropColumn('password');
            });
        }

        if (Schema::hasColumn('Docente', 'password')) {
            Schema::table('Docente', function (Blueprint $table) {
                $table->dropColumn(['password', 'rol']);
            });
        }
    }
};
