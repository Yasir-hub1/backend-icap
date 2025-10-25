<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Horario extends Model
{
    protected $table = 'horario';
    protected $primaryKey = 'horario_id';
    public $timestamps = false;

    protected $fillable = [
        'dias',
        'hora_ini',
        'hora_fin'
    ];

    protected $casts = [
        'horario_id' => 'integer',
        'hora_ini' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i'
    ];

    /**
     * Relación con grupos (many-to-many)
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'Grupo_horario', 'horario_id', 'grupo_id', 'horario_id', 'grupo_id')
                    ->withPivot('aula');
    }

    /**
     * Scope para buscar por días
     */
    public function scopePorDias($query, string $dias)
    {
        return $query->where('dias', 'ILIKE', "%{$dias}%");
    }

    /**
     * Scope para buscar por turno
     */
    public function scopePorTurno($query, string $turno)
    {
        switch (strtolower($turno)) {
            case 'mañana':
                return $query->where('hora_ini', '>=', '06:00')->where('hora_ini', '<', '12:00');
            case 'tarde':
                return $query->where('hora_ini', '>=', '12:00')->where('hora_ini', '<', '18:00');
            case 'noche':
                return $query->where('hora_ini', '>=', '18:00');
            default:
                return $query;
        }
    }

    /**
     * Accessor para obtener la duración en horas
     */
    public function getDuracionHorasAttribute(): float
    {
        return $this->hora_ini->diffInHours($this->hora_fin);
    }
}
