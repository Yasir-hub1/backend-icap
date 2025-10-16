<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Descuento extends Model
{
    protected $table = 'Descuento';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'descuento'
    ];

    protected $casts = [
        'id' => 'integer',
        'descuento' => 'decimal:2'
    ];

    /**
     * Relación con inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'Descuento_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para descuentos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('descuento', '>', 0);
    }

    /**
     * Scope para descuentos por porcentaje mínimo
     */
    public function scopePorcentajeMinimo($query, float $porcentaje)
    {
        return $query->where('descuento', '>=', $porcentaje);
    }
}
