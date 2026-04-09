<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestSimple extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'provider_id',
        'title',
        'description',
        'status',
        'latitude',
        'longitude',
        'category',
        'urgency',
        'attachments',
        'accepted_at',
        'completed_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'attachments' => 'array',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_COMPLETED = 'completed';

    // Category options
    const CATEGORIES = [
        'plumbing',
        'electrical', 
        'hvac',
        'cleaning',
        'landscaping',
        'pest_control',
        'other'
    ];

    // Urgency levels
    const URGENCY_LEVELS = [
        'low',
        'medium', 
        'high',
        'emergency'
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    // Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeForCustomer(Builder $query, $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForProvider(Builder $query, $providerId): Builder
    {
        return $query->where('provider_id', $providerId);
    }

    public function scopeAvailableForProvider(Builder $query, $providerId): Builder
    {
        return $query->where(function ($q) use ($providerId) {
            $q->where('status', self::STATUS_PENDING)
              ->orWhere('provider_id', $providerId);
        });
    }

    /**
     * Filter service requests within $radiusKm of the given coordinates
     * Uses MySQL 8 native spatial functions for optimal performance
     */
    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusKm = 50): Builder
    {
        // Validate ranges defensively
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

    // Business Logic Methods
    public function canBeAccepted(): bool
    {
        return $this->status === self::STATUS_PENDING && is_null($this->provider_id);
    }

    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED && !is_null($this->provider_id);
    }

    public function isOwnedByCustomer($customerId): bool
    {
        return $this->customer_id === $customerId;
    }

    public function isAssignedToProvider($providerId): bool
    {
        return $this->provider_id === $providerId;
    }

    public function acceptByProvider($providerId): bool
    {
        if (!$this->canBeAccepted()) {
            return false;
        }

        if ($this->customer_id === $providerId) {
            throw new \Exception('Provider cannot accept their own request');
        }

        return $this->update([
            'provider_id' => $providerId,
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function completeByProvider(): bool
    {
        if (!$this->canBeCompleted()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function getDistanceFrom($lat, $lng): float
    {
        // Using Haversine formula for distance calculation
        $earthRadius = 6371; // Earth's radius in kilometers

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($lat);
        $lonTo = deg2rad($lng);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'yellow',
            self::STATUS_ACCEPTED => 'blue', 
            self::STATUS_COMPLETED => 'green',
            default => 'gray'
        };
    }

    public function getUrgencyColor(): string
    {
        return match($this->urgency) {
            'emergency' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'green',
            default => 'gray'
        };
    }

    // Accessors
    public function getFormattedCreatedAtAttribute(): string
    {
        return $this->created_at->format('M d, Y H:i');
    }

    public function getFormattedAcceptedAtAttribute(): ?string
    {
        return $this->accepted_at?->format('M d, Y H:i');
    }

    public function getFormattedCompletedAtAttribute(): ?string
    {
        return $this->completed_at?->format('M d, Y H:i');
    }

    public function getDurationAttribute(): ?string
    {
        if (!$this->accepted_at || !$this->completed_at) {
            return null;
        }

        $duration = $this->accepted_at->diff($this->completed_at);
        
        if ($duration->days > 0) {
            return $duration->days . ' days';
        } elseif ($duration->h > 0) {
            return $duration->h . ' hours';
        } else {
            return $duration->i . ' minutes';
        }
    }
}
