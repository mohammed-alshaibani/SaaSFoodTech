<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
        'category_id',
        'description',
        'group',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Get the category that owns the permission.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PermissionCategory::class);
    }

    /**
     * Get the permission scopes for the permission.
     */
    public function permissionScopes(): HasMany
    {
        return $this->hasMany(PermissionScope::class);
    }

    /**
     * Scope a query to only include system permissions.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope a query to only include custom permissions.
     */
    public function scopeCustom($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope a query to filter by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope a query to filter by group.
     */
    public function scopeByGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Check if permission is scoped.
     */
    public function isScoped(): bool
    {
        return $this->permissionScopes()->exists();
    }

    /**
     * Get permission with full details including category.
     */
    public function toArray()
    {
        $array = parent::toArray();
        $array['category'] = $this->category;
        $array['scopes'] = $this->permissionScopes;
        $array['is_scoped'] = $this->isScoped();

        return $array;
    }
}
