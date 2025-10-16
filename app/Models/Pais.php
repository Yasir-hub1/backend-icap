<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    protected $table = 'Pais';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_pais',
        'codigo_iso',
        'codigo_telefono'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    /**
     * Relación con provincias
     */
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'Pais_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_pais', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para países activos
     */
    public function scopeActivos($query)
    {
        return $query->whereNotNull('codigo_iso');
    }
}
