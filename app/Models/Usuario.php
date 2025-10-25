<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Usuario extends Model
{
    protected $table = 'usuario';
    protected $primaryKey = 'usuario_id';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'password',
        'persona_id'
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'usuario_id' => 'integer',
        'persona_id' => 'integer'
    ];

    /**
     * Relación con persona (0..1:1)
     * Un usuario pertenece a una persona
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'persona_id', 'persona_id');
    }

    /**
     * Relación con bitácora
     */
    public function bitacoras(): HasMany
    {
        return $this->hasMany(Bitacora::class, 'usuario_id', 'usuario_id');
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
}
