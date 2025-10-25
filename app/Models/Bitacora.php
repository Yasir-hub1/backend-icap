<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bitacora extends Model
{
    protected $table = 'bitacora';
    protected $primaryKey = 'bitacora_id';
    public $timestamps = false;

    protected $fillable = [
        'fecha_hora',
        'tabla',
        'cod_tabla',
        'transaccion',
        'usuario_id'
    ];

    protected $casts = [
        'Bitacora_id' => 'integer',
        'usuario_id' => 'integer',
        'fecha_hora' => 'datetime'
    ];

    /**
     * Relación con usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Scope para buscar por tabla
     */
    public function scopePorTabla($query, string $tabla)
    {
        return $query->where('tabla', $tabla);
    }

    /**
     * Scope para buscar por transacción
     */
    public function scopePorTransaccion($query, string $transaccion)
    {
        return $query->where('transaccion', 'ILIKE', "%{$transaccion}%");
    }

    /**
     * Scope para buscar por usuario
     */
    public function scopePorUsuario($query, int $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    /**
     * Scope para fechas recientes
     */
    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha_hora', '>=', now()->subDays($dias));
    }
}
