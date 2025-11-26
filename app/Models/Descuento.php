<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Descuento extends Model
{
    protected $table = 'descuento';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'descuento',
        'programa_id',
        'fecha_inicio',
        'fecha_fin',
        'inscripcion_id' // Mantener por compatibilidad temporal
    ];

    protected $casts = [
        'id' => 'integer',
        'programa_id' => 'integer',
        'inscripcion_id' => 'integer',
        'descuento' => 'decimal:2',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date'
    ];

    /**
     * Relación con programa (muchos a uno)
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'programa_id', 'id');
    }

    /**
     * Relación con inscripciones (muchos a uno) - Para descuentos aplicados
     */
    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'descuento_id', 'id');
    }

    /**
     * Relación con inscripción (uno a uno) - Mantener por compatibilidad
     */
    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id', 'id');
    }

    /**
     * Scope para descuentos activos y vigentes
     */
    public function scopeActivos($query)
    {
        return $query->where('descuento', '>', 0)
                    ->where('fecha_inicio', '<=', now()->toDateString())
                    ->where('fecha_fin', '>=', now()->toDateString());
    }

    /**
     * Scope para descuentos vigentes de un programa
     */
    public function scopeVigentesPorPrograma($query, $programaId)
    {
        return $query->where('programa_id', $programaId)
                    ->where('descuento', '>', 0)
                    ->where('fecha_inicio', '<=', now()->toDateString())
                    ->where('fecha_fin', '>=', now()->toDateString())
                    ->orderBy('descuento', 'desc'); // Ordenar por mayor porcentaje
    }

    /**
     * Verificar si el descuento está vigente
     */
    public function estaVigente(): bool
    {
        if (!$this->fecha_inicio || !$this->fecha_fin) {
            return false;
        }
        $hoy = now()->toDateString();
        return $this->fecha_inicio <= $hoy && $this->fecha_fin >= $hoy;
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por porcentaje mínimo
     */
    public function scopePorPorcentajeMinimo($query, float $porcentaje)
    {
        return $query->where('descuento', '>=', $porcentaje);
    }
}
