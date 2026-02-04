<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmergencyContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'relationship',
        'phone_number',
        'alternate_phone',
        'email',
        'notes',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the user this contact belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full contact information as array
     */
    public function getContactInfoAttribute(): array
    {
        return [
            'name' => $this->name,
            'relationship' => $this->relationship,
            'primary_phone' => $this->phone_number,
            'alternate_phone' => $this->alternate_phone,
            'email' => $this->email,
            'notes' => $this->notes,
        ];
    }

    /**
     * Scope for primary contacts
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope for secondary contacts
     */
    public function scopeSecondary($query)
    {
        return $query->where('is_primary', false);
    }
}