<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'rol_id';
    public $timestamps = true;

    protected $fillable = [
        'nombre_rol',
        'descripcion',
        'activo'
    ];

    protected $casts = [
        'rol_id' => 'integer',
        'activo' => 'boolean'
    ];

    /**
     * Relación con permisos (many-to-many)
     */
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id', 'rol_id', 'permiso_id')
                    ->withPivot(['activo'])
                    ->wherePivot('activo', true);
    }

    /**
     * Relación con usuarios
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id', 'rol_id');
    }

    /**
     * Scope para roles activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where('nombre_rol', 'ILIKE', "%{$nombre}%");
    }

    /**
     * Verificar si el rol tiene un permiso específico
     */
    public function tienePermiso(string $permiso): bool
    {
        return $this->permisos()->where('nombre_permiso', $permiso)->exists();
    }

    /**
     * Verificar si el rol tiene permisos en un módulo específico
     */
    public function tienePermisosEnModulo(string $modulo): bool
    {
        return $this->permisos()->where('modulo', $modulo)->exists();
    }

    /**
     * Obtener permisos por módulo
     */
    public function permisosPorModulo(string $modulo)
    {
        return $this->permisos()->where('modulo', $modulo)->get();
    }

    /**
     * Asignar permiso al rol
     */
    public function asignarPermiso(int $permisoId): bool
    {
        try {
            $this->permisos()->syncWithoutDetaching([
                $permisoId => ['activo' => true]
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Revocar permiso del rol
     */
    public function revocarPermiso(int $permisoId): bool
    {
        try {
            $this->permisos()->updateExistingPivot($permisoId, ['activo' => false]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
