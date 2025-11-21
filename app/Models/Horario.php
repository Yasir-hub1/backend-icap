<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Horario extends Model
{
    protected $table = 'horario';
    protected $primaryKey = 'horario_id';
    public $timestamps = true;

    protected $fillable = [
        'dias',
        'hora_ini',
        'hora_fin'
    ];

    protected $casts = [
        'horario_id' => 'integer',
        'hora_ini' => 'datetime',
        'hora_fin' => 'datetime'
    ];

    /**
     * Atributos adicionales que se deben agregar a la serialización
     */
    protected $appends = ['hora_ini_formatted', 'hora_fin_formatted', 'duracion_horas'];

    /**
     * Relación con grupos (many-to-many)
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupo_horario', 'horario_id', 'grupo_id', 'horario_id', 'grupo_id')
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
     * Accessor para formatear hora_ini como string H:i
     */
    public function getHoraIniFormattedAttribute(): ?string
    {
        if (!$this->hora_ini) {
            return null;
        }

        // Si es un objeto Carbon/DateTime, formatear
        if ($this->hora_ini instanceof \DateTime || $this->hora_ini instanceof \Carbon\Carbon) {
            return $this->hora_ini->format('H:i');
        }

        // Si es un string, extraer solo la hora
        if (is_string($this->hora_ini)) {
            // Si viene como timestamp ISO, extraer la hora
            if (strpos($this->hora_ini, 'T') !== false) {
                $parts = explode('T', $this->hora_ini);
                if (isset($parts[1])) {
                    return substr($parts[1], 0, 5); // HH:mm
                }
            }
            // Si ya viene como H:i, devolverlo
            if (preg_match('/^\d{2}:\d{2}/', $this->hora_ini)) {
                return substr($this->hora_ini, 0, 5);
            }
        }

        return null;
    }

    /**
     * Accessor para formatear hora_fin como string H:i
     */
    public function getHoraFinFormattedAttribute(): ?string
    {
        if (!$this->hora_fin) {
            return null;
        }

        // Si es un objeto Carbon/DateTime, formatear
        if ($this->hora_fin instanceof \DateTime || $this->hora_fin instanceof \Carbon\Carbon) {
            return $this->hora_fin->format('H:i');
        }

        // Si es un string, extraer solo la hora
        if (is_string($this->hora_fin)) {
            // Si viene como timestamp ISO, extraer la hora
            if (strpos($this->hora_fin, 'T') !== false) {
                $parts = explode('T', $this->hora_fin);
                if (isset($parts[1])) {
                    return substr($parts[1], 0, 5); // HH:mm
                }
            }
            // Si ya viene como H:i, devolverlo
            if (preg_match('/^\d{2}:\d{2}/', $this->hora_fin)) {
                return substr($this->hora_fin, 0, 5);
            }
        }

        return null;
    }

    /**
     * Accessor para obtener la duración en horas
     */
    public function getDuracionHorasAttribute(): ?float
    {
        if (!$this->hora_ini || !$this->hora_fin) {
            return null;
        }

        try {
            // Obtener las horas formateadas
            $horaIni = $this->hora_ini_formatted;
            $horaFin = $this->hora_fin_formatted;

            if (!$horaIni || !$horaFin) {
                return null;
            }

            // Convertir a objetos DateTime para calcular diferencia
            $inicio = \Carbon\Carbon::createFromFormat('H:i', $horaIni);
            $fin = \Carbon\Carbon::createFromFormat('H:i', $horaFin);

            // Si la hora fin es menor que inicio, asumir que es del día siguiente
            if ($fin->lt($inicio)) {
                $fin->addDay();
            }

            return round($inicio->diffInMinutes($fin) / 60, 1);
        } catch (\Exception $e) {
            return null;
        }
    }
}
