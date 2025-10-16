<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grupo extends Model
{
    protected $table = 'Grupo';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fecha_ini',
        'fecha_fin',
        'Programa_id',
        'Docente_id',
        'horario_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_ini' => 'date',
        'fecha_fin' => 'date',
        'Programa_id' => 'integer',
        'Docente_id' => 'integer',
        'horario_id' => 'integer'
    ];

    /**
     * Relación con programa
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'Programa_id');
    }

    /**
     * Relación con docente
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'Docente_id');
    }

    /**
     * Relación con horario
     */
    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    /**
     * Relación con estudiantes (many-to-many)
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'grupo_estudiante', 'Grupo_id', 'Estudiante_id')
                    ->withPivot(['nota', 'estado'])
                    ->withTimestamps();
    }

    /**
     * Relación con horarios adicionales (many-to-many)
     */
    public function horariosAdicionales(): BelongsToMany
    {
        return $this->belongsToMany(Horario::class, 'Grupo_horario', 'Grupo_id', 'Horario_id')
                    ->withPivot('aula')
                    ->withTimestamps();
    }

    /**
     * Relación con estudiantes a través de grupo_estudiante
     */
    public function estudiantesGrupo(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'grupo_estudiante', 'Grupo_id', 'Estudiante_id')
                    ->withPivot(['nota', 'estado'])
                    ->withTimestamps();
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
     * Scope para grupos por programa
     */
    public function scopePorPrograma($query, int $programaId)
    {
        return $query->where('Programa_id', $programaId);
    }

    /**
     * Scope para grupos por docente
     */
    public function scopePorDocente($query, int $docenteId)
    {
        return $query->where('Docente_id', $docenteId);
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
