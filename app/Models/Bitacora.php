<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'Bitacora';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fecha_hora',
        'tabla',
        'codTable',
        'transaccion',
        'Usuario_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_hora' => 'datetime',
        'Usuario_id' => 'integer'
    ];

    /**
     * Relación con usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'Usuario_id');
    }

    /**
     * Scope para bitácoras recientes
     */
    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha_hora', '>=', now()->subDays($dias));
    }

    /**
     * Scope para bitácoras por tabla
     */
    public function scopePorTabla($query, string $tabla)
    {
        return $query->where('tabla', $tabla);
    }

    /**
     * Scope para bitácoras por usuario
     */
    public function scopePorUsuario($query, int $usuarioId)
    {
        return $query->where('Usuario_id', $usuarioId);
    }

    /**
     * Scope para bitácoras por transacción
     */
    public function scopePorTransaccion($query, string $transaccion)
    {
        return $query->where('transaccion', 'ILIKE', "%{$transaccion}%");
    }

    /**
     * Scope para bitácoras por rango de fechas
     */
    public function scopePorRangoFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_hora', [$fechaInicio, $fechaFin]);
    }
}
