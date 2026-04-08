<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermissionsAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_id',
        'permission_id',
        'action',
        'performed_by',
        'reason',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the role that owns the audit record.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class);
    }

    /**
     * Get the permission that owns the audit record.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Get the user who performed the action.
     */
    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Scope a query to filter by action type.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by performer.
     */
    public function scopeByPerformer($query, $userId)
    {
        return $query->where('performed_by', $userId);
    }
}
