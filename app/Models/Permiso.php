<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'permisos';
    protected $primaryKey = 'permiso_id';
    public $timestamps = true;

    protected $fillable = [
        'nombre_permiso',
        'descripcion',
        'modulo',
        'accion',
        'activo'
    ];

    protected $casts = [
        'permiso_id' => 'integer',
        'activo' => 'boolean'
    ];

    /**
     * Relación con roles (many-to-many)
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'permiso_id', 'rol_id', 'permiso_id', 'rol_id')
                    ->withPivot(['activo'])
                    ->wherePivot('activo', true);
    }

    /**
     * Scope para permisos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para buscar por módulo
     */
    public function scopePorModulo($query, string $modulo)
    {
        return $query->where('modulo', $modulo);
    }

    /**
     * Scope para buscar por acción
     */
    public function scopePorAccion($query, string $accion)
    {
        return $query->where('accion', $accion);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_permiso', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Obtener permisos agrupados por módulo
     */
    public static function agrupadosPorModulo()
    {
        return self::activos()
            ->orderBy('modulo')
            ->orderBy('accion')
            ->get()
            ->groupBy('modulo');
    }

    /**
     * Obtener permisos por módulo y acción
     */
    public static function porModuloYAccion(string $modulo, string $accion)
    {
        return self::activos()
            ->where('modulo', $modulo)
            ->where('accion', $accion)
            ->first();
    }

    /**
     * Crear permiso dinámicamente
     */
    public static function crearPermiso(string $modulo, string $accion, string $descripcion = null): self
    {
        $nombrePermiso = strtolower($modulo) . '_' . strtolower($accion);

        return self::updateOrCreate(
            ['nombre_permiso' => $nombrePermiso],
            [
                'descripcion' => $descripcion ?? "Permiso para {$accion} en {$modulo}",
                'modulo' => $modulo,
                'accion' => $accion,
                'activo' => true
            ]
        );
    }
}
