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

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'attachments' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 50)
    {
        if (config('database.default') === 'sqlite') {
            $latDelta = $radiusKm / 111.32;
            $lngDelta = $radiusKm / (111.32 * cos(deg2rad($lat)));

            return $query
                ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
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

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
