<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Modulo extends Model
{
    protected $table = 'Modulo';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'credito',
        'horas_academicas'
    ];

    protected $casts = [
        'id' => 'integer',
        'credito' => 'integer',
        'horas_academicas' => 'integer'
    ];

    /**
     * Relación con programas (many-to-many)
     */
    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'Programa_modulo', 'Modulo_id', 'Programa_id')
                    ->withPivot('edicion')
                    ->withTimestamps();
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para módulos con créditos
     */
    public function scopeConCreditos($query)
    {
        return $query->whereNotNull('credito')->where('credito', '>', 0);
    }

    /**
     * Scope para módulos por horas académicas
     */
    public function scopePorHoras($query, int $horasMinimas)
    {
        return $query->where('horas_academicas', '>=', $horasMinimas);
    }
}
