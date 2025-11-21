<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Descuento extends Model
{
    protected $table = 'descuento';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'descuento',
        'inscripcion_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'inscripcion_id' => 'integer',
        'descuento' => 'decimal:2'
    ];

    /**
     * Relación con inscripción (uno a uno)
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id', 'id');
    }

    /**
     * Scope para descuentos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('descuento', '>', 0);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por porcentaje mínimo
     */
    public function scopePorPorcentajeMinimo($query, float $porcentaje)
    {
        return $query->where('descuento', '>=', $porcentaje);
    }
}
