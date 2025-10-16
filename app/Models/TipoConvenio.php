<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoConvenio extends Model
{
    protected $table = 'Tipo_convenio';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_tipo',
        'descripcion'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    /**
     * Relación con convenios
     */
    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class, 'Tipo_convenio_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_tipo', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para tipos con convenios activos
     */
    public function scopeConConveniosActivos($query)
    {
        return $query->whereHas('convenios', function($q) {
            $q->where('estado', 1)
              ->where('fecha_fin', '>=', now());
        });
    }
}
