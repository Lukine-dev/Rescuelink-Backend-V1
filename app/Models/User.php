<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'role',
        'first_name',
        'middle_name',
        'last_name',
        'ext_name',
        'username',
        'email',
        'password',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the user's full name
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->first_name . ' ' . $this->last_name;
        if ($this->ext_name) {
            $name .= ' ' . $this->ext_name;
        }
        return $name;
    }

    /**
     * Get accidents reported by this user
     */
    public function reportedAccidents(): HasMany
    {
        return $this->hasMany(Accident::class, 'reported_by');
    }

    /**
     * Get responders managed by this admin user
     */
    public function responders(): HasMany
    {
        return $this->hasMany(Responder::class, 'admin_id');
    }

    /**
     * Get vehicles managed by this admin user
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'admin_id');
    }

    /**
     * Get the responder profile for this user
     */
    public function responderProfile(): HasOne
    {
        return $this->hasOne(Responder::class);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin or superadmin
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['superadmin', 'admin']);
    }
}