<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoDocumento extends Model
{
    protected $table = 'Tipo_documento';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_entidad',
        'descripcion'
    ];

    protected $casts = [
        'id' => 'integer'
    ];

    /**
     * Relación con documentos
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'Tipo_documento_id');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_entidad', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para tipos con documentos activos
     */
    public function scopeConDocumentosActivos($query)
    {
        return $query->whereHas('documentos', function($q) {
            $q->where('estado', 1);
        });
    }
}
