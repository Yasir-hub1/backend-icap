<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Programa extends Model
{
    protected $table = 'Programa';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'duracion_meses',
        'total_modulos',
        'costo',
        'Rama_academica_id',
        'Tipo_programa_id',
        'Programa_id',
        'Institucion_id',
        'version_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'duracion_meses' => 'integer',
        'total_modulos' => 'integer',
        'costo' => 'decimal:2',
        'Rama_academica_id' => 'integer',
        'Tipo_programa_id' => 'integer',
        'Programa_id' => 'integer',
        'Institucion_id' => 'integer',
        'version_id' => 'integer'
    ];

    /**
     * Relación con rama académica
     */
    public function ramaAcademica(): BelongsTo
    {
        return $this->belongsTo(RamaAcademica::class, 'Rama_academica_id');
    }

    /**
     * Relación con tipo de programa
     */
    public function tipoPrograma(): BelongsTo
    {
        return $this->belongsTo(TipoPrograma::class, 'Tipo_programa_id');
    }

    /**
     * Relación con institución
     */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'Institucion_id');
    }

    /**
     * Relación con versión
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'version_id');
    }

    /**
     * Relación con programa padre (self-referencing directo)
     */
    public function programaPadre(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'Programa_id');
    }

    /**
     * Relación con subprogramas (self-referencing directo)
     */
    public function subprogramas(): HasMany
    {
        return $this->hasMany(Programa::class, 'Programa_id');
    }

    /**
     * Relación many-to-many con programas padre (Programa_subprograma)
     */
    public function programasPadre(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'Programa_subprograma', 'Programa_hijo_id', 'Programa_padre_id');
    }

    /**
     * Relación many-to-many con programas hijo (Programa_subprograma)
     */
    public function programasHijo(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'Programa_subprograma', 'Programa_padre_id', 'Programa_hijo_id');
    }

    /**
     * Relación con módulos (many-to-many)
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'Programa_modulo', 'Programa_id', 'Modulo_id')
                    ->withPivot('edicion')
                    ->withTimestamps();
    }

    /**
     * Relación con inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'Programa_id');
    }

    /**
     * Relación con grupos
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'Programa_id');
    }

    /**
     * Scope para programas activos
     */
    public function scopeActivos($query)
    {
        return $query->whereHas('institucion', function($q) {
            $q->where('estado', 1);
        });
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para programas por tipo
     */
    public function scopePorTipo($query, int $tipoId)
    {
        return $query->where('Tipo_programa_id', $tipoId);
    }

    /**
     * Scope para programas por rama académica
     */
    public function scopePorRama($query, int $ramaId)
    {
        return $query->where('Rama_academica_id', $ramaId);
    }

    /**
     * Accessor para determinar si es curso o programa
     */
    public function getEsCursoAttribute(): bool
    {
        return $this->duracion_meses < 12; // Menos de 12 meses = curso
    }

    /**
     * Accessor para obtener el nombre completo
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} - {$this->tipoPrograma->nombre}";
    }
}
