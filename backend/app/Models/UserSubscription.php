<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'ends_at',
        'canceled_at',
        'trial_ends_at',
        'stripe_subscription_id',
        'stripe_customer_id',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription plan for this subscription.
     */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
                    ->where(function ($q) {
                        $q->whereNull('ends_at')
                          ->orWhere('ends_at', '>', now());
                    });
    }

    /**
     * Scope a query to only include canceled subscriptions.
     */
    public function scopeCanceled($query)
    {
        return $query->whereNotNull('canceled_at');
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('ends_at', '<=', now());
    }

    /**
     * Check if the subscription is currently active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               (!$this->ends_at || $this->ends_at->isFuture());
    }

    /**
     * Check if the subscription is in trial period.
     */
    public function isInTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the subscription is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    /**
     * Get the days remaining in the subscription.
     */
    public function getDaysRemaining(): int
    {
        if (!$this->ends_at) {
            return -1; // Unlimited
        }

        return now()->diffInDays($this->ends_at, false);
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(?string $reason = null): bool
    {
        $this->update([
            'canceled_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'cancellation_reason' => $reason,
                'canceled_at' => now()->toISOString(),
            ]),
        ]);

        return true;
    }

    /**
     * Get the plan limits.
     */
    public function getPlanLimits(): array
    {
        return $this->subscriptionPlan->limits ?? [];
    }

    /**
     * Get a specific plan limit.
     */
    public function getPlanLimit(string $key, $default = null)
    {
        return $this->subscriptionPlan->getLimit($key, $default);
    }

    /**
     * Check if the plan has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        return $this->subscriptionPlan->hasFeature($feature);
    }
}
