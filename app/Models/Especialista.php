<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Modelo Especialista — Roadmap §1 (Glosario de Dominio).
 *
 * Usuario autenticado del sistema (psicólogo, médico, fisioterapeuta, etc.).
 * Opera sobre la tabla 'users' existente en la DB.
 * El perfil profesional extendido está en la tabla 'profesionales' (relación 1:1).
 *
 * Nunca llamar "usuario" ni "doctor" en el código del dominio.
 */
class Especialista extends Authenticatable
{
    use Notifiable, SoftDeletes;

    /**
     * Apunta a la tabla 'users' existente en la DB.
     * El roadmap define 'especialistas', pero la DB original usa 'users'.
     * Esta decisión está documentada en DOCUMENTACION.md.
     */
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'is_active',
        'password',
        'kdf_salt',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'kdf_salt',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'locked_until' => 'datetime',
        ];
    }

    /**
     * Perfil profesional del especialista.
     * Contiene UUID, área, especialidad, número de registro.
     */
    public function profesional(): HasOne
    {
        return $this->hasOne(Profesional::class, 'user_id');
    }

    /**
     * Áreas autorizadas del especialista a través de la tabla profesionales.
     * En Sprint 1, un profesional tiene una sola área (relación directa).
     * En HU-03 se extenderá con especialista_rol_area para multi-área.
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'profesionales', 'user_id', 'area_id');
    }

    /**
     * Verifica si el especialista tiene un rol específico por slug.
     * Preparado para HU-03 (RBAC). Actualmente verifica a través de role_user.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles()->where('slug', $slug)->exists();
    }

    /**
     * Roles asignados al especialista.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
}
