<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciudad extends Model
{
    protected $table = 'ciudad';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_ciudad',
        'codigo_postal',
        'provincia_id'
    ];

    protected $casts = [
        'provincia_id' => 'integer'
    ];

    /**
     * Relación con provincia
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'provincia_id', 'id');
    }

    /**
     * Relación con instituciones
     */
    public function instituciones(): HasMany
    {
        return $this->hasMany(Institucion::class, 'ciudad_id', 'id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_ciudad', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por provincia
     */
    public function scopePorProvincia($query, int $provinciaId)
    {
        return $query->where('provincia_id', $provinciaId);
    }
}
