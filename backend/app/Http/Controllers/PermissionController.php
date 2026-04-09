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
        $query = Permission::with(['category', 'permissionScopes']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->byCategory($request->category_id);
        }

        // Filter by group
        if ($request->has('group')) {
            $query->byGroup($request->group);
        }

        // Filter by type (system/custom)
        if ($request->has('type')) {
            if ($request->type === 'system') {
                $query->system();
            } elseif ($request->type === 'custom') {
                $query->custom();
            }
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $permissions = $query->orderBy('category_id')
            ->orderBy('group')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'permissions' => $permissions,
            'categories' => PermissionCategory::ordered()->get(),
            'groups' => Permission::distinct()->pluck('group')->filter(),
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:permission_categories,id',
            'is_system' => 'boolean',
            'scopes' => 'nullable|array',
            'scopes.*.scope_type' => 'required|string|in:location,department,team,self',
            'scopes.*.scope_values' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $permission = Permission::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'group' => $validated['group'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'is_system' => $validated['is_system'] ?? false,
                'guard_name' => 'api',
            ]);

            // Create scopes if provided
            if (!empty($validated['scopes'])) {
                foreach ($validated['scopes'] as $scopeData) {
                    $permission->permissionScopes()->create([
                        'scope_type' => $scopeData['scope_type'],
                        'scope_values' => $scopeData['scope_values'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Permission created successfully',
                'permission' => $permission->load(['category', 'scopes'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
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
        $permission->load(['category', 'permissionScopes']);

        return response()->json(['permission' => $permission]);
    }

    /**
     * Update the specified permission.
     */
    public function update(Request $request, Permission $permission): JsonResponse
    {
        if ($permission->is_system) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'System permissions cannot be modified'
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
            'description' => 'nullable|string',
            'group' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:permission_categories,id',
            'scopes' => 'nullable|array',
            'scopes.*.scope_type' => 'required|string|in:location,department,team,self',
            'scopes.*.scope_values' => 'required|array',
        ]);

        try {
            DB::beginTransaction();

            $oldValues = $permission->toArray();

            $permission->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'group' => $validated['group'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
            ]);

            // Update scopes
            if (isset($validated['scopes'])) {
                $permission->permissionScopes()->delete();
                foreach ($validated['scopes'] as $scopeData) {
                    $permission->permissionScopes()->create([
                        'scope_type' => $scopeData['scope_type'],
                        'scope_values' => $scopeData['scope_values'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Permission updated successfully',
                'permission' => $permission->fresh()->load(['category', 'scopes'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to update permission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified permission.
     */
    public function destroy(Permission $permission): JsonResponse
    {
        if ($permission->is_system) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'System permissions cannot be deleted'
            ], 403);
        }

        try {
            DB::beginTransaction();

            $oldValues = $permission->toArray();

            $permission->permissionScopes()->delete();
            $permission->delete();

            DB::commit();

            return response()->json([
                'message' => 'Permission deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to delete permission',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get permission categories.
     */
    public function categories(): JsonResponse
    {
        $categories = PermissionCategory::withCount('permissions')
            ->ordered()
            ->get();

        return response()->json(['categories' => $categories]);
    }

    /**
     * Store a new permission category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permission_categories,name',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
        ]);

        $category = PermissionCategory::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

}
