<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Institucion extends Model
{
    protected $table = 'institucion';
    protected $primaryKey = 'id';
    public $timestamps = false;

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
        'ciudad_id' => 'integer',
        'fecha_fundacion' => 'date'
    ];

    /**
     * Relación con ciudad
     */
    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class, 'ciudad_id', 'id');
    }

    /**
     * Relación con programas
     */
    public function programas(): HasMany
    {
        return $this->hasMany(Programa::class, 'institucion_id', 'id');
    }

    /**
     * Relación con convenios (many-to-many)
     */
    public function convenios(): BelongsToMany
    {
        return $this->belongsToMany(Convenio::class, 'Institucion_convenio', 'institucion_id', 'convenio_id')
                    ->withPivot(['porcentaje_participacion', 'monto_asignado', 'estado'])
                    ->withTimestamps();
    }

    /**
     * Scope para instituciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para buscar por ciudad
     */
    public function scopePorCiudad($query, int $ciudadId)
    {
        return $query->where('ciudad_id', $ciudadId);
    }
}
