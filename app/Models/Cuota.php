<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuota extends Model
{
    protected $table = 'cuotas';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fecha_ini',
        'fecha_fin',
        'monto',
        'plan_pagos_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'plan_pagos_id' => 'integer',
        'fecha_ini' => 'date',
        'fecha_fin' => 'date',
        'monto' => 'decimal:2'
    ];

    /**
     * Relación con plan de pagos
     */
    public function planPagos(): BelongsTo
    {
        return $this->belongsTo(PlanPagos::class, 'plan_pagos_id', 'id');
    }

    /**
     * Relación con pagos
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'cuota_id', 'id');
    }

    /**
     * Scope para cuotas pendientes
     */
    public function scopePendientes($query)
    {
        return $query->whereDoesntHave('pagos');
    }

    /**
     * Scope para cuotas vencidas
     */
    public function scopeVencidas($query)
    {
        return $query->where('fecha_fin', '<', now())
                    ->whereDoesntHave('pagos');
    }

    /**
     * Scope para cuotas por vencer
     */
    public function scopePorVencer($query, int $dias = 7)
    {
        return $query->where('fecha_fin', '<=', now()->addDays($dias))
                    ->where('fecha_fin', '>', now())
                    ->whereDoesntHave('pagos');
    }

    /**
     * Accessor para verificar si está pagada
     */
    public function getEstaPagadaAttribute(): bool
    {
        return $this->pagos()->exists();
    }

    /**
     * Accessor para obtener monto pagado
     */
    public function getMontoPagadoAttribute(): float
    {
        return $this->pagos()->sum('monto');
    }

    /**
     * Accessor para obtener saldo pendiente
     */
    public function getSaldoPendienteAttribute(): float
    {
        return $this->monto - $this->monto_pagado;
    }

    /**
     * Accessor para verificar si está vencida
     */
    public function getEstaVencidaAttribute(): bool
    {
        return $this->fecha_fin < now() && !$this->esta_pagada;
    }
}
