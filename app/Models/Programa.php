<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Programa extends Model
{
    protected $table = 'programa';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'duracion_meses',
        'total_modulos',
        'costo',
        'version_id',
        'rama_academica_id',
        'tipo_programa_id',
        'institucion_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'duracion_meses' => 'integer',
        'total_modulos' => 'integer',
        'costo' => 'decimal:2',
        'version_id' => 'integer',
        'rama_academica_id' => 'integer',
        'tipo_programa_id' => 'integer',
        'institucion_id' => 'integer'
    ];

    /**
     * Relación con versión
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'version_id', 'id');
    }

    /**
     * Relación con rama académica
     */
    public function ramaAcademica(): BelongsTo
    {
        return $this->belongsTo(RamaAcademica::class, 'rama_academica_id', 'id');
    }

    /**
     * Relación con tipo de programa
     */
    public function tipoPrograma(): BelongsTo
    {
        return $this->belongsTo(TipoPrograma::class, 'tipo_programa_id', 'id');
    }

    /**
     * Relación con institución
     */
    public function institucion(): BelongsTo
    {
        return $this->belongsTo(Institucion::class, 'institucion_id', 'id');
    }

    /**
     * Relación con inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'programa_id', 'id');
    }

    /**
     * Relación con grupos
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'programa_id', 'id');
    }

    /**
     * Relación con módulos (many-to-many)
     */
    public function modulos(): BelongsToMany
    {
        return $this->belongsToMany(Modulo::class, 'programa_modulo', 'programa_id', 'modulo_id', 'id', 'modulo_id')
                    ->withPivot('edicion');
    }

    /**
     * Relación con subprogramas (many-to-many)
     */
    public function subprogramas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'programa_subprograma', 'programa_id', 'subprograma_id', 'id', 'id');
    }

    /**
     * Relación con programas padre (many-to-many)
     */
    public function programasPadre(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'programa_subprograma', 'subprograma_id', 'programa_id', 'id', 'id');
    }

    /**
     * Scope para programas activos
     */
    public function scopeActivos($query)
    {
        return $query->whereHas('institucion', function($q) {
            $q->where('estado', 'activo');
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
        return $query->where('tipo_programa_id', $tipoId);
    }

    /**
     * Scope para programas por rama académica
     */
    public function scopePorRama($query, int $ramaId)
    {
        return $query->where('rama_academica_id', $ramaId);
    }

    /**
     * Scope para programas por institución
     */
    public function scopePorInstitucion($query, int $institucionId)
    {
        return $query->where('institucion_id', $institucionId);
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
