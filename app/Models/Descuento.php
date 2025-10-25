<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Descuento extends Model
{
    protected $table = 'descuento';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'descuento'
    ];

    protected $casts = [
        'descuento' => 'decimal:2'
    ];

    /**
     * Relación con inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'descuento_id', 'id');
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
