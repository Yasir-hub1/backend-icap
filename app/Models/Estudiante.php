<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Traits\Authenticatable;

class Estudiante extends Usuario implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;
    protected $table = 'Estudiante';

    protected $fillable = [
        'ci',
        'nombre',
        'apellido',
        'celular',
        'fecha_nacimiento',
        'direccion',
        'fotografia',
        'registro_estudiante',
        'provincia',
        'Estado_id',
        'password'
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'id' => 'integer',
        'fecha_nacimiento' => 'date',
        'Estado_id' => 'integer'
    ];

    /**
     * Relación con estado del estudiante
     */
    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoEstudiante::class, 'Estado_id');
    }

    /**
     * Relación con inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'Estudiante_id');
    }

    /**
     * Relación con documentos
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'estudiante_id');
    }

    /**
     * Relación con grupos (many-to-many)
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupo_estudiante', 'Estudiante_id', 'Grupo_id')
                    ->withPivot(['nota', 'estado'])
                    ->withTimestamps();
    }

    /**
     * Scope para estudiantes activos
     */
    public function scopeActivos($query)
    {
        return $query->whereHas('estado', function($q) {
            $q->where('nombre_estado', 'Activo');
        });
    }

    /**
     * Scope para buscar por registro
     */
    public function scopePorRegistro($query, string $registro)
    {
        return $query->where('registro_estudiante', $registro);
    }

    /**
     * Scope para estudiantes con inscripciones
     */
    public function scopeConInscripciones($query)
    {
        return $query->whereHas('inscripciones');
    }

    /**
     * Accessor para obtener el programa actual
     */
    public function getProgramaActualAttribute()
    {
        return $this->inscripciones()
                    ->with(['programa', 'programa.ramaAcademica', 'programa.tipoPrograma'])
                    ->latest()
                    ->first();
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
            'rol' => 'ESTUDIANTE',
            'ci' => $this->ci,
            'registro' => $this->registro_estudiante,
            'estado_id' => $this->Estado_id
        ];
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return $this->password;
    }
}
