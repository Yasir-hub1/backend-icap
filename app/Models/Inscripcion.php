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
        'programa_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'estudiante_id' => 'integer',
        'programa_id' => 'integer',
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
     * Relación con descuento (uno a uno)
     */
    public function descuento(): HasOne
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
