<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'fecha',
        'monto',
        'token',
        'cuota_id',
        'verificado',
        'fecha_verificacion',
        'verificado_por',
        'observaciones',
        'metodo',
        'comprobante'
    ];

    protected $casts = [
        'id' => 'integer',
        'cuota_id' => 'integer',
        'fecha' => 'date',
        'monto' => 'decimal:2',
        'verificado' => 'boolean',
        'fecha_verificacion' => 'datetime',
        'verificado_por' => 'integer'
    ];

    /**
     * Relación con cuota
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'cuota_id', 'id');
    }

    /**
     * Relación con usuario que verificó el pago
     */
    public function verificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'verificado_por', 'usuario_id');
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
     * Accessor para obtener porcentaje pagado
     */
    public function getPorcentajePagadoAttribute(): float
    {
        $cuota = $this->cuota;
        return $cuota && $cuota->monto > 0 ? ($this->monto / $cuota->monto) * 100 : 0;
    }
}
