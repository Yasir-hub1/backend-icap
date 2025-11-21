<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Grupo extends Model
{
    protected $table = 'grupo';
    protected $primaryKey = 'grupo_id';
    public $timestamps = true;

    protected $fillable = [
        'fecha_ini',
        'fecha_fin',
        'programa_id',
        'modulo_id',
        'docente_id'
    ];

    protected $casts = [
        'grupo_id' => 'integer',
        'fecha_ini' => 'date',
        'fecha_fin' => 'date'
    ];

    /**
     * Atributos adicionales que se deben agregar a la serialización
     */
    protected $appends = ['id'];

    /**
     * Accessor para compatibilidad: devolver grupo_id como id también
     */
    public function getIdAttribute()
    {
        return $this->attributes['grupo_id'] ?? null;
    }

    /**
     * Relación con programa
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'programa_id', 'id');
    }

    /**
     * Relación con módulo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id', 'modulo_id');
    }

    /**
     * Relación con docente
     */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Docente::class, 'docente_id', 'id');
    }

    /**
     * Relación con estudiantes (many-to-many)
     * La tabla grupo_estudiante usa grupo_id y estudiante_id
     * El modelo Estudiante usa id como primary key (que viene de persona)
     */
    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Estudiante::class, 'grupo_estudiante', 'grupo_id', 'estudiante_id', 'grupo_id', 'id')
                    ->withPivot(['nota', 'estado']);
    }

    /**
     * Relación con horarios (many-to-many)
     */
    public function horarios(): BelongsToMany
    {
        return $this->belongsToMany(Horario::class, 'grupo_horario', 'grupo_id', 'horario_id', 'grupo_id', 'horario_id')
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
        return $query->where('docente_id', $docenteId);
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
