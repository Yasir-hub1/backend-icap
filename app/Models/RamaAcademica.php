<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RamaAcademica extends Model
{
    protected $table = 'rama_academica';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nombre'
    ];

    /**
     * Relación con programas
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class, 'rama_academica_id', 'id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }
}
