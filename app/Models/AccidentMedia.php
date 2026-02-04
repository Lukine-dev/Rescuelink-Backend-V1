<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccidentMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'accident_id',
        'uploaded_by',
        'file_path',
        'file_name',
        'file_type',
        'mime_type',
        'file_size',
        'media_type',
        'description',
        'is_public',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_public' => 'boolean',
    ];

    /**
     * Get the accident this media belongs to
     */
    public function accident(): BelongsTo
    {
        return $this->belongsTo(Accident::class);
    }

    /**
     * Get the user who uploaded this media
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the full URL to the media file
     */
    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Get the file extension
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    /**
     * Check if media is an image
     */
    public function getIsImageAttribute(): bool
    {
        return $this->media_type === 'photo' || in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Check if media is a video
     */
    public function getIsVideoAttribute(): bool
    {
        return $this->media_type === 'video' || str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Scope for public media
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for media by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('media_type', $type);
    }
}