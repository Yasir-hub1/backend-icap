<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inscripcion extends Model
{
    protected $table = 'Inscripcion';
    protected $primaryKey = 'id';

    protected $fillable = [
        'fecha',
        'Programa_id',
        'Estudiante_id',
        'Descuento_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha' => 'datetime',
        'Programa_id' => 'integer',
        'Estudiante_id' => 'integer',
        'Descuento_id' => 'integer'
    ];

    /**
     * Relación con programa
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'Programa_id');
    }

    /**
     * Relación con estudiante
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'Estudiante_id');
    }

    /**
     * Relación con descuento
     */
    public function descuento(): BelongsTo
    {
        return $this->belongsTo(Descuento::class, 'Descuento_id');
    }

    /**
     * Relación con plan de pagos
     */
    public function planPagos(): HasOne
    {
        return $this->hasOne(PlanPagos::class, 'Inscripcion_id');
    }

    /**
     * Relación con pagos (a través de plan de pagos)
     */
    public function pagos(): HasMany
    {
        return $this->hasManyThrough(Pago::class, PlanPagos::class, 'Inscripcion_id', 'cuotas_id');
    }

    /**
     * Scope para inscripciones recientes
     */
    public function scopeRecientes($query, int $dias = 30)
    {
        return $query->where('fecha', '>=', now()->subDays($dias));
    }

    /**
     * Scope para inscripciones por programa
     */
    public function scopePorPrograma($query, int $programaId)
    {
        return $query->where('Programa_id', $programaId);
    }

    /**
     * Scope para inscripciones por estudiante
     */
    public function scopePorEstudiante($query, int $estudianteId)
    {
        return $query->where('Estudiante_id', $estudianteId);
    }

    /**
     * Accessor para calcular el costo final con descuento
     */
    public function getCostoFinalAttribute(): float
    {
        $costoBase = $this->programa->costo;

        if ($this->descuento) {
            $descuento = $costoBase * ($this->descuento->descuento / 100);
            return $costoBase - $descuento;
        }

        return $costoBase;
    }

    /**
     * Accessor para verificar si tiene plan de pagos
     */
    public function getTienePlanPagosAttribute(): bool
    {
        return $this->planPagos()->exists();
    }
}
