<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Traits\Authenticatable;

class Docente extends Persona implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;
    protected $table = 'docente';
    protected $primaryKey = 'registro_docente';

    protected $fillable = [
        'registro_docente',
        'cargo',
        'area_de_especializacion',
        'modalidad_de_contratacion'
    ];

    protected $casts = [
        'registro_docente' => 'integer'
    ];

    /**
     * Relación con grupos
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'registro_docente', 'registro_docente');
    }

    /**
     * Relación con programas (a través de grupos)
     */
    public function programas(): BelongsToMany
    {
        return $this->belongsToMany(Programa::class, 'Grupo', 'registro_docente', 'id')
                    ->distinct();
    }

    /**
     * Scope para buscar por registro
     */
    public function scopePorRegistro($query, string $registro)
    {
        return $query->where('registro_docente', $registro);
    }

    /**
     * Scope para buscar por especialización
     */
    public function scopePorEspecializacion($query, string $especializacion)
    {
        return $query->where('area_de_especializacion', 'ILIKE', "%{$especializacion}%");
    }

    /**
     * Scope para docentes con grupos activos
     */
    public function scopeConGruposActivos($query)
    {
        return $query->whereHas('grupos', function($q) {
            $q->where('fecha_fin', '>=', now());
        });
    }

    /**
     * Accessor para obtener carga horaria actual
     */
    public function getCargaHorariaActualAttribute()
    {
        return $this->grupos()
                    ->where('fecha_fin', '>=', now())
                    ->with('horario')
                    ->get()
                    ->sum(function($grupo) {
                        return $grupo->horario->hora_fin->diffInHours($grupo->horario->hora_ini);
                    });
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
            'rol' => 'DOCENTE',
            'ci' => $this->ci,
            'registro' => $this->registro_docente
        ];
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword()
    {
        return null; // Los docentes no tienen password en esta estructura
    }
}
