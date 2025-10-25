<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grupo extends Model
{
    protected $table = 'grupo';
    protected $primaryKey = 'grupo_id';

    protected $fillable = [
        'fecha_ini',
        'fecha_fin',
        'registro_docente'
    ];

    protected $casts = [
        'grupo_id' => 'integer',
        'registro_docente' => 'integer',
        'fecha_ini' => 'date',
        'fecha_fin' => 'date'
    ];

    /**
     * Relación con docente
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'registro_docente', 'registro_docente');
    }

    /**
     * Relación con estudiantes (many-to-many)
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'grupo_estudiante', 'grupo_id', 'registro_estudiante', 'grupo_id', 'registro_estudiante')
                    ->withPivot(['nota', 'estado']);
    }

    /**
     * Relación con horarios (many-to-many)
     */
    public function horarios(): BelongsToMany
    {
        return $this->belongsToMany(Horario::class, 'Grupo_horario', 'grupo_id', 'horario_id', 'grupo_id', 'horario_id')
                    ->withPivot('aula');
    }

    /**
     * Scope para grupos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('fecha_fin', '>=', now());
    }

    /**
     * Scope para grupos finalizados
     */
    public function scopeFinalizados($query)
    {
        return $query->where('fecha_fin', '<', now());
    }

    /**
     * Scope para grupos por docente
     */
    public function scopePorDocente($query, int $docenteId)
    {
        return $query->where('registro_docente', $docenteId);
    }

    /**
     * Accessor para verificar si está activo
     */
    public function getEstaActivoAttribute(): bool
    {
        return $this->fecha_fin >= now();
    }

    /**
     * Accessor para obtener el número de estudiantes
     */
    public function getNumeroEstudiantesAttribute(): int
    {
        return $this->estudiantes()->count();
    }

    /**
     * Accessor para obtener la duración en días
     */
    public function getDuracionDiasAttribute(): int
    {
        return $this->fecha_ini->diffInDays($this->fecha_fin);
    }
}
