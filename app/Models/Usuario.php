<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usuario extends Model
{
    protected $table = 'Usuario';
    protected $primaryKey = 'id';

    protected $fillable = [
        'ci',
        'nombre',
        'apellido',
        'celular',
        'fecha_nacimiento',
        'direccion',
        'fotografia',
        'clave'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_nacimiento' => 'date'
    ];

    /**
     * Relación con bitácora
     */
    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'Usuario_id');
    }

    /**
     * Scope para buscar por CI
     */
    public function scopePorCi($query, string $ci)
    {
        return $query->where('ci', $ci);
    }

    /**
     * Scope para buscar por nombre completo
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where(function($q) use ($nombre) {
            $q->where('nombre', 'ILIKE', "%{$nombre}%")
              ->orWhere('apellido', 'ILIKE', "%{$nombre}%");
        });
    }

    /**
     * Accessor para nombre completo
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    /**
     * Accessor para edad
     */
    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }
}
