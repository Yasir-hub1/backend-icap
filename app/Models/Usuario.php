<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuario';
    protected $primaryKey = 'usuario_id';
    public $timestamps = true;

    protected $fillable = [
        'email',
        'password',
        'persona_id',
        'rol_id',
        'debe_cambiar_password'
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'persona_id' => 'integer',
        'rol_id' => 'integer',
        'debe_cambiar_password' => 'boolean'
    ];

    /**
     * Relación con persona (0..1:1)
     * Un usuario pertenece a una persona
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id', 'id');
    }

    /**
     * Relación con bitácora
     */
    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'usuario_id', 'usuario_id');
    }

    /**
     * Relación con rol
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'rol_id');
    }

    /**
     * Scope para buscar por persona
     */
    public function scopePorPersona($query, int $personaId)
    {
        return $query->where('persona_id', $personaId);
    }

    /**
     * Scope para buscar por email
     */
    public function scopePorEmail($query, string $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Accessor para nombre completo (delegado a persona)
     */
    public function getNombreCompletoAttribute(): string
    {
        return $this->persona ? $this->persona->nombre_completo : 'Sin persona asociada';
    }

    /**
     * Accessor para edad (delegado a persona)
     */
    public function getEdadAttribute(): ?int
    {
        return $this->persona ? $this->persona->edad : null;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [
            'rol' => $this->rol ? $this->rol->nombre_rol : 'ADMIN',
            'email' => $this->email,
            'persona_id' => $this->persona_id,
            'rol_id' => $this->rol_id
        ];
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function tienePermiso(string $permiso): bool
    {
        if (!$this->rol) {
            return false;
        }

        return $this->rol->tienePermiso($permiso);
    }

    /**
     * Verificar si el usuario tiene permisos en un módulo específico
     */
    public function tienePermisosEnModulo(string $modulo): bool
    {
        if (!$this->rol) {
            return false;
        }

        return $this->rol->tienePermisosEnModulo($modulo);
    }

    /**
     * Obtener permisos del usuario
     */
    public function obtenerPermisos()
    {
        if (!$this->rol) {
            return collect();
        }

        return $this->rol->permisos;
    }
}
