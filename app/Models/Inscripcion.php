<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inscripcion extends Model
{
    protected $table = 'inscripcion';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'fecha',
        'estudiante_id',
        'programa_id',
        'descuento_id',
        'costo_base',
        'costo_final'
    ];

    protected $casts = [
        'id' => 'integer',
        'estudiante_id' => 'integer',
        'programa_id' => 'integer',

        'descuento_id' => 'integer',
        'costo_base' => 'decimal:2',
        'costo_final' => 'decimal:2',
        'fecha' => 'date'
    ];

    /**
     * Relación con estudiante
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id', 'id');
    }

    /**
     * Relación con programa
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'programa_id', 'id');
    }

    /**
     * Relación con plan de pagos (uno a uno)
     */
    public function planPago(): HasOne
    {
        return $this->hasOne(PlanPagos::class, 'inscripcion_id', 'id');
    }

    /**
     * Obtener grupos del estudiante para este programa
     * La relación estudiante-grupo se maneja a través de grupo_estudiante (pivot table)
     * No hay relación directa entre inscripcion y grupo
     */
    public function obtenerGrupos()
    {
        return $this->estudiante->grupos()
            ->where('programa_id', $this->programa_id)
            ->get();
    }

    /**
     * Relación con descuento aplicado (muchos a uno)
     */
    public function descuento(): BelongsTo
    {
        return $this->belongsTo(Descuento::class, 'descuento_id', 'id');
    }

    /**
     * Relación con descuento antiguo (uno a uno) - Mantener por compatibilidad
     */
    public function descuentoAntiguo(): HasOne
    {
        return $this->hasOne(Descuento::class, 'inscripcion_id', 'id');
    }

    /**
     * Scope para inscripciones recientes
     */
    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    /**
     * Scope para inscripciones por estudiante
     */
    public function scopePorEstudiante($query, $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
    }

    /**
     * Scope para inscripciones con descuento
     */
    public function scopeConDescuento($query)
    {
        return $query->whereHas('descuento');
    }

    /**
     * Scope para inscripciones sin descuento
     */
    public function scopeSinDescuento($query)
    {
        return $query->whereDoesntHave('descuento');
    }
}
