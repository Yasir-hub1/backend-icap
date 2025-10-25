<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Persona extends Model
{
    protected $table = 'persona';
    protected $primaryKey = 'persona_id';
    public $timestamps = false;

    protected $fillable = [
        'ci',
        'nombre',
        'apellido',
        'celular',
        'fecha_nacimiento',
        'direccion',
        'fotografia'
    ];

    protected $casts = [
        'persona_id' => 'integer',
        'fecha_nacimiento' => 'date'
    ];

    /**
     * Relación con usuario (1:0..1)
     * Una persona puede tener o no un usuario
     */
    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class, 'persona_id', 'persona_id');
    }

    /**
     * Relación con documentos (1:0..*)
     * Una persona puede tener muchos documentos
     */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'persona_id', 'persona_id');
    }

    /**
     * Scope para buscar por CI
     */
    public function scopePorCi($query, string $ci)
    {
        return $query->where('ci', $ci);
    }

    /**
     * Scope para buscar por nombre completo
     */
    public function scopePorNombre($query, string $nombre)
    {
        return $query->where(function($q) use ($nombre) {
            $q->where('nombre', 'ILIKE', "%{$nombre}%")
              ->orWhere('apellido', 'ILIKE', "%{$nombre}%");
        });
    }

    /**
     * Scope para buscar por email
     */
    public function scopePorEmail($query, string $email)
    {
        return $query->where('email', 'ILIKE', "%{$email}%");
    }

    /**
     * Accessor para nombre completo
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    /**
     * Accessor para edad
     */
    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    /**
     * Accessor para iniciales
     */
    public function getInicialesAttribute(): string
    {
        return strtoupper(substr($this->nombre, 0, 1) . substr($this->apellido, 0, 1));
    }
}
