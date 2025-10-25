<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPagos extends Model
{
    protected $table = 'plan_pagos';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'monto_total',
        'total_cuotas',
        'inscripcion_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'monto_total' => 'decimal:2',
        'total_cuotas' => 'integer',
        'inscripcion_id' => 'integer'
    ];

    /**
     * Relación con inscripción
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id', 'id');
    }

    /**
     * Relación con cuotas
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class, 'plan_pagos_id', 'id');
    }

    /**
     * Scope para planes completos
     */
    public function scopeCompletos($query)
    {
        return $query->whereHas('cuotas', function($q) {
            $q->whereDoesntHave('pagos');
        }, '=', 0);
    }

    /**
     * Scope para planes pendientes
     */
    public function scopePendientes($query)
    {
        return $query->whereHas('cuotas', function($q) {
            $q->whereDoesntHave('pagos');
        });
    }

    /**
     * Accessor para verificar si está completo
     */
    public function getEstaCompletoAttribute(): bool
    {
        return $this->cuotas()->whereDoesntHave('pagos')->count() === 0;
    }

    /**
     * Accessor para obtener monto pagado
     */
    public function getMontoPagadoAttribute(): float
    {
        return $this->cuotas()->with('pagos')->get()->sum(function($cuota) {
            return $cuota->pagos->sum('monto');
        });
    }

    /**
     * Accessor para obtener monto pendiente
     */
    public function getMontoPendienteAttribute(): float
    {
        return $this->monto_total - $this->monto_pagado;
    }
}
