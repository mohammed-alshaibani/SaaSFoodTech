<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'permission_id',
        'type',
        'granted_by',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the permission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the permission.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Get the user who granted the permission.
     */
    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * Check if permission is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if permission is active.
     */
    public function isActive(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Scope a query to only include active permissions.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope a query to only include granted permissions.
     */
    public function scopeGranted($query)
    {
        return $query->where('type', 'grant');
    }

    /**
     * Scope a query to only include denied permissions.
     */
    public function scopeDenied($query)
    {
        return $query->where('type', 'deny');
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to filter by permission.
     */
    public function scopeByPermission($query, $permissionId)
    {
        return $query->where('permission_id', $permissionId);
    }
}
