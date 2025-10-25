<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Modulo extends Model
{
    protected $table = 'modulo';
    protected $primaryKey = 'modulo_id';

    protected $fillable = [
        'nombre',
        'credito',
        'horas_academicas'
    ];

    protected $casts = [
        'modulo_id' => 'integer',
        'credito' => 'integer',
        'horas_academicas' => 'integer'
    ];

    /**
     * Relación con programas (many-to-many)
     */
    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'Programa_modulo', 'modulo_id', 'programa_id', 'modulo_id', 'id')
                    ->withPivot('estado');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para módulos por créditos
     */
    public function scopePorCreditos($query, int $creditos)
    {
        return $query->where('credito', $creditos);
    }

    /**
     * Scope para módulos por horas académicas
     */
    public function scopePorHoras($query, int $horas)
    {
        return $query->where('horas_academicas', $horas);
    }
}
