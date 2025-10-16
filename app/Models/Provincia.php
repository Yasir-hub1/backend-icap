<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    protected $table = 'Provincia';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_provincia',
        'codigo_provincia',
        'Pais_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'Pais_id' => 'integer'
    ];

    /**
     * Relación con país
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'Pais_id');
    }

    /**
     * Relación con ciudades
     */
    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class, 'Provincia_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_provincia', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para provincias de un país específico
     */
    public function scopeDelPais($query, int $paisId)
    {
        return $query->where('Pais_id', $paisId);
    }
}
