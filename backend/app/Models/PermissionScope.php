<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionScope extends Model
{
    use HasFactory;

    protected $fillable = [
        'permission_id',
        'scope_type',
        'scope_values',
    ];

    protected $casts = [
        'scope_values' => 'array',
    ];

    /**
     * Get the permission that owns the scope.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Check if scope applies to given value.
     */
    public function appliesTo($value): bool
    {
        return in_array($value, $this->scope_values);
    }

    /**
     * Scope a query to filter by scope type.
     */
    public function scopeByType($query, $scopeType)
    {
        return $query->where('scope_type', $scopeType);
    }
}
