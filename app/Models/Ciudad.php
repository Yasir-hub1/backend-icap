<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciudad extends Model
{
    protected $table = 'Ciudad';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_ciudad',
        'codigo_postal',
        'Provincia_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'Provincia_id' => 'integer'
    ];

    /**
     * Relación con provincia
     */
    public function provincia(): BelongsTo
    {
        return $this->belongsTo(Provincia::class, 'Provincia_id');
    }

    /**
     * Relación con instituciones
     */
    public function instituciones(): HasMany
    {
        return $this->hasMany(Institucion::class, 'ciudad_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_ciudad', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para ciudades de una provincia específica
     */
    public function scopeDeProvincia($query, int $provinciaId)
    {
        return $query->where('Provincia_id', $provinciaId);
    }

    /**
     * Accessor para obtener el nombre completo con provincia y país
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre_ciudad}, {$this->provincia->nombre_provincia}, {$this->provincia->pais->nombre_pais}";
    }
}
