<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Traits\Authenticatable;

class Estudiante extends Persona implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;
    protected $table = 'estudiante';
    protected $primaryKey = 'id';
    public $timestamps = true;
    public $incrementing = true; // El id se genera automáticamente desde la secuencia de persona

    protected $fillable = [
        // Campos heredados de Persona
        // NO incluir 'id' porque se genera automáticamente desde la secuencia de persona con INHERITS
        'ci',
        'nombre',
        'apellido',
        'celular',
        'sexo',
        'fecha_nacimiento',
        'direccion',
        'fotografia',
        // NO incluir usuario_id - la relación es: usuario tiene persona_id, no al revés
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
     * Override para manejar correctamente la inserción con PostgreSQL INHERITS
     * El id se genera automáticamente desde la secuencia de persona
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Con PostgreSQL INHERITS, no debemos establecer id explícitamente
            // Se generará automáticamente desde la secuencia de persona
            // Eliminar id si está presente (incluso si es null)
            if (array_key_exists('id', $model->attributes)) {
                unset($model->attributes['id']);
            }
            // También eliminar de original si existe
            if (array_key_exists('id', $model->original)) {
                unset($model->original['id']);
            }
            // Asegurar que Laravel no intente establecer id automáticamente
            $model->exists = false;
        });
    }

    /**
     * Override getKeyName para evitar problemas con la primary key
     * Con INHERITS, el id se genera desde la secuencia de persona
     */
    public function getKeyName()
    {
        return 'id';
    }

    /**
     * Override para evitar que Laravel intente establecer id en insert
     * Con PostgreSQL INHERITS, el id se genera desde la secuencia de persona
     */
    protected function performInsert(\Illuminate\Database\Eloquent\Builder $query)
    {
        // Remover id de los atributos antes de insertar
        if (isset($this->attributes['id'])) {
            unset($this->attributes['id']);
        }

        // Guardar registro_estudiante para poder recuperar el id después
        $registroEstudiante = $this->registro_estudiante;

        $result = parent::performInsert($query);
        
        // Después de insertar, obtener el id generado desde la base de datos
        // Con INHERITS, PostgreSQL inserta en persona y genera el id automáticamente
        // Laravel puede no recuperarlo automáticamente, así que lo hacemos manualmente
        if (!$this->id && $registroEstudiante) {
            $inserted = DB::table($this->table)
                ->where('registro_estudiante', $registroEstudiante)
                ->first();
            
            if ($inserted && isset($inserted->id)) {
                $this->setAttribute('id', $inserted->id);
                $this->syncOriginal();
                $this->exists = true;
            }
        }
        
        return $result;
    }

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
