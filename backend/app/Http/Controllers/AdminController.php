<?php

namespace App\Http\Controllers;

use App\Events\UserPermissionsUpdated;
use App\Http\Requests\AssignPermissionsRequest;
use App\Http\Resources\UserResource;
use App\Models\ServiceRequest;
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
        $users = User::with(['roles', 'permissions'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
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
            'plan' => ['required', Rule::in(['free', 'paid'])],
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
     * POST /api/users/{user}/permissions
     * Grant a single direct permission to a user (Advanced RBAC).
     * Body: { "permission": "request.accept", "reason": "...", "expires_at": "2026-01-01" }
     */
    public function grantDirectPermission(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permission' => ['required', 'string', 'exists:permissions,name'],
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $result = $user->grantPermission(
            $validated['permission'],
            $validated['reason'] ?? null,
            isset($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : null,
            $request->user()->id
        );

        event(new UserPermissionsUpdated($user, 'granted', $validated['permission']));

        return response()->json([
            'success' => true,
            'message' => "Permission [{$validated['permission']}] granted to {$user->name}.",
            'permission' => $result,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * DELETE /api/users/{user}/permissions/{permission}
     * Revoke a single direct permission from a user.
     */
    public function revokeDirectPermission(Request $request, User $user, string $permission): JsonResponse
    {
        $deleted = $user->removeDirectPermission($permission);

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => "Permission [{$permission}] was not found on user {$user->name}.",
            ], 404);
        }

        event(new UserPermissionsUpdated($user, 'revoked', $permission));

        return response()->json([
            'success' => true,
            'message' => "Permission [{$permission}] revoked from {$user->name}.",
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * GET /api/users/{user}/permissions
     * Show all effective permissions for a given user.
     */
    public function getUserPermissions(User $user): JsonResponse
    {
        $user->load(['roles', 'permissions']);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->name,
                'role_permissions' => $user->getPermissionsViaRoles()->pluck('name'),
                'direct_permissions' => $user->getDirectPermissions()->pluck('name'),
                'effective' => $user->getAllPermissions()->pluck('name')->unique()->values(),
            ],
        ]);
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
    }
}
