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
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        // Campos heredados de Persona
        'id',
        'ci',
        'nombre',
        'apellido',
        'celular',
        'sexo',
        'fecha_nacimiento',
        'direccion',
        'fotografia',
        'usuario_id',
        // Campos propios de Estudiante
        'registro_estudiante',
        'provincia',
        'estado_id'
    ];

    protected $casts = [
        'id' => 'integer',
        'estado_id' => 'integer',
        'usuario_id' => 'integer',
        'fecha_nacimiento' => 'date'
    ];

    /**
     * Relación con inscripciones
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(Inscripcion::class, 'estudiante_id', 'id');
    }

    /**
     * Relación con usuario (heredada de Persona)
     * No sobrescribir - usar la relación HasOne de Persona que es la correcta
     * La relación es: Persona -> HasOne -> Usuario (Usuario tiene persona_id)
     */

    /**
     * Relación con estado del estudiante
     */
    public function estadoEstudiante(): BelongsTo
    {
        return $this->belongsTo(EstadoEstudiante::class, 'estado_id', 'id');
    }

    /**
     * Relación con documentos (heredada de Persona)
     * Los documentos ahora se manejan a través de la relación con Persona
     */

    /**
     * Relación con grupos (many-to-many)
     * La tabla grupo_estudiante usa estudiante_id y grupo_id
     * El modelo Grupo usa grupo_id como primary key (no id)
     */
    public function grupos(): BelongsToMany
    {
        return $this->belongsToMany(Grupo::class, 'grupo_estudiante', 'estudiante_id', 'grupo_id', 'id', 'grupo_id')
                    ->withPivot(['nota', 'estado']);
    }

    /**
     * Relación con estado (alias para estadoEstudiante)
     */
    public function estado(): BelongsTo
    {
        return $this->estadoEstudiante();
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
