<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramaModulo extends Model
{
    protected $table = 'programa_modulo';
    
    // La tabla usa clave primaria compuesta
    // Nota: Laravel no soporta completamente claves primarias compuestas
    // Por lo tanto, no definimos $primaryKey y usamos métodos personalizados cuando sea necesario
    public $incrementing = false;
    
    public $timestamps = true;

    protected $fillable = [
        'programa_id',
        'modulo_id',
        'edicion',
        'estado'
    ];

    protected $casts = [
        'programa_id' => 'integer',
        'modulo_id' => 'integer',
        'edicion' => 'integer',
        'estado' => 'integer'
    ];

    /**
     * Relación con programa
     */
    public function programa(): BelongsTo
    {
        return $this->belongsTo(Programa::class, 'programa_id', 'id');
    }

    /**
     * Relación con módulo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'modulo_id', 'modulo_id');
    }

    /**
     * Scope para módulos activos de un programa
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    /**
     * Scope para módulos por programa
     */
    public function scopePorPrograma($query, int $programaId)
    {
        return $query->where('programa_id', $programaId);
    }

    /**
     * Scope para módulos por edición
     */
    public function scopePorEdicion($query, int $edicion)
    {
        return $query->where('edicion', $edicion);
    }
}

