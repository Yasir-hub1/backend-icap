<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inscripcion extends Model
{
    protected $table = 'inscripcion';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'fecha',
        'registro_estudiante',
        'descuento_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'registro_estudiante' => 'integer',
        'descuento_id' => 'integer',
        'fecha' => 'date'
    ];

    /**
     * Relación con estudiante
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'registro_estudiante', 'registro_estudiante');
    }

    /**
     * Relación con descuento
     */
    public function descuento(): BelongsTo
    {
        return $this->belongsTo(Descuento::class, 'descuento_id', 'id');
    }

    /**
     * Relación con plan de pagos
     */
    public function planPagos(): HasOne
    {
        return $this->hasOne(PlanPagos::class, 'inscripcion_id', 'id');
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
    public function scopePorEstudiante($query, int $estudianteId)
    {
        return $query->where('registro_estudiante', $estudianteId);
    }

    /**
     * Scope para inscripciones con descuento
     */
    public function scopeConDescuento($query)
    {
        return $query->whereNotNull('descuento_id');
    }

    /**
     * Scope para inscripciones sin descuento
     */
    public function scopeSinDescuento($query)
    {
        return $query->whereNull('descuento_id');
    }
}
