<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Horario extends Model
{
    protected $table = 'Horario';
    protected $primaryKey = 'id';

    protected $fillable = [
        'dias',
        'hora_ini',
        'hora_fin'
    ];

    protected $casts = [
        'id' => 'integer',
        'hora_ini' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i'
    ];

    /**
     * Relación con grupos
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'horario_id');
    }

    /**
     * Relación con grupos adicionales (many-to-many)
     */
    public function gruposAdicionales(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'Grupo_horario', 'Horario_id', 'Grupo_id')
                    ->withPivot('aula')
                    ->withTimestamps();
    }

    /**
     * Scope para buscar por días
     */
    public function scopePorDias($query, string $dias)
    {
        return $query->where('dias', 'ILIKE', "%{$dias}%");
    }

    /**
     * Scope para horarios en rango de horas
     */
    public function scopeEnRangoHoras($query, $horaInicio, $horaFin)
    {
        return $query->where('hora_ini', '>=', $horaInicio)
                    ->where('hora_fin', '<=', $horaFin);
    }

    /**
     * Scope para horarios matutinos
     */
    public function scopeMatutinos($query)
    {
        return $query->where('hora_ini', '>=', '06:00:00')
                    ->where('hora_ini', '<', '12:00:00');
    }

    /**
     * Scope para horarios vespertinos
     */
    public function scopeVespertinos($query)
    {
        return $query->where('hora_ini', '>=', '12:00:00')
                    ->where('hora_ini', '<', '18:00:00');
    }

    /**
     * Scope para horarios nocturnos
     */
    public function scopeNocturnos($query)
    {
        return $query->where('hora_ini', '>=', '18:00:00');
    }

    /**
     * Accessor para obtener la duración en horas
     */
    public function getDuracionHorasAttribute(): float
    {
        return $this->hora_ini->diffInHours($this->hora_fin);
    }

    /**
     * Accessor para obtener el horario formateado
     */
    public function getHorarioFormateadoAttribute(): string
    {
        return "{$this->hora_ini->format('H:i')} - {$this->hora_fin->format('H:i')}";
    }

    /**
     * Accessor para obtener el turno
     */
    public function getTurnoAttribute(): string
    {
        $hora = $this->hora_ini->hour;

        if ($hora < 12) return 'Matutino';
        if ($hora < 18) return 'Vespertino';
        return 'Nocturno';
    }
}
