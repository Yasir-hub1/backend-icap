<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Version extends Model
{
    protected $table = 'Version';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'anio'
    ];

    protected $casts = [
        'id' => 'integer',
        'anio' => 'integer'
    ];

    /**
     * Relación con programas
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class, 'version_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para versiones por año
     */
    public function scopePorAnio($query, int $anio)
    {
        return $query->where('anio', $anio);
    }

    /**
     * Scope para versiones recientes
     */
    public function scopeRecientes($query, int $anios = 5)
    {
        return $query->where('anio', '>=', now()->year - $anios);
    }

    /**
     * Scope para versiones con programas activos
     */
    public function scopeConProgramasActivos($query)
    {
        return $query->whereHas('programas', function($q) {
            $q->whereHas('institucion', function($q2) {
                $q2->where('estado', 1);
            });
        });
    }
}
