<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoPrograma extends Model
{
    protected $table = 'Tipo_programa';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    /**
     * Relación con programas
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class, 'Tipo_programa_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para tipos con programas activos
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
