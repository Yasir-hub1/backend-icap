<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoEstudiante extends Model
{
    protected $table = 'estado_estudiante';
    protected $primaryKey = 'estado_id';
    public $timestamps = false;

    protected $fillable = [
        'nombre_estado'
    ];

    /**
     * Relación con estudiantes en grupo_estudiante
     */
    public function estudiantesEnGrupo(): HasMany
    {
        return $this->hasMany(GrupoEstudiante::class, 'estado', 'estado_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_estado', 'ILIKE', "%{$nombre}%");
    }
}
