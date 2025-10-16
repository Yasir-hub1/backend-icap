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
        'token',
        'cuotas_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'datetime',
        'monto' => 'decimal:2',
        'cuotas_id' => 'integer'
    ];

    /**
     * Relación con cuota
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'cuotas_id');
    }

    /**
     * Scope para pagos recientes
     */
    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    /**
     * Scope para pagos por rango de fechas
     */
    public function scopePorRangoFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    /**
     * Scope para pagos por monto mínimo
     */
    public function scopeMontoMinimo($query, float $monto)
    {
        return $query->where('monto', '>=', $monto);
    }

    /**
     * Accessor para obtener información del estudiante
     */
    public function getEstudianteAttribute()
    {
        return $this->cuota->planPagos->inscripcion->estudiante;
    }

    /**
     * Accessor para obtener información del programa
     */
    public function getProgramaAttribute()
    {
        return $this->cuota->planPagos->inscripcion->programa;
    }
}
