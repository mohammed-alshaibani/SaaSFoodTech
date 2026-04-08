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
}
