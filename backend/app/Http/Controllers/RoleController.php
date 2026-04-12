<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::with('permissions');

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $roles = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $roles->items(),
            'meta' => [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total(),
            ]
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,guard_name,sanctum',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        try {
            $role = Role::firstOrCreate(
                ['name' => $validated['name'], 'guard_name' => 'sanctum'],
                ['guard_name' => 'sanctum']
            );

            // Assign permissions
            if (!empty($validated['permissions'])) {
                $permissionNames = Permission::whereIn('id', $validated['permissions'])->pluck('name')->toArray();
                $role->syncPermissions($permissionNames);
            }

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role->load('permissions')
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create role',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json(['role' => $role]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)->where('guard_name', 'sanctum')],
                'permissions' => 'nullable|array',
                'permissions.*' => 'exists:permissions,id',
            ]);

            $role->update([
                'name' => $validated['name'],
            ]);

            // Sync permissions only if provided
            if ($request->has('permissions')) {
                if (!empty($validated['permissions'])) {
                    $permissionNames = Permission::whereIn('id', $validated['permissions'])->pluck('name')->toArray();
                    $role->syncPermissions($permissionNames);
                } else {
                    // If permissions array is empty, revoke all permissions
                    $role->syncPermissions([]);
                }
            }

            // Clear Spatie permission cache
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'message' => 'Role updated successfully',
                'data' => $role->fresh()->load('permissions')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update role',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy($id): JsonResponse
    {
        $role = Role::find($id);
        if (!$role) {
            return response()->json(['message' => 'Role not found'], 404);
        }

        try {
            // Protect critical system roles
            if (in_array($role->name, ['admin', 'super-admin'])) {
                return response()->json([
                    'error' => 'Protected Role',
                    'message' => 'System critical roles cannot be deleted.'
                ], 403);
            }

            $role->delete();
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'message' => 'Role deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete role',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Grant permission to role.
     */
    public function grantPermission(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_id' => 'required|exists:permissions,id',
        ]);

        try {
            $permission = Permission::findOrFail($validated['permission_id']);
            $role->givePermissionTo($permission);

            return response()->json([
                'message' => 'Permission granted successfully',
                'role' => $role->fresh()->load('permissions')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to grant permission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke permission from role.
     */
    public function revokePermission(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'permission_id' => 'required|exists:permissions,id',
        ]);

        try {
            $permission = Permission::findOrFail($validated['permission_id']);
            $role->revokePermissionTo($permission);

            return response()->json([
                'message' => 'Permission revoked successfully',
                'role' => $role->fresh()->load('permissions')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to revoke permission',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
