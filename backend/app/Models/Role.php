<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Get the child roles for this role.
     */
    public function childRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_hierarchy', 'parent_role_id', 'child_role_id');
    }

    /**
     * Get the parent roles for this role.
     */
    public function parentRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_hierarchy', 'child_role_id', 'parent_role_id');
    }

    /**
     * Get all permissions including inherited from parent roles.
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        $permissions = $this->permissions;

        // Get permissions from parent roles
        foreach ($this->parentRoles as $parentRole) {
            $permissions = $permissions->merge($parentRole->getAllPermissions());
        }

        return $permissions->unique('id');
    }

    /**
     * Check if role has permission (including inherited).
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Check direct permissions
        if (parent::hasPermissionTo($permission, $guardName)) {
            return true;
        }

        // Check inherited permissions from parent roles
        foreach ($this->parentRoles as $parentRole) {
            if ($parentRole->hasPermissionTo($permission, $guardName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if role is parent of another role.
     */
    public function isParentOf(Role $role): bool
    {
        return $this->childRoles()->where('child_role_id', $role->id)->exists();
    }

    /**
     * Check if role is child of another role.
     */
    public function isChildOf(Role $role): bool
    {
        return $this->parentRoles()->where('parent_role_id', $role->id)->exists();
    }

    /**
     * Get all descendant roles (recursive).
     */
    public function getDescendants(): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($this->childRoles as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Get all ancestor roles (recursive).
     */
    public function getAncestors(): \Illuminate\Support\Collection
    {
        $ancestors = collect();

        foreach ($this->parentRoles as $parent) {
            $ancestors->push($parent);
            $ancestors = $ancestors->merge($parent->getAncestors());
        }

        return $ancestors;
    }

    /**
     * Add child role with hierarchy validation.
     */
    public function addChildRole(Role $childRole): bool
    {
        // Prevent circular references
        if ($this->wouldCreateCircularReference($childRole)) {
            return false;
        }

        $this->childRoles()->attach($childRole->id);
        return true;
    }

    /**
     * Check if adding child role would create circular reference.
     */
    private function wouldCreateCircularReference(Role $childRole): bool
    {
        return $childRole->getDescendants()->contains($this) || $this->id === $childRole->id;
    }

    /**
     * Remove child role.
     */
    public function removeChildRole(Role $childRole): int
    {
        return $this->childRoles()->detach($childRole->id);
    }

    /**
     * Scope a query to only include root roles (no parents).
     */
    public function scopeRoot($query)
    {
        return $query->whereDoesntHave('parentRoles');
    }

    /**
     * Scope a query to only include leaf roles (no children).
     */
    public function scopeLeaf($query)
    {
        return $query->whereDoesntHave('childRoles');
    }
}
