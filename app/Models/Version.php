<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Version extends Model
{
    protected $table = 'version';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'año'
    ];

    protected $casts = [
        'año' => 'integer'
    ];

    /**
     * Relación con programas
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class, 'version_id', 'id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por año
     */
    public function scopePorAño($query, int $año)
    {
        return $query->where('año', $año);
    }

    /**
     * Scope para versiones recientes
     */
    public function scopeRecientes($query)
    {
        return $query->where('año', '>=', now()->year - 2);
    }
}
