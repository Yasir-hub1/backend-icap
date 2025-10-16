<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Institucion extends Model
{
    protected $table = 'Institucion';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'email',
        'sitio_web',
        'fecha_fundacion',
        'estado',
        'ciudad_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_fundacion' => 'date',
        'estado' => 'integer',
        'ciudad_id' => 'integer'
    ];

    /**
     * Relación con ciudad
     */
    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id');
    }

    /**
     * Relación con programas
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class, 'Institucion_id');
    }

    /**
     * Relación con convenios (many-to-many)
     */
    public function convenios(): BelongsToMany
    {
        return $this->belongsToMany(Convenio::class, 'Institucion_convenio', 'Institucion_id', 'Convenio_id')
                    ->withPivot(['porcentaje_participacion', 'monto_asignado', 'estado'])
                    ->withTimestamps();
    }

    /**
     * Scope para instituciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 1);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para instituciones con convenios activos
     */
    public function scopeConConveniosActivos($query)
    {
        return $query->whereHas('convenios', function($q) {
            $q->where('fecha_fin', '>=', now())
              ->where('estado', 1);
        });
    }

    /**
     * Accessor para obtener el nombre completo con ubicación
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} - {$this->ciudad->nombre_ciudad}";
    }
}
