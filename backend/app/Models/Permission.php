<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Get the scopes for the permission.
     */
    public function permissionScopes()
    {
        return $this->hasMany(PermissionScope::class);
    }

    /**
     * Check if the permission has any scopes defined.
     */
    public function isScoped(): bool
    {
        return $this->permissionScopes()->exists();
    }
}
