<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pais extends Model
{
    protected $table = 'pais';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_pais',
        'codigo_iso',
        'codigo_telefono'
    ];

    /**
     * Relación con provincias
     */
    public function provincias(): HasMany
    {
        return $this->hasMany(Provincia::class, 'pais_id', 'id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_pais', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por código ISO
     */
    public function scopePorCodigoIso($query, string $codigo)
    {
        return $query->where('codigo_iso', $codigo);
    }
}
