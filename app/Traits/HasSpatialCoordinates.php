<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait HasSpatialCoordinates
{
    /**
     * Set geometry coordinates from latitude and longitude
     */
    public function setGeometryCoordinates($latitude, $longitude, $field = 'coordinates')
    {
        $this->{$field} = DB::raw("ST_SetSRID(ST_MakePoint($longitude, $latitude), 4326)");
        
        // Update decimal columns if they exist
        if (isset($this->latitude)) {
            $this->latitude = $latitude;
        }
        if (isset($this->longitude)) {
            $this->longitude = $longitude;
        }
    }

    /**
     * Get coordinates as array [longitude, latitude]
     */
    public function getGeometryCoordinates($field = 'coordinates')
    {
        if (!$this->{$field}) {
            return null;
        }
        
        $result = DB::selectOne(
            "SELECT ST_X(?) as longitude, ST_Y(?) as latitude",
            [$this->{$field}, $this->{$field}]
        );
        
        return $result ? [
            'longitude' => (float) $result->longitude,
            'latitude' => (float) $result->latitude,
        ] : null;
    }

    /**
     * Scope for records within distance (kilometers)
     */
    public function scopeWithinRadius($query, $latitude, $longitude, $radiusKm, $geometryField = 'coordinates')
    {
        return $query->whereRaw(
            "ST_DWithin(
                $geometryField::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography,
                ? * 1000
            )",
            [$longitude, $latitude, $radiusKm]
        );
    }

    /**
     * Calculate distance between two points in kilometers
     */
    public function distanceTo($latitude, $longitude, $geometryField = 'coordinates')
    {
        if (!$this->{$geometryField}) {
            return null;
        }
        
        $result = DB::selectOne(
            "SELECT ST_Distance(
                ?::geography,
                ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography
            ) / 1000 as distance_km",
            [$this->{$geometryField}, $longitude, $latitude]
        );
        
        return $result ? round($result->distance_km, 2) : null;
    }
}