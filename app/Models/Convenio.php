<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Convenio extends Model
{
    protected $table = 'convenio';
    protected $primaryKey = 'convenio_id';
    public $timestamps = false;

    protected $fillable = [
        'numero_convenio',
        'objeto_convenio',
        'fecha_ini',
        'fecha_fin',
        'fecha_firma',
        'moneda',
        'observaciones',
        'tipo_convenio_id'
    ];

    protected $casts = [
        'convenio_id' => 'integer',
        'tipo_convenio_id' => 'integer',
        'fecha_ini' => 'date',
        'fecha_fin' => 'date',
        'fecha_firma' => 'date'
    ];

    /**
     * Relación con tipo de convenio
     */
    public function tipoConvenio(): BelongsTo
    {
        return $this->belongsTo(TipoConvenio::class, 'tipo_convenio_id', 'tipo_convenio_id');
    }

    /**
     * Relación con documentos
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'convenio_id', 'convenio_id');
    }

    /**
     * Relación con instituciones (many-to-many)
     */
    public function instituciones(): BelongsToMany
    {
        return $this->belongsToMany(Institucion::class, 'Institucion_convenio', 'convenio_id', 'institucion_id')
                    ->withPivot(['porcentaje_participacion', 'monto_asignado', 'estado'])
                    ->withTimestamps();
    }

    /**
     * Scope para convenios activos
     */
    public function scopeActivos($query)
    {
        return $query->where('fecha_fin', '>=', now());
    }

    /**
     * Scope para buscar por número
     */
    public function scopePorNumero($query, string $numero)
    {
        return $query->where('numero_convenio', 'ILIKE', "%{$numero}%");
    }

    /**
     * Scope para buscar por tipo
     */
    public function scopePorTipo($query, int $tipoId)
    {
        return $query->where('tipo_convenio_id', $tipoId);
    }
}
