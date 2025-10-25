<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fecha',
        'monto',
        'saldo',
        'cuota_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'cuota_id' => 'integer',
        'fecha' => 'date',
        'monto' => 'decimal:2',
        'saldo' => 'decimal:2'
    ];

    /**
     * Relación con cuota
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'cuota_id', 'id');
    }

    /**
     * Scope para pagos recientes
     */
    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    /**
     * Scope para pagos por cuota
     */
    public function scopePorCuota($query, int $cuotaId)
    {
        return $query->where('cuota_id', $cuotaId);
    }

    /**
     * Scope para pagos por rango de fechas
     */
    public function scopePorRangoFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    /**
     * Accessor para verificar si es pago completo
     */
    public function getEsPagoCompletoAttribute(): bool
    {
        return $this->saldo <= 0;
    }

    /**
     * Accessor para obtener porcentaje pagado
     */
    public function getPorcentajePagadoAttribute(): float
    {
        $cuota = $this->cuota;
        return $cuota ? ($this->monto / $cuota->monto) * 100 : 0;
    }
}
