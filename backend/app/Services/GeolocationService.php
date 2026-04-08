<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class GeolocationService
{
    /**
     * Calculate distance between two coordinates using Haversine formula.
     *
     * @param float $lat1 Latitude of first point
     * @param float $lon1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lon2 Longitude of second point
     * @return float Distance in kilometers
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Find points within a specified radius of a given coordinate.
     * Uses database-specific spatial functions when available.
     *
     * @param float $latitude Center latitude
     * @param float $longitude Center longitude
     * @param float $radiusKm Radius in kilometers
     * @param string $table Table name
     * @param string $latColumn Latitude column name
     * @param string $lonColumn Longitude column name
     * @return string SQL WHERE clause for distance filtering
     */
    public static function getDistanceSQL(
        float $latitude, 
        float $longitude, 
        float $radiusKm,
        string $table = 'service_requests',
        string $latColumn = 'latitude',
        string $lonColumn = 'longitude'
    ): array {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return [
                'select' => "*, (ST_Distance_Sphere(POINT({$lonColumn}, {$latColumn}), POINT({$longitude}, {$latitude})) / 1000) AS distance",
                'where' => "ST_Distance_Sphere(POINT({$lonColumn}, {$latColumn}), POINT({$longitude}, {$latitude})) <= " . ($radiusKm * 1000),
                'order' => 'distance ASC'
            ];
        }

        // Fallback: Haversine Formula for SQLite / PostgreSQL / etc.
        $haversine = "(6371 * acos(cos(radians({$latitude})) * cos(radians({$latColumn})) * cos(radians({$lonColumn}) - radians({$longitude})) + sin(radians({$latitude})) * sin(radians({$latColumn}))))";

        return [
            'select' => "*, {$haversine} AS distance",
            'where' => "{$haversine} <= {$radiusKm}",
            'order' => 'distance ASC'
        ];
    }

    /**
     * Validate geographic coordinates.
     *
     * @param float $latitude Latitude to validate
     * @param float $longitude Longitude to validate
     * @return array Validation result with errors if any
     */
    public static function validateCoordinates(float $latitude, float $longitude): array
    {
        $errors = [];

        if ($latitude < -90 || $latitude > 90) {
            $errors[] = 'Latitude must be between -90 and 90 degrees';
        }

        if ($longitude < -180 || $longitude > 180) {
            $errors[] = 'Longitude must be between -180 and 180 degrees';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get bounding box for a given radius (useful for initial filtering).
     *
     * @param float $latitude Center latitude
     * @param float $longitude Center longitude
     * @param float $radiusKm Radius in kilometers
     * @return array [minLat, maxLat, minLon, maxLon]
     */
    public static function getBoundingBox(float $latitude, float $longitude, float $radiusKm): array
    {
        $earthRadius = 6371;
        
        $deltaLat = ($radiusKm / $earthRadius) * (180 / pi());
        $deltaLon = ($radiusKm / $earthRadius) * (180 / pi()) / cos($latitude * pi() / 180);

        return [
            'min_lat' => $latitude - $deltaLat,
            'max_lat' => $latitude + $deltaLat,
            'min_lon' => $longitude - $deltaLon,
            'max_lon' => $longitude + $deltaLon
        ];
    }

    /**
     * Convert distance between different units.
     *
     * @param float $distance Distance value
     * @param string $fromUnit Source unit (km, miles, meters)
     * @param string $toUnit Target unit (km, miles, meters)
     * @return float Converted distance
     */
    public static function convertDistance(float $distance, string $fromUnit, string $toUnit): float
    {
        $conversions = [
            'km_to_miles' => 0.621371,
            'km_to_meters' => 1000,
            'miles_to_km' => 1.60934,
            'miles_to_meters' => 1609.34,
            'meters_to_km' => 0.001,
            'meters_to_miles' => 0.000621371,
        ];

        $conversionKey = strtolower($fromUnit) . '_to_' . strtolower($toUnit);
        
        if (isset($conversions[$conversionKey])) {
            return $distance * $conversions[$conversionKey];
        }

        // If same unit, return as-is
        if ($fromUnit === $toUnit) {
            return $distance;
        }

        throw new \InvalidArgumentException("Unsupported conversion from {$fromUnit} to {$toUnit}");
    }

    /**
     * Estimate travel time based on distance and average speed.
     *
     * @param float $distance Distance in kilometers
     * @param float $speedKmh Average speed in km/h (default: 50 km/h for city driving)
     * @return array Estimated travel time in different formats
     */
    public static function estimateTravelTime(float $distance, float $speedKmh = 50): array
    {
        if ($speedKmh <= 0) {
            throw new \InvalidArgumentException('Speed must be greater than 0');
        }

        $timeHours = $distance / $speedKmh;
        $timeMinutes = $timeHours * 60;
        $timeSeconds = $timeMinutes * 60;

        return [
            'hours' => (int) floor($timeHours),
            'minutes' => (int) floor($timeMinutes % 60),
            'seconds' => (int) floor($timeSeconds % 60),
            'total_minutes' => $timeMinutes,
            'formatted' => self::formatTime($timeHours)
        ];
    }

    /**
     * Format time in human-readable format.
     *
     * @param float $hours Time in hours
     * @return string Formatted time string
     */
    private static function formatTime(float $hours): string
    {
        $h = (int) floor($hours);
        $m = (int) floor(($hours - $h) * 60);

        if ($h > 0) {
            return "{$h}h {$m}m";
        } elseif ($m > 0) {
            return "{$m}m";
        } else {
            return 'Less than 1 minute';
        }
    }

    /**
     * Check if a point is within a polygon (useful for service area validation).
     *
     * @param float $pointLat Point latitude
     * @param float $pointLon Point longitude
     * @param array $polygon Array of [[lat, lon], ...] polygon vertices
     * @return bool True if point is inside polygon
     */
    public static function isPointInPolygon(float $pointLat, float $pointLon, array $polygon): bool
    {
        if (count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            $xi = $polygon[$i][1]; // longitude
            $yi = $polygon[$i][0]; // latitude
            $xj = $polygon[$j][1]; // longitude
            $yj = $polygon[$j][0]; // latitude

            if ((($yi > $pointLat) != ($yj > $pointLat)) &&
                ($pointLon < ($xj - $xi) * ($pointLat - $yi) / ($yj - $yi) + $xi)) {
                $inside = !$inside;
            }
            $j = $i;
        }

        return $inside;
    }
}
