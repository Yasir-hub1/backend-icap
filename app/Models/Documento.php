<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    protected $table = 'Documentos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre_documento',
        'version',
        'path_documento',
        'estado',
        'observaciones',
        'Tipo_documento_id',
        'convenio_id',
        'estudiante_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'estado' => 'integer',
        'Tipo_documento_id' => 'integer',
        'convenio_id' => 'integer',
        'estudiante_id' => 'integer'
    ];

    /**
     * Relación con tipo de documento
     */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class, 'Tipo_documento_id');
    }

    /**
     * Relación con convenio
     */
    public function convenio(): BelongsTo
    {
        return $this->belongsTo(Convenio::class, 'convenio_id');
    }

    /**
     * Relación con estudiante
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class, 'estudiante_id');
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
        return $query->where('Tipo_documento_id', $tipoId);
    }

    /**
     * Scope para documentos por estudiante
     */
    public function scopePorEstudiante($query, int $estudianteId)
    {
        return $query->where('estudiante_id', $estudianteId);
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
