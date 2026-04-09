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
        $query = Role::with(['permissions', 'parentRoles', 'childRoles']);

        // Filter by root roles (no parents)
        if ($request->boolean('root_only')) {
            $query->root();
        }

        // Filter by leaf roles (no children)
        if ($request->boolean('leaf_only')) {
            $query->leaf();
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return response()->json(['roles' => $roles]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'parent_roles' => 'nullable|array',
            'parent_roles.*' => 'exists:roles,id',
        ]);

        try {
            DB::beginTransaction();

            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'api',
            ]);

            // Assign permissions
            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            // Set parent roles (hierarchy)
            if (!empty($validated['parent_roles'])) {
                foreach ($validated['parent_roles'] as $parentRoleId) {
                    $parentRole = Role::findOrFail($parentRoleId);
                    if (!$parentRole->addChildRole($role)) {
                        throw new \Exception('Cannot create circular reference in role hierarchy');
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role->load(['permissions', 'parentRoles', 'childRoles'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
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
        $role->load([
            'permissions',
            'parentRoles',
            'childRoles',
        ]);

        // Get all permissions (including inherited)
        $allPermissions = $role->getAllPermissions();

        return response()->json([
            'role' => $role,
            'all_permissions' => $allPermissions,
            'inherited_permissions' => $allPermissions->diff($role->permissions),
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
            'parent_roles' => 'nullable|array',
            'parent_roles.*' => 'exists:roles,id',
        ]);

        try {
            DB::beginTransaction();

            $oldValues = $role->toArray();

            $role->update(['name' => $validated['name']]);

            // Update permissions
            if (isset($validated['permissions'])) {
                $oldPermissions = $role->permissions->pluck('id')->toArray();
                $role->syncPermissions($validated['permissions']);
            }

            // Update parent roles (hierarchy)
            if (isset($validated['parent_roles'])) {
                // Remove existing parent relationships
                $role->parentRoles()->detach();

                // Add new parent relationships
                foreach ($validated['parent_roles'] as $parentRoleId) {
                    $parentRole = Role::findOrFail($parentRoleId);
                    if (!$parentRole->addChildRole($role)) {
                        throw new \Exception('Cannot create circular reference in role hierarchy');
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Role updated successfully',
                'role' => $role->fresh()->load(['permissions', 'parentRoles', 'childRoles'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update role',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            DB::beginTransaction();

            $oldValues = $role->toArray();

            // Check if role has users
            if ($role->users()->count() > 0) {
                return response()->json([
                    'error' => 'Cannot delete role',
                    'message' => 'Role is assigned to users. Please reassign users first.'
                ], 409);
            }

            // Remove hierarchy relationships
            $role->parentRoles()->detach();
            $role->childRoles()->detach();

            $role->delete();

            DB::commit();

            return response()->json([
                'message' => 'Role deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
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
            'reason' => 'nullable|string',
        ]);

        try {
            $permission = Permission::findOrFail($validated['permission_id']);

            if ($role->hasPermissionTo($permission)) {
                return response()->json([
                    'error' => 'Permission already granted',
                    'message' => 'Role already has this permission'
                ], 409);
            }

            $role->givePermissionTo($permission);

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
            'reason' => 'nullable|string',
        ]);

        try {
            $permission = Permission::findOrFail($validated['permission_id']);

            if (!$role->hasPermissionTo($permission)) {
                return response()->json([
                    'error' => 'Permission not found',
                    'message' => 'Role does not have this permission'
                ], 404);
            }

            $role->revokePermissionTo($permission);

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

    /**
     * Add child role to role hierarchy.
     */
    public function addChildRole(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'child_role_id' => 'required|exists:roles,id|different:' . $role->id,
        ]);

        try {
            $childRole = Role::findOrFail($validated['child_role_id']);

            if (!$role->addChildRole($childRole)) {
                return response()->json([
                    'error' => 'Cannot create hierarchy',
                    'message' => 'This would create a circular reference in the role hierarchy'
                ], 409);
            }

            return response()->json([
                'message' => 'Child role added successfully',
                'role' => $role->fresh()->load(['parentRoles', 'childRoles'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add child role',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove child role from role hierarchy.
     */
    public function removeChildRole(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'child_role_id' => 'required|exists:roles,id',
        ]);

        try {
            $childRole = Role::findOrFail($validated['child_role_id']);
            $role->removeChildRole($childRole);

            return response()->json([
                'message' => 'Child role removed successfully',
                'role' => $role->fresh()->load(['parentRoles', 'childRoles'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to remove child role',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role hierarchy tree.
     */
    public function hierarchy(): JsonResponse
    {
        $rootRoles = Role::with(['childRoles.childRoles', 'permissions'])
            ->root()
            ->get();

        return response()->json(['hierarchy' => $rootRoles]);
    }

}
