<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\PermissionCategory;
use App\Models\PermissionScope;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Permission::query();

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $permissions = $query->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $permissions->items(),
            'meta' => [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total(),
            ]
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,NULL,id,guard_name,sanctum',
        ]);

        try {
            $permission = Permission::firstOrCreate(
                ['name' => $validated['name'], 'guard_name' => 'sanctum'],
                ['guard_name' => 'sanctum']
            );

            return response()->json([
                'message' => 'Permission created successfully',
                'permission' => $permission
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create permission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified permission.
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json(['permission' => $permission]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)->where('guard_name', 'sanctum')],
        ]);

        try {
            $permission->update([
                'name' => $validated['name'],
            ]);

            // Clear Spatie permission cache
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json([
                'message' => 'Permission updated successfully',
                'permission' => $permission->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update permission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified permission.
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Check if permission exists without loading model
            $permission = \DB::table('permissions')->where('id', $id)->first();

            if (!$permission) {
                return response()->json([
                    'error' => 'Permission not found'
                ], 404);
            }

            // Delete directly without loading model
            \DB::table('permissions')->where('id', $id)->delete();

            return response()->json([
                'message' => 'Permission deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete permission',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
