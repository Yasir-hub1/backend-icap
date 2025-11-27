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
            // Campos para PagoFácil QR
            if (!Schema::hasColumn('pagos', 'nro_pago')) {
                $table->string('nro_pago', 50)->unique()->nullable()->after('id');
            }
            if (!Schema::hasColumn('pagos', 'nro_transaccion')) {
                $table->string('nro_transaccion', 100)->nullable()->after('nro_pago');
            }
            if (!Schema::hasColumn('pagos', 'estado_pagofacil')) {
                $table->string('estado_pagofacil', 50)->nullable()->after('verificado');
            }
            if (!Schema::hasColumn('pagos', 'qr_image')) {
                $table->string('qr_image')->nullable()->after('comprobante');
            }
            if (!Schema::hasColumn('pagos', 'qr_expires_at')) {
                $table->timestamp('qr_expires_at')->nullable()->after('qr_image');
            }
            if (!Schema::hasColumn('pagos', 'payment_method_id')) {
                $table->integer('payment_method_id')->nullable()->after('qr_expires_at');
            }
            if (!Schema::hasColumn('pagos', 'payment_info')) {
                $table->json('payment_info')->nullable()->after('payment_method_id');
            }
            if (!Schema::hasColumn('pagos', 'callback_data')) {
                $table->json('callback_data')->nullable()->after('payment_info');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn([
                'nro_pago',
                'nro_transaccion',
                'estado_pagofacil',
                'qr_image',
                'qr_expires_at',
                'payment_method_id',
                'payment_info',
                'callback_data'
            ]);
        });
    }
};
