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
        'metodo',
        'comprobante_path',
        'observaciones',
        'verificado',
        'fecha_verificacion',
        'verificado_por',
        'cuotas_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'datetime',
        'monto' => 'decimal:2',
        'cuotas_id' => 'integer',
        'verificado' => 'boolean',
        'fecha_verificacion' => 'datetime',
        'verificado_por' => 'integer'
    ];

    /**
     * Relación con cuota
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'cuotas_id');
    }

    /**
     * Relación con usuario verificador
     */
    public function verificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'verificado_por');
    }

    /**
     * Scope para pagos recientes
     */
    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    /**
     * Scope para pagos verificados
     */
    public function scopeVerificados($query)
    {
        return $query->where('verificado', true);
    }

    /**
     * Scope para pagos pendientes de verificación
     */
    public function scopePendientesVerificacion($query)
    {
        return $query->where('verificado', false);
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
