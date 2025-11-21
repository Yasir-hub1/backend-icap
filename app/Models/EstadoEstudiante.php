<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoEstudiante extends Model
{
    protected $table = 'estado_estudiante';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nombre_estado'
    ];

    /**
     * Relación con estudiantes
     */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class, 'estado_id', 'id');
    }


    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_estado', 'ILIKE', "%{$nombre}%");
    }
}
