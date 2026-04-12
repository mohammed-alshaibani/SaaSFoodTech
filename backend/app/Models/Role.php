<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    protected $guard_name = 'sanctum';

    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Add a child role to this role.
     */
    public function addChildRole(Role $role)
    {
        return \Illuminate\Support\Facades\DB::table('role_hierarchy')->updateOrInsert(
            ['parent_role_id' => $this->id, 'child_role_id' => $role->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Check if this role is a child of another role.
     */
    public function isChildOf(Role $role): bool
    {
        return \Illuminate\Support\Facades\DB::table('role_hierarchy')
            ->where('parent_role_id', $role->id)
            ->where('child_role_id', $this->id)
            ->exists();
    }
}
