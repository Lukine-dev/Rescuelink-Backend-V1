<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Accident extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reported_by',
        'location',
        'description',
        'severity',
        'status',
        'coordinates',
        'latitude',
        'longitude',
        'injured_count',
        'casualty_count',
        'emergency_contacts',
        'reported_at',
        'resolved_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'emergency_contacts' => 'array',
        'injured_count' => 'integer',
        'casualty_count' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Set the coordinates from latitude and longitude
     */
    public function setCoordinatesFromLatLng($latitude, $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->coordinates = DB::raw("ST_SetSRID(ST_MakePoint($longitude, $latitude), 4326)");
    }

    /**
     * Get coordinates as array
     */
    public function getCoordinatesArrayAttribute()
    {
        if (!$this->coordinates) {
            return null;
        }
        
        $result = DB::selectOne(
            "SELECT ST_X(coordinates::geometry) as longitude, 
                    ST_Y(coordinates::geometry) as latitude 
             FROM accidents 
             WHERE id = ?",
            [$this->id]
        );
        
        return $result ? [
            'longitude' => (float) $result->longitude,
            'latitude' => (float) $result->latitude,
        ] : null;
    }

    /**
     * Scope for finding accidents within distance (in kilometers)
     */
    public function scopeWithinDistance($query, $latitude, $longitude, $distanceKm)
    {
        return $query->whereRaw(
            "ST_DWithin(
                coordinates::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                ? * 1000
            )",
            [$longitude, $latitude, $distanceKm]
        );
    }

    /**
     * Get distance from given point in kilometers
     */
    public function getDistanceFrom($latitude, $longitude)
    {
        if (!$this->coordinates) {
            return null;
        }
        
        $result = DB::selectOne(
            "SELECT ST_Distance(
                coordinates::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
            ) / 1000 as distance_km",
            [$longitude, $latitude]
        );
        
        return round($result->distance_km, 2);
    }

    /**
     * Find nearest accidents to a location
     */
    public static function findNearest($latitude, $longitude, $limit = 10)
    {
        return self::select('*')
            ->selectRaw(
                "ST_Distance(
                    coordinates::geography,
                    ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
                ) / 1000 as distance_km",
                [$longitude, $latitude]
            )
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the user who reported this accident
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * Get responders assigned to this accident
     */
    public function responders(): BelongsToMany
    {
        return $this->belongsToMany(Responder::class, 'accident_responder')
                    ->withPivot('status', 'assigned_at', 'arrived_at', 'completed_at', 'notes')
                    ->withTimestamps();
    }

    /**
     * Get vehicles dispatched to this accident
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'accident_vehicle')
                    ->withPivot('status', 'dispatched_at', 'arrived_at', 'returned_at', 'distance_traveled', 'fuel_used')
                    ->withTimestamps();
    }

    /**
     * Get media attachments for this accident
     */
    public function media(): HasMany
    {
        return $this->hasMany(AccidentMedia::class);
    }

    /**
     * Scope for active accidents
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['reported', 'dispatched', 'in_progress']);
    }

    /**
     * Scope for resolved accidents
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Scope for critical accidents
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Get the assigned responder count
     */
    public function getAssignedRespondersCountAttribute(): int
    {
        return $this->responders()->whereIn('accident_responder.status', ['assigned', 'en_route', 'on_scene'])->count();
    }

    /**
     * Check if accident is resolved
     */
    public function getIsResolvedAttribute(): bool
    {
        return $this->status === 'resolved' && $this->resolved_at !== null;
    }

    // HELPER FOR COORDINATES
      // Helper method to set coordinates
      public function setCoordinates($latitude, $longitude)
      {
          $this->coordinates = DB::raw("point($longitude, $latitude)");
          $this->latitude = $latitude;
          $this->longitude = $longitude;
      }
  
}