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
        'fecha_ini' => 'date',
        'fecha_fin' => 'date',
        'monto' => 'decimal:2',
        'plan_pagos_id' => 'integer'
    ];

    /**
     * Relación con plan de pagos
     */
    public function planPagos(): BelongsTo
    {
        return $this->belongsTo(PlanPagos::class, 'plan_pagos_id');
    }

    /**
     * Relación con pagos
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'cuotas_id');
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
     * Scope para cuotas pendientes
     */
    public function scopePendientes($query)
    {
        return $query->where('fecha_fin', '>=', now())
                    ->whereDoesntHave('pagos');
    }

    /**
     * Scope para cuotas pagadas
     */
    public function scopePagadas($query)
    {
        return $query->whereHas('pagos');
    }

    /**
     * Scope para cuotas por plan
     */
    public function scopePorPlan($query, int $planId)
    {
        return $query->where('plan_pagos_id', $planId);
    }

    /**
     * Accessor para verificar si está vencida
     */
    public function getEstaVencidaAttribute(): bool
    {
        return $this->fecha_fin < now() && !$this->pagos()->exists();
    }

    /**
     * Accessor para verificar si está pagada
     */
    public function getEstaPagadaAttribute(): bool
    {
        return $this->pagos()->exists();
    }

    /**
     * Accessor para verificar si está pendiente
     */
    public function getEstaPendienteAttribute(): bool
    {
        return !$this->esta_vencida && !$this->esta_pagada;
    }

    /**
     * Accessor para obtener el monto pagado
     */
    public function getMontoPagadoAttribute(): float
    {
        return $this->pagos()->sum('monto');
    }

    /**
     * Accessor para obtener el saldo pendiente
     */
    public function getSaldoPendienteAttribute(): float
    {
        return $this->monto - $this->monto_pagado;
    }
}
