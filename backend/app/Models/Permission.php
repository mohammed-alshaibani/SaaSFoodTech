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
    public function scopes()
    {
        return $this->hasMany(PermissionScope::class);
    }
}
