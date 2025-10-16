<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Docente extends Usuario
{
    protected $table = 'Docente';

    protected $fillable = [
        'ci',
        'nombre',
        'apellido',
        'celular',
        'fecha_nacimiento',
        'direccion',
        'fotografia',
        'registro_docente',
        'cargo',
        'area_de_especializacion',
        'modalidad_de_contratacion'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_nacimiento' => 'date'
    ];

    /**
     * Relación con grupos
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'Docente_id');
    }

    /**
     * Relación con programas (a través de grupos)
     */
    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'Grupo', 'Docente_id', 'Programa_id')
                    ->distinct();
    }

    /**
     * Scope para docentes activos
     */
    public function scopeActivos($query)
    {
        return $query->whereNotNull('registro_docente');
    }

    /**
     * Scope para buscar por registro
     */
    public function scopePorRegistro($query, string $registro)
    {
        return $query->where('registro_docente', $registro);
    }

    /**
     * Scope para buscar por especialización
     */
    public function scopePorEspecializacion($query, string $especializacion)
    {
        return $query->where('area_de_especializacion', 'ILIKE', "%{$especializacion}%");
    }

    /**
     * Scope para docentes con grupos activos
     */
    public function scopeConGruposActivos($query)
    {
        return $query->whereHas('grupos', function($q) {
            $q->where('fecha_fin', '>=', now());
        });
    }

    /**
     * Accessor para obtener carga horaria actual
     */
    public function getCargaHorariaActualAttribute()
    {
        return $this->grupos()
                    ->where('fecha_fin', '>=', now())
                    ->with('horario')
                    ->get()
                    ->sum(function($grupo) {
                        return $grupo->horario->hora_fin->diffInHours($grupo->horario->hora_ini);
                    });
    }
}
