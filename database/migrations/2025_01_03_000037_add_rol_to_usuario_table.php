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
            $table->unsignedBigInteger('rol_id')->nullable()->after('usuario_id');
            $table->foreign('rol_id')->references('rol_id')->on('roles')->onDelete('set null');
            $table->index('rol_id');
        });
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
