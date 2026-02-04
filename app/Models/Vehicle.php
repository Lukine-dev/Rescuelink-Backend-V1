<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'admin_id',
        'license_plate',
        'vehicle_type',
        'model',
        'year',
        'status',
        'current_location',
        'vehicle_coordinates',
        'current_latitude',
        'current_longitude',
        'fuel_level',
        'odometer_reading',
        'last_maintenance',
        'next_maintenance',
        'equipment_list',
    ];

    protected $casts = [
        'last_maintenance' => 'date',
        'next_maintenance' => 'date',
        'year' => 'integer',
        'fuel_level' => 'integer',
        'odometer_reading' => 'integer',
        'current_latitude' => 'float',
        'current_longitude' => 'float',
        'equipment_list' => 'array',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Update vehicle location
     */
    public function updateLocation($latitude, $longitude, $locationName = null)
    {
        $this->current_latitude = $latitude;
        $this->current_longitude = $longitude;
        $this->vehicle_coordinates = DB::raw("ST_MakePoint($longitude, $latitude)");
        
        if ($locationName) {
            $this->current_location = $locationName;
        }
        
        $this->save();
    }

    /**
     * Parse vehicle coordinates
     */
    public function getVehicleCoordinatesAttribute($value)
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
     * Scope for vehicles near location
     */
    public function scopeNearLocation($query, $latitude, $longitude, $distanceKm = 10)
    {
        return $query->whereRaw(
            "ST_DWithin(
                vehicle_coordinates::geography,
                ST_MakePoint(?, ?)::geography,
                ? * 1000
            )",
            [$longitude, $latitude, $distanceKm]
        )->where('status', 'available');
    }

    /**
     * Get distance from given point in kilometers
     */
    public function getDistanceFrom($latitude, $longitude)
    {
        if (!$this->vehicle_coordinates) {
            return null;
        }
        
        $result = DB::selectOne(
            "SELECT ST_Distance(
                vehicle_coordinates::geography,
                ST_MakePoint(?, ?)::geography
            ) / 1000 as distance_km",
            [$longitude, $latitude]
        );
        
        return round($result->distance_km, 2);
    }

    /**
     * Get the admin who manages this vehicle
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Get accidents this vehicle is dispatched to
     */
    public function accidents(): BelongsToMany
    {
        return $this->belongsToMany(Accident::class, 'accident_vehicle')
                    ->withPivot('status', 'dispatched_at', 'arrived_at', 'returned_at', 'distance_traveled', 'fuel_used')
                    ->withTimestamps();
    }

    /**
     * Get responders assigned to this vehicle
     */
    public function responders(): BelongsToMany
    {
        return $this->belongsToMany(Responder::class, 'responder_vehicle')
                    ->withPivot('assigned_date', 'unassigned_date', 'assignment_type')
                    ->withTimestamps();
    }

    /**
     * Get currently assigned responders
     */
    public function currentResponders()
    {
        return $this->responders()
                    ->wherePivotNull('unassigned_date')
                    ->whereIn('assignment_type', ['primary', 'secondary'])
                    ->get();
    }

    /**
     * Get active dispatches for this vehicle
     */
    public function activeDispatches()
    {
        return $this->accidents()
                    ->whereIn('accidents.status', ['reported', 'dispatched', 'in_progress'])
                    ->whereIn('accident_vehicle.status', ['dispatched', 'en_route', 'on_scene', 'transporting'])
                    ->get();
    }

    /**
     * Check if vehicle needs maintenance
     */
    public function getNeedsMaintenanceAttribute(): bool
    {
        if (!$this->next_maintenance) {
            return false;
        }
        return $this->next_maintenance <= now()->addDays(7);
    }

    /**
     * Check if vehicle is low on fuel
     */
    public function getIsLowFuelAttribute(): bool
    {
        return $this->fuel_level !== null && $this->fuel_level < 20;
    }

    /**
     * Check if vehicle is available
     */
    public function getIsAvailableAttribute(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Get vehicle's current location as array
     */
    public function getCurrentLocationAttribute(): ?array
    {
        if ($this->current_latitude && $this->current_longitude) {
            return [
                'latitude' => $this->current_latitude,
                'longitude' => $this->current_longitude,
                'address' => $this->current_location,
            ];
        }
        return null;
    }

    /**
     * Scope for available vehicles
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope for vehicles by type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('vehicle_type', $type);
    }

    /**
     * Scope for vehicles needing maintenance
     */
    public function scopeNeedsMaintenance($query)
    {
        return $query->where('next_maintenance', '<=', now()->addDays(7))
                    ->orWhere('status', 'maintenance');
    }
}