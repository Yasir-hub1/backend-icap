<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    protected $table = 'documentos';
    protected $primaryKey = 'documento_id';

    protected $fillable = [
        'nombre_documento',
        'version',
        'path_documento',
        'estado',
        'observaciones',
        'tipo_documento',
        'persona_id',
        'convenio_id'
    ];

    protected $casts = [
        'documento_id' => 'integer',
        'tipo_documento' => 'integer',
        'persona_id' => 'integer',
        'convenio_id' => 'integer'
    ];

    /**
     * Relación con tipo de documento
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'tipo_documento', 'Tipo_documento_id');
    }

    /**
     * Relación con convenio
     */
    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class, 'convenio_id', 'convenio_id');
    }

    /**
     * Relación con persona
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id', 'persona_id');
    }

    /**
     * Scope para documentos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 1);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_documento', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Scope para documentos por tipo
     */
    public function scopePorTipo($query, int $tipoId)
    {
        return $query->where('tipo_documento', $tipoId);
    }

    /**
     * Scope para documentos por persona
     */
    public function scopePorPersona($query, int $personaId)
    {
        return $query->where('persona_id', $personaId);
    }

    /**
     * Scope para documentos por convenio
     */
    public function scopePorConvenio($query, int $convenioId)
    {
        return $query->where('convenio_id', $convenioId);
    }

    /**
     * Accessor para verificar si está activo
     */
    public function getEstaActivoAttribute(): bool
    {
        return $this->estado == 1;
    }

    /**
     * Accessor para obtener la extensión del archivo
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->path_documento, PATHINFO_EXTENSION);
    }

    /**
     * Accessor para obtener el tamaño del archivo
     */
    public function getTamañoAttribute(): string
    {
        if (file_exists($this->path_documento)) {
            $bytes = filesize($this->path_documento);
            $units = ['B', 'KB', 'MB', 'GB'];

            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }

            return round($bytes, 2) . ' ' . $units[$i];
        }

        return 'N/A';
    }
}
