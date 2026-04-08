<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;

use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;
use App\Services\Subscription\PlanResolverManager;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles {
        hasPermissionTo as traitHasPermissionTo;
    }

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

    /**
     * Get direct user permissions.
     */
    public function userPermissions()
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * Get active direct user permissions.
     */
    public function activeUserPermissions()
    {
        return $this->userPermissions()->active();
    }

    /**
     * Check if user has permission (including direct user permissions).
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Check role-based permissions first
        if ($this->traitHasPermissionTo($permission, $guardName)) {
            return true;
        }

        // Check direct user permissions
        $permissionName = is_string($permission) ? $permission : $permission->name;

        $userPermission = $this->activeUserPermissions()
            ->whereHas('permission', function ($query) use ($permissionName) {
                $query->where('name', $permissionName);
            })
            ->first();

        if ($userPermission) {
            return $userPermission->type === 'grant';
        }

        return false;
    }

    /**
     * Grant direct permission to user.
     */
    public function grantPermission($permission, $reason = null, $expiresAt = null, $grantedBy = null)
    {
        $permissionModel = is_string($permission)
            ? Permission::where('name', $permission)->first()
            : $permission;

        if (!$permissionModel) {
            return null;
        }

        return $this->userPermissions()->updateOrCreate(
            ['permission_id' => $permissionModel->id],
            [
                'type' => 'grant',
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'granted_by' => $grantedBy ?? (Auth::check() ? Auth::id() : null),
            ]
        );
    }

    /**
     * Deny direct permission to user.
     */
    public function denyPermission($permission, $reason = null, $expiresAt = null, $grantedBy = null)
    {
        $permissionModel = is_string($permission)
            ? Permission::where('name', $permission)->first()
            : $permission;

        if (!$permissionModel) {
            return null;
        }

        return $this->userPermissions()->updateOrCreate(
            ['permission_id' => $permissionModel->id],
            [
                'type' => 'deny',
                'reason' => $reason,
                'expires_at' => $expiresAt,
                'granted_by' => $grantedBy ?? (Auth::check() ? Auth::id() : null),
            ]
        );
    }

    /**
     * Remove direct permission from user.
     */
    public function removeDirectPermission($permission)
    {
        $permissionModel = is_string($permission)
            ? Permission::where('name', $permission)->first()
            : $permission;

        if (!$permissionModel) {
            return false;
        }

        return $this->userPermissions()->where('permission_id', $permissionModel->id)->delete();
    }

    /**
     * Get all effective permissions (roles + direct user permissions).
     */
    public function getAllEffectivePermissions()
    {
        $rolePermissions = $this->getAllPermissions();

        $directPermissions = $this->activeUserPermissions()
            ->with('permission')
            ->get()
            ->map(function ($userPermission) {
                return $userPermission->type === 'grant'
                    ? $userPermission->permission
                    : null;
            })
            ->filter();

        return $rolePermissions->merge($directPermissions)->unique('id');
    }

    /**
     * Get the user's current plan name using strategy pattern.
     */
    public function getCurrentPlan(): string
    {
        return PlanResolverManager::resolve($this);
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
            ->whereMonth('created_at', now()->month)
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
            $used = $this->serviceRequests()->whereMonth('created_at', now()->month)->count();
            
            return [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit === 'unlimited' ? 'unlimited' : max(0, $limit - $used),
                'percentage' => $limit === 'unlimited' ? 0 : round(($used / $limit) * 100, 2),
            ];
        }

        $limit = $activeSubscription->getPlanLimit('requests_per_month', 'unlimited');
        $used = $this->serviceRequests()->whereMonth('created_at', now()->month)->count();

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
            return match($feature) {
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
}
