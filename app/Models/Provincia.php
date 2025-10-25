<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provincia extends Model
{
    protected $table = 'provincia';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'nombre_provincia',
        'codigo_provincia',
        'pais_id'
    ];

    protected $casts = [
        'pais_id' => 'integer'
    ];

    /**
     * Relación con país
     */
    public function pais(): BelongsTo
    {
        return $this->belongsTo(Pais::class, 'pais_id', 'id');
    }

    /**
     * Relación con ciudades
     */
    public function ciudades(): HasMany
    {
        return $this->hasMany(Ciudad::class, 'provincia_id', 'id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_provincia', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por país
     */
    public function scopePorPais($query, int $paisId)
    {
        return $query->where('pais_id', $paisId);
    }
}
