<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoEstudiante extends Model
{
    protected $table = 'Estado_estudiante';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_estado'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    /**
     * Relación con estudiantes
     */
    public function estudiantes(): HasMany
    {
        return $this->hasMany(Estudiante::class, 'Estado_id');
    }

    /**
     * Scope para estado activo
     */
    public function scopeActivo($query)
    {
        return $query->where('nombre_estado', 'Activo');
    }

    /**
     * Scope para estado inactivo
     */
    public function scopeInactivo($query)
    {
        return $query->where('nombre_estado', 'Inactivo');
    }
}
