<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'description',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'performed_at' => 'datetime',
    ];

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the model that was audited
     */
    public function model()
    {
        return $this->morphTo();
    }

    /**
     * Scope for logs by model type
     */
    public function scopeForModel($query, $modelType)
    {
        return $query->where('model_type', $modelType);
    }

    /**
     * Scope for logs by user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for logs by action
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Get the changes made in this log
     */
    public function getChangesAttribute(): array
    {
        $changes = [];
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];
        
        foreach ($newValues as $key => $value) {
            if (!array_key_exists($key, $oldValues) || $oldValues[$key] !== $value) {
                $changes[$key] = [
                    'from' => $oldValues[$key] ?? null,
                    'to' => $value,
                ];
            }
        }
        
        return $changes;
    }
}