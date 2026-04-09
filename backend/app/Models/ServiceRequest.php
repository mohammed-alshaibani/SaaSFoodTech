<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasFileUploads;
use App\Traits\HasGeolocation;

class ServiceRequest extends Model
{
    use HasFactory, HasFileUploads, HasGeolocation;
    protected $fillable = [
        'customer_id',
        'provider_id',
        'title',
        'description',
        'status',
        'latitude',
        'longitude',
        'attachments',
    ];

    /**
     * Cast columns to correct types automatically.
     * Without these, latitude/longitude are returned as strings from MySQL.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'attachments' => 'array',
    ];

    // ── Relationships ────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    // ── Query Scopes ─────────────────────────────────────────

    /**
     * Filter service requests within $radiusKm of the given coordinates.
     *
     * MySQL 8 native: uses ST_Distance_Sphere() which leverages spatial indexes
     * for optimal performance. The project runs MySQL in production (Docker) so
     * the SQLite fallback has been deliberately removed.
     *
     * Coordinate ranges are validated at the HTTP layer (StoreServiceRequestRequest
     * and the /nearby endpoint validation), so we trust the values here.
     */
    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 50)
    {
        // Validate ranges defensively (belt-and-suspenders)
        if ($lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException("Latitude must be between -90 and 90, got: {$lat}");
        }
        if ($lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException("Longitude must be between -180 and 180, got: {$lng}");
        }

        return $query
            ->selectRaw(
                '*, ROUND(ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) / 1000, 2) AS distance_km',
                [$lng, $lat]
            )
            ->whereRaw(
                'ST_Distance_Sphere(POINT(longitude, latitude), POINT(?, ?)) <= ?',
                [$lng, $lat, $radiusKm * 1000]
            )
            ->orderBy('distance_km');
    }

    /**
     * Scope to only pending requests — convenience scope.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
