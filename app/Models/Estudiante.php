<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Traits\Authenticatable;

class Estudiante extends Persona implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;
    protected $table = 'estudiante';
    protected $primaryKey = 'registro_estudiante';
    public $timestamps = false;

    protected $fillable = [
        'persona_id',
        'ci',
        'nombre',
        'apellido',
        'celular',
        'fecha_nacimiento',
        'direccion',
        'fotografia',
        'email',
        'telefono_fijo',
        'genero',
        'estado_civil',
        'nacionalidad',
        'lugar_nacimiento',
        'provincia'
    ];

    protected $casts = [
        'registro_estudiante' => 'integer'
    ];

    /**
     * Relación con inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'registro_estudiante', 'registro_estudiante');
    }

    /**
     * Relación con usuario a través de persona
     */
    public function usuario(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Usuario::class, 'persona_id', 'persona_id');
    }

    /**
     * Relación con persona (self-reference para herencia)
     */
    public function persona(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id', 'persona_id');
    }

    /**
     * Relación con documentos (heredada de Persona)
     * Los documentos ahora se manejan a través de la relación con Persona
     */

    /**
     * Relación con grupos (many-to-many)
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupo_estudiante', 'registro_estudiante', 'grupo_id', 'registro_estudiante', 'grupo_id')
                    ->withPivot(['nota', 'estado']);
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
            'registro' => $this->registro_estudiante
        ];
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return null; // Los estudiantes no tienen password en esta estructura
    }
}
