<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPagos extends Model
{
    protected $table = 'plan_pagos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'monto_total',
        'total_cuotas',
        'Inscripcion_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'monto_total' => 'decimal:2',
        'total_cuotas' => 'integer',
        'Inscripcion_id' => 'integer'
    ];

    /**
     * Relación con inscripción
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'Inscripcion_id');
    }

    /**
     * Relación con cuotas
     */
    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class, 'plan_pagos_id');
    }

    /**
     * Scope para planes activos
     */
    public function scopeActivos($query)
    {
        return $query->whereHas('cuotas', function($q) {
            $q->where('fecha_fin', '>=', now());
        });
    }

    /**
     * Scope para planes por inscripción
     */
    public function scopePorInscripcion($query, int $inscripcionId)
    {
        return $query->where('Inscripcion_id', $inscripcionId);
    }

    /**
     * Accessor para calcular el monto pagado
     */
    public function getMontoPagadoAttribute(): float
    {
        return $this->cuotas()
                    ->whereHas('pagos')
                    ->sum('monto');
    }

    /**
     * Accessor para calcular el saldo pendiente
     */
    public function getSaldoPendienteAttribute(): float
    {
        return $this->monto_total - $this->monto_pagado;
    }

    /**
     * Accessor para verificar si está completo
     */
    public function getEstaCompletoAttribute(): bool
    {
        return $this->saldo_pendiente <= 0;
    }

    /**
     * Accessor para obtener el porcentaje pagado
     */
    public function getPorcentajePagadoAttribute(): float
    {
        if ($this->monto_total == 0) return 0;
        return ($this->monto_pagado / $this->monto_total) * 100;
    }
}
