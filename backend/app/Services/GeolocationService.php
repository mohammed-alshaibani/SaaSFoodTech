<?php

namespace App\Services;

class GeolocationService
{
    /**
     * Calculate distance between two points in km.
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Validate coordinates.
     */
    public static function validateCoordinates($lat, $lon): array
    {
        $valid = true;
        $errors = [];

        if ($lat < -90 || $lat > 90) {
            $valid = false;
            $errors[] = 'Latitude must be between -90 and 90 degrees';
        }

        if ($lon < -180 || $lon > 180) {
            $valid = false;
            $errors[] = 'Longitude must be between -180 and 180 degrees';
        }

        return [
            'valid' => $valid,
            'errors' => $errors
        ];
    }

    /**
     * Convert distance between units.
     */
    public static function convertDistance($value, $from, $to): float
    {
        // Internal conversion to km first
        $km = $value;
        if ($from === 'miles')
            $km = $value * 1.60934;
        if ($from === 'meters')
            $km = $value / 1000;

        // Convert from km to target
        if ($to === 'miles')
            return $km / 1.60934;
        if ($to === 'meters')
            return $km * 1000;
        if ($to === 'km')
            return $km;

        return $km;
    }

    /**
     * Estimate travel time based on distance and speed.
     */
    public static function estimateTravelTime($distance, $speedKmh): array
    {
        if ($speedKmh <= 0)
            $speedKmh = 50; // default speed

        $totalHours = $distance / $speedKmh;
        $hours = floor($totalHours);
        $minutes = round(($totalHours - $hours) * 60);

        return [
            'hours' => (int) $hours,
            'minutes' => (int) $minutes,
            'total_minutes' => (int) round($totalHours * 60),
            'formatted' => "{$hours}h {$minutes}m"
        ];
    }

    /**
     * Get bounding box for a point and radius.
     */
    public static function getBoundingBox($lat, $lon, $radiusKm): array
    {
        $latDelta = $radiusKm / 111.32;
        $lonDelta = $radiusKm / (111.32 * cos(deg2rad($lat)));

        return [
            'min_lat' => $lat - $latDelta,
            'max_lat' => $lat + $latDelta,
            'min_lon' => $lon - $lonDelta,
            'max_lon' => $lon + $lonDelta,
        ];
    }

    /**
     * Check if point is inside a polygon.
     */
    public static function isPointInPolygon($lat, $lon, $polygon): bool
    {
        $inside = false;
        $j = count($polygon) - 1;

        for ($i = 0; $i < count($polygon); $i++) {
            if (
                ($polygon[$i][1] < $lon && $polygon[$j][1] >= $lon || $polygon[$j][1] < $lon && $polygon[$i][1] >= $lon) &&
                ($polygon[$i][0] <= $lat || $polygon[$j][0] <= $lat)
            ) {
                if ($polygon[$i][0] + ($lon - $polygon[$i][1]) / ($polygon[$j][1] - $polygon[$i][1]) * ($polygon[$j][0] - $polygon[$i][0]) < $lat) {
                    $inside = !$inside;
                }
            }
            $j = $i;
        }

        return $inside;
    }
}
