<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoConvenio extends Model
{
    protected $table = 'tipo_convenio';
    protected $primaryKey = 'tipo_convenio_id';

    protected $fillable = [
        'nombre_tipo',
        'descripcion'
    ];

    /**
     * Relación con convenios
     */
    public function convenios(): HasMany
    {
        return $this->hasMany(Convenio::class, 'tipo_convenio_id', 'tipo_convenio_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_tipo', 'ILIKE', "%{$nombre}%");
    }
}
