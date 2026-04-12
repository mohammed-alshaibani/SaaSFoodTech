<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $guard_name = 'sanctum';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'plan',
        'latitude',
        'longitude',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function serviceRequests()
    {
        return $this->hasMany(ServiceRequest::class, 'customer_id');
    }

    public function acceptedRequests()
    {
        return $this->hasMany(ServiceRequest::class, 'provider_id');
    }

    /**
     * Get the user's subscriptions.
     */
    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    /**
     * Get the user's active subscription.
     */
    public function activeSubscription()
    {
        return $this->subscriptions()->active()->first();
    }

    /**
     * Get the user's subscription plan.
     */
    public function subscriptionPlan()
    {
        return $this->hasOneThrough(
            SubscriptionPlan::class,
            UserSubscription::class,
            'user_id',
            'id',
            'id',
            'subscription_plan_id'
        )->active();
    }

    // Spatie HasRoles provides all permission logic intrinsically.

    /**
     * Get the user's current plan name using strategy pattern.
     */
    public function getCurrentPlan(): string
    {
        $activeSubscription = $this->activeSubscription();
        if ($activeSubscription && $activeSubscription->subscriptionPlan) {
            return $activeSubscription->subscriptionPlan->name;
        }

        return $this->plan ?? 'free';
    }

    /**
     * Check if user is on a specific plan.
     */
    public function isOnPlan(string $planName): bool
    {
        return $this->getCurrentPlan() === $planName;
    }

    /**
     * Check if user has exceeded their monthly request limit.
     */
    public function hasExceededRequestLimit(): bool
    {
        $activeSubscription = $this->activeSubscription();

        if (!$activeSubscription) {
            // Fallback to legacy plan logic
            return $this->plan !== 'free' ? false :
                $this->serviceRequests()->whereMonth('created_at', now()->month)->count() >= 3;
        }

        $limit = $activeSubscription->getPlanLimit('requests_per_month', 'unlimited');

        if ($limit === 'unlimited') {
            return false;
        }

        $currentCount = $this->serviceRequests()
            ->whereIn('status', ['pending', 'accepted'])
            ->count();

        return $currentCount >= $limit;
    }

    /**
     * Get current month's request usage.
     */
    public function getCurrentMonthUsage(): array
    {
        $activeSubscription = $this->activeSubscription();

        if (!$activeSubscription) {
            // Fallback to legacy plan logic
            $limit = $this->plan === 'free' ? 3 : 'unlimited';
            $used = $this->serviceRequests()->whereIn('status', ['pending', 'accepted'])->count();

            return [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit === 'unlimited' ? 'unlimited' : max(0, $limit - $used),
                'percentage' => $limit === 'unlimited' ? 0 : round(($used / $limit) * 100, 2),
            ];
        }

        $limit = $activeSubscription->getPlanLimit('requests_per_month', 'unlimited');
        $used = $this->serviceRequests()->whereIn('status', ['pending', 'accepted'])->count();

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === 'unlimited' ? 'unlimited' : max(0, $limit - $used),
            'percentage' => $limit === 'unlimited' ? 0 : round(($used / $limit) * 100, 2),
        ];
    }

    /**
     * Check if user has access to a specific feature.
     */
    public function hasFeatureAccess(string $feature): bool
    {
        $activeSubscription = $this->activeSubscription();

        if (!$activeSubscription) {
            // Fallback to legacy plan logic
            return match ($feature) {
                'ai_enhancement' => in_array($this->plan, ['premium', 'enterprise']),
                'priority_support' => in_array($this->plan, ['premium', 'enterprise']),
                'api_access' => $this->plan === 'enterprise',
                default => true,
            };
        }

        return $activeSubscription->hasFeature($feature);
    }

    /**
     * Subscribe to a plan.
     */
    public function subscribeTo(SubscriptionPlan $plan, array $options = []): UserSubscription
    {
        // Cancel any existing active subscriptions
        $this->subscriptions()->active()->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        return $this->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $options['ends_at'] ?? null,
            'trial_ends_at' => $options['trial_ends_at'] ?? null,
            'metadata' => $options['metadata'] ?? [],
        ]);
    }

    /**
     * Get all effective permissions for the user (including roles and direct).
     */
    public function getAllEffectivePermissions()
    {
        return $this->getAllPermissions();
    }
}
