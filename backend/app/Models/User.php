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

    protected $fillable = [
        'name',
        'email',
        'password',
        'plan',
        'latitude',
        'longitude',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

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

    public function subscriptions()
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->subscriptions()->active()->first();
    }

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

    public function getCurrentPlan(): string
    {
        $activeSubscription = $this->activeSubscription();
        if ($activeSubscription && $activeSubscription->subscriptionPlan) {
            return $activeSubscription->subscriptionPlan->name;
        }

        return $this->plan ?? 'free';
    }

    public function isOnPlan(string $planName): bool
    {
        return $this->getCurrentPlan() === $planName;
    }

    public function hasExceededRequestLimit(): bool
    {
        $activeSubscription = $this->activeSubscription();

        if (!$activeSubscription) {
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

    public function getCurrentMonthUsage(): array
    {
        $activeSubscription = $this->activeSubscription();

        if (!$activeSubscription) {
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

    public function hasFeatureAccess(string $feature): bool
    {
        $activeSubscription = $this->activeSubscription();

        if (!$activeSubscription) {
            return match ($feature) {
                'ai_enhancement' => in_array($this->plan, ['premium', 'enterprise']),
                'priority_support' => in_array($this->plan, ['premium', 'enterprise']),
                'api_access' => $this->plan === 'enterprise',
                default => true,
            };
        }

        return $activeSubscription->hasFeature($feature);
    }

    public function subscribeTo(SubscriptionPlan $plan, array $options = []): UserSubscription
    {
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
}
