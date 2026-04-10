<?php

namespace App\Http\Controllers;

use App\Events\UserPermissionsUpdated;
use App\Http\Requests\AssignPermissionsRequest;
use App\Http\Resources\UserResource;
use App\Models\ServiceRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class AdminController extends Controller
{
    /**
     * GET /api/admin/users
     * List all users with their roles and direct permissions.
     */
    public function users(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 20);
            
            // Try to load with roles and permissions, fall back if tables don't exist
            try {
                $users = User::with(['roles', 'permissions'])
                    ->latest()
                    ->paginate($perPage);
            } catch (\Exception $e) {
                \Log::warning('[AdminController] Spatie tables may not exist, loading users without roles/permissions', [
                    'error' => $e->getMessage(),
                ]);
                $users = User::latest()->paginate($perPage);
            }

            return response()->json([
                'data' => UserResource::collection($users->items()),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Users error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to load users.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * GET /api/admin/users/{user}
     * Show a single user with roles, permissions, and their service requests summary.
     */
    public function showUser(User $user): JsonResponse
    {
        $user->load(['roles', 'permissions']);

        return response()->json([
            'data' => (new UserResource($user))->toArray(request()),
            'stats' => [
                'request_count' => ServiceRequest::where('customer_id', $user->id)->count(),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/users/{user}/plan
     * Toggle a user's subscription plan between free and paid.
     */
    public function updatePlan(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::in(['free', 'basic', 'paid', 'premium', 'enterprise'])],
        ]);

        $user->update(['plan' => $validated['plan']]);

        return response()->json([
            'message' => "User plan updated to [{$validated['plan']}].",
            'data' => new UserResource($user->load('roles')),
        ]);
    }

    /**
     * POST /api/admin/users/{user}/permissions
     * Sync direct permissions on a user (model-level override, takes priority over role).
     *
     * Provider Admins are scoped: they may only manage users whose provider_company_id
     * matches their own (currently simplified — Admin has no scope restriction).
     *
     * Body: { "permissions": ["request.accept", "request.complete"] }
     */
    public function syncPermissions(AssignPermissionsRequest $request, User $user): JsonResponse
    {
        // Provider Admin scope guard — cannot escalate to admin-only permissions
        if (!$request->user()->hasRole('admin')) {
            $adminOnlyPermissions = ['user.manage', 'permission.assign'];
            $requested = $request->permissions;

            $forbidden = array_intersect($requested, $adminOnlyPermissions);
            if (!empty($forbidden)) {
                return response()->json([
                    'message' => 'You cannot assign admin-level permissions.',
                    'forbidden' => $forbidden,
                ], 403);
            }
        }

        // syncPermissions replaces all direct permissions (revoke old, grant new)
        $user->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permissions updated.',
            'user' => $user->name,
            'permissions' => $user->getDirectPermissions()->pluck('name'),
        ]);
    }

    /**
     * DELETE /api/admin/users/{user}/permissions
     * Revoke ALL direct permissions (revert user to role defaults).
     */
    public function revokeAllPermissions(User $user): JsonResponse
    {
        $user->syncPermissions([]);

        return response()->json([
            'message' => 'All direct permissions revoked. User now operates under role defaults only.',
        ]);
    }

    /**
     * PUT/PATCH /api/admin/users/{user}
     * Update user details (name, email, role, plan)
     */
    public function updateUser(Request $request, User $user): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255|unique:users,email,' . $user->id,
                'role' => 'nullable|string',
                'plan' => 'nullable|in:free,premium,enterprise',
            ]);

            $updateData = [];
            if (isset($validated['name'])) $updateData['name'] = $validated['name'];
            if (isset($validated['email'])) $updateData['email'] = $validated['email'];
            if (isset($validated['plan'])) $updateData['plan'] = $validated['plan'];

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            // Update role if provided
            if (isset($validated['role']) && !empty($validated['role'])) {
                try {
                    $user->syncRoles([$validated['role']]);
                } catch (\Exception $e) {
                    \Log::warning('[AdminController] Role update failed, may not have Spatie installed', ['error' => $e->getMessage()]);
                    // Don't fail the whole request if role update fails
                }
            }

            $user->load(['roles', 'permissions']);

            return response()->json([
                'message' => 'User updated successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('[AdminController] Update user validation error: ' . json_encode($e->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Update user error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/users/{user}
     * Delete a user
     */
    public function deleteUser(User $user): JsonResponse
    {
        try {
            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Delete user error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * POST /api/users/{user}/permissions
     * Grant a single direct permission to a user (Advanced RBAC).
     * Body: { "permission": "request.accept", "reason": "...", "expires_at": "2026-01-01" }
     */
    public function grantDirectPermission(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permission' => ['required', 'string'],
        ]);

        try {
            $user->givePermissionTo($validated['permission']);
            event(new UserPermissionsUpdated($user, 'granted', $validated['permission']));

            return response()->json([
                'success' => true,
                'message' => "Permission [{$validated['permission']}] granted to {$user->name}.",
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            // Handle case where permissions table doesn't exist
            if (str_contains($e->getMessage(), 'Base table or view not found') || str_contains($e->getMessage(), 'no such table')) {
                \Log::warning('[AdminController] Permissions table may not exist', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Permission system not available. Please ensure Spatie Permission tables are migrated.',
                ], 503);
            }
            throw $e;
        }
    }

    /**
     * DELETE /api/users/{user}/permissions/{permission}
     * Revoke a single direct permission from a user.
     */
    public function revokeDirectPermission(Request $request, User $user, string $permission): JsonResponse
    {
        try {
            if (!$user->hasPermissionTo($permission)) {
                return response()->json([
                    'success' => false,
                    'message' => "Permission [{$permission}] was not found on user {$user->name}.",
                ], 404);
            }

            $user->revokePermissionTo($permission);

            event(new UserPermissionsUpdated($user, 'revoked', $permission));

            return response()->json([
                'success' => true,
                'message' => "Permission [{$permission}] revoked from {$user->name}.",
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            // Handle case where permissions table doesn't exist
            if (str_contains($e->getMessage(), 'Base table or view not found') || str_contains($e->getMessage(), 'no such table')) {
                \Log::warning('[AdminController] Permissions table may not exist', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Permission system not available. Please ensure Spatie Permission tables are migrated.',
                ], 503);
            }
            throw $e;
        }
    }

    /**
     * GET /api/users/{user}/permissions
     * Show all effective permissions for a given user.
     */
    public function getUserPermissions(User $user): JsonResponse
    {
        try {
            $user->load(['roles', 'permissions']);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'roles' => $user->roles->pluck('name'),
                    'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
                    'permissions_via_roles' => $user->getPermissionsViaRoles()->pluck('name'),
                    'all_permissions' => $user->getAllPermissions()->pluck('name'),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            // Handle case where permissions table doesn't exist
            if (str_contains($e->getMessage(), 'Base table or view not found') || str_contains($e->getMessage(), 'no such table')) {
                \Log::warning('[AdminController] Permissions table may not exist', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Permission system not available. Please ensure Spatie Permission tables are migrated.',
                    'data' => [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ],
                        'roles' => [],
                        'direct_permissions' => [],
                        'permissions_via_roles' => [],
                        'all_permissions' => [],
                    ],
                ], 503);
            }
            throw $e;
        }
    }

    /**
     * GET /api/admin/permissions
     * List all available permissions (for the frontend dropdown).
     */
    public function permissions(): JsonResponse
    {
        return response()->json([
            'data' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    /**
     * GET /api/admin/stats
     * Return high-level marketplace metrics for the dashboard.
     */
    public function stats(): JsonResponse
    {
        try {
            return response()->json([
                'data' => [
                    'users' => [
                        'total' => User::count(),
                        'paid' => User::where('plan', 'paid')->count(),
                        'free' => User::where('plan', 'free')->count(),
                    ],
                    'requests' => [
                        'total' => ServiceRequest::count(),
                        'pending' => ServiceRequest::where('status', 'pending')->count(),
                        'accepted' => ServiceRequest::where('status', 'accepted')->count(),
                        'completed' => ServiceRequest::where('status', 'completed')->count(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Stats error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to load dashboard statistics.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * GET /api/admin/subscriptions/pending
     */
    public function pendingSubscriptions(): JsonResponse
    {
        $subscriptions = \App\Models\UserSubscription::with(['user', 'subscriptionPlan'])
            ->where('status', 'pending')
            ->latest()
            ->get();

        return response()->json([
            'data' => $subscriptions
        ]);
    }

    /**
     * POST /api/admin/subscriptions/{subscription}/accept
     */
    public function acceptSubscription(\App\Models\UserSubscription $subscription): JsonResponse
    {
        if ($subscription->status !== 'pending') {
            return response()->json(['message' => 'Subscription is not pending'], 400);
        }

        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return response()->json([
            'message' => 'Subscription accepted successfully',
            'data' => $subscription->fresh(['user', 'subscriptionPlan'])
        ]);
    }

    /**
     * GET /api/admin/plans
     * List all subscription plans
     */
    public function plans(): JsonResponse
    {
        try {
            $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('price')->get();
            return response()->json([
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Plans error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load plans',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * POST /api/admin/plans
     * Create a new subscription plan
     */
    public function createPlan(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'description_ar' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'interval' => 'required|in:monthly,yearly',
                'limits' => 'nullable|array',
                'features' => 'nullable|array',
            ]);

            $plan = SubscriptionPlan::create([
                'name' => $validated['name'],
                'display_name' => $validated['name_ar'] ?? $validated['name'],
                'description' => $validated['description'] ?? '',
                'price' => $validated['price'],
                'billing_cycle' => $validated['interval'],
                'features' => $validated['features'] ?? [],
                'limits' => $validated['limits'] ?? [],
                'is_active' => true,
                'sort_order' => 0,
            ]);

            return response()->json([
                'message' => 'Plan created successfully',
                'data' => $plan
            ], 201);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Create plan error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create plan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * PATCH /api/admin/plans/{plan}
     * Update a subscription plan
     * 
     * NOTE: This endpoint only supports PATCH method, not PUT.
     * Frontend should use api.patch() for plan updates.
     */
    public function updatePlanDetails(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'name_ar' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'description_ar' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'interval' => 'nullable|in:monthly,yearly',
                'limits' => 'nullable|array',
                'features' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ]);

            $updateData = [];
            if (isset($validated['name'])) $updateData['name'] = $validated['name'];
            if (isset($validated['name_ar'])) $updateData['display_name'] = $validated['name_ar'];
            if (isset($validated['description'])) $updateData['description'] = $validated['description'];
            if (isset($validated['price'])) $updateData['price'] = $validated['price'];
            if (isset($validated['interval'])) $updateData['billing_cycle'] = $validated['interval'];
            if (isset($validated['features'])) $updateData['features'] = $validated['features'];
            if (isset($validated['limits'])) $updateData['limits'] = $validated['limits'];
            if (isset($validated['is_active'])) $updateData['is_active'] = $validated['is_active'];

            $plan->update($updateData);

            return response()->json([
                'message' => 'Plan updated successfully',
                'data' => $plan->fresh()
            ]);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Update plan error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update plan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/plans/{plan}
     * Delete a subscription plan
     */
    public function deletePlan(SubscriptionPlan $plan): JsonResponse
    {
        try {
            $plan->delete();
            return response()->json([
                'message' => 'Plan deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('[AdminController] Delete plan error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete plan',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
