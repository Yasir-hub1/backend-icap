<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Convenio extends Model
{
    protected $table = 'Convenio';
    protected $primaryKey = 'id';

    protected $fillable = [
        'numero_convenio',
        'objeto_convenio',
        'fecha_ini',
        'fecha_fin',
        'fecha_firma',
        'moneda',
        'observaciones',
        'estado',
        'Tipo_convenio_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_ini' => 'date',
        'fecha_fin' => 'date',
        'fecha_firma' => 'date',
        'estado' => 'integer',
        'Tipo_convenio_id' => 'integer'
    ];

    /**
     * Relación con tipo de convenio
     */
    public function tipoConvenio(): BelongsTo
    {
        return $this->belongsTo(TipoConvenio::class, 'Tipo_convenio_id');
    }

    /**
     * Relación con instituciones (many-to-many)
     */
    public function instituciones(): BelongsToMany
    {
        return $this->belongsToMany(Institucion::class, 'Institucion_convenio', 'Convenio_id', 'Institucion_id')
                    ->withPivot(['porcentaje_participacion', 'monto_asignado', 'estado'])
                    ->withTimestamps();
    }

    /**
     * Relación con documentos
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'convenio_id');
    }

    /**
     * Scope para convenios activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 1)
                    ->where('fecha_fin', '>=', now());
    }

    /**
     * Scope para convenios vencidos
     */
    public function scopeVencidos($query)
    {
        return $query->where('fecha_fin', '<', now());
    }

    /**
     * Scope para convenios por tipo
     */
    public function scopePorTipo($query, int $tipoId)
    {
        return $query->where('Tipo_convenio_id', $tipoId);
    }

    /**
     * Scope para buscar por número
     */
    public function scopePorNumero($query, string $numero)
    {
        return $query->where('numero_convenio', 'ILIKE', "%{$numero}%");
    }

    /**
     * Accessor para verificar si está activo
     */
    public function getEstaActivoAttribute(): bool
    {
        return $this->estado == 1 && $this->fecha_fin >= now();
    }

    /**
     * Accessor para obtener la duración en días
     */
    public function getDuracionDiasAttribute(): int
    {
        return $this->fecha_ini->diffInDays($this->fecha_fin);
    }

    /**
     * Accessor para obtener el tiempo restante en días
     */
    public function getTiempoRestanteDiasAttribute(): int
    {
        return max(0, now()->diffInDays($this->fecha_fin, false));
    }
}
