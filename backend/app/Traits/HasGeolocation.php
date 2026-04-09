<?php

namespace App\Traits;

trait HasGeolocation
{
    /**
     * Calculate the distance from this model to a given latitude and longitude.
     * Uses the Haversine formula.
     * Returns distance in kilometers.
     *
     * @param float $latitude
     * @param float $longitude
     * @return float
     */
    public function getDistanceTo(float $latitude, float $longitude): float
    {
        $earthRadiusKm = 6371;

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadiusKm;
    }

    /**
     * Scope a query to only include models within a given radius.
     */
    public function scopeWithinRadius($query, float $latitude, float $longitude, float $radiusKm = 50)
    {
        // Using Haversine formula in raw SQL for bounding box selection
        $haversine = "(6371 * acos(cos(radians(?))
                        * cos(radians(latitude))
                        * cos(radians(longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(latitude))))";

        return $query->select('*')
            ->selectRaw("{$haversine} AS distance", [$latitude, $longitude, $latitude])
            ->whereRaw("{$haversine} < ?", [$latitude, $longitude, $latitude, $radiusKm])
            ->orderBy('distance');
    }
}
