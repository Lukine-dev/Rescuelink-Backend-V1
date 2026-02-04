<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Responder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'admin_id',
        'department',
        'specialization',
        'badge_number',
        'status',
        'availability',
        'contact_number',
        'emergency_contact',
        'location_coordinates',
        'current_latitude',
        'current_longitude',
        'last_active_at',
        'joined_date',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
        'joined_date' => 'datetime',
        'current_latitude' => 'float',
        'current_longitude' => 'float',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Update location with coordinates
     */
    public function updateLocation($latitude, $longitude)
    {
        $this->current_latitude = $latitude;
        $this->current_longitude = $longitude;
        $this->location_coordinates = DB::raw("ST_MakePoint($longitude, $latitude)");
        $this->last_active_at = now();
        $this->save();
    }

    /**
     * Parse location coordinates
     */
    public function getLocationCoordinatesAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        if (is_string($value)) {
            $value = trim($value, '()');
            $parts = explode(',', $value);
            
            if (count($parts) === 2) {
                return [
                    'longitude' => (float) trim($parts[0]),
                    'latitude' => (float) trim($parts[1]),
                ];
            }
        }
        
        return $value;
    }

    /**
     * Scope for responders near location
     */
    public function scopeNearLocation($query, $latitude, $longitude, $distanceKm = 10)
    {
        return $query->whereRaw(
            "ST_DWithin(
                location_coordinates::geography,
                ST_MakePoint(?, ?)::geography,
                ? * 1000
            )",
            [$longitude, $latitude, $distanceKm]
        )->where('availability', 'available');
    }

    /**
     * Get distance from given point in kilometers
     */
    public function getDistanceFrom($latitude, $longitude)
    {
        if (!$this->location_coordinates) {
            return null;
        }
        
        $result = DB::selectOne(
            "SELECT ST_Distance(
                location_coordinates::geography,
                ST_MakePoint(?, ?)::geography
            ) / 1000 as distance_km",
            [$longitude, $latitude]
        );
        
        return round($result->distance_km, 2);
    }
    
    /**
     * Get the user account associated with this responder
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who manages this responder
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get accidents assigned to this responder
     */
    public function accidents(): BelongsToMany
    {
        return $this->belongsToMany(Accident::class, 'accident_responder')
                    ->withPivot('status', 'assigned_at', 'arrived_at', 'completed_at', 'notes')
                    ->withTimestamps();
    }

    /**
     * Get vehicles assigned to this responder
     */
    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'responder_vehicle')
                    ->withPivot('assigned_date', 'unassigned_date', 'assignment_type')
                    ->withTimestamps();
    }

    /**
     * Get current vehicle assignment
     */
    public function currentVehicle()
    {
        return $this->vehicles()
                    ->wherePivotNull('unassigned_date')
                    ->where('assignment_type', 'primary')
                    ->first();
    }

    /**
     * Get active accidents assigned to this responder
     */
    public function activeAccidents()
    {
        return $this->accidents()
                    ->whereIn('accidents.status', ['reported', 'dispatched', 'in_progress'])
                    ->whereIn('accident_responder.status', ['assigned', 'en_route', 'on_scene', 'treating', 'transporting'])
                    ->get();
    }

    /**
     * Check if responder is available
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->status === 'active' && $this->availability === 'available';
    }

    /**
     * Get responder's full name
     */
    public function getFullNameAttribute(): string
    {
        return $this->user->full_name;
    }

    /**
     * Get responder's current location as array
     */
    public function getCurrentLocationAttribute(): ?array
    {
        if ($this->current_latitude && $this->current_longitude) {
            return [
                'latitude' => $this->current_latitude,
                'longitude' => $this->current_longitude,
            ];
        }
        return null;
    }

    /**
     * Scope for available responders
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')
                    ->where('availability', 'available');
    }

    /**
     * Scope for active responders
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}