<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProviderUserController extends Controller
{
    /**
     * GET /api/provider/users
     * List all employees/users under this provider
     */
    public function index(Request $request): JsonResponse
    {
        // Require policy or simple check (must be a provider admin)
        if (!$request->user()->hasRole('provider_admin')) {
            abort(403, 'Only Provider Admins can view the team roster.');
        }

        // Ideally, multi-tenancy binds them by provider_id. We'll simulate by returning all provider staff for MVP.
        $employees = User::role(['provider_employee', 'provider_admin'])->get()->map(function ($user) {
            // Include roles and directly assigned permissions for the dynamic UI
            $user->parsed_role = $user->roles->first()->name ?? 'provider_employee';
            $user->direct_permissions = $user->getAllPermissions()->pluck('name');
            return $user;
        });

        return response()->json([
            'data' => $employees,
            'message' => 'Team roster retrieved'
        ]);
    }

    /**
     * POST /api/provider/users
     * Create a new employee
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('provider_admin')) {
            abort(403, 'Only Provider Admins can create new employees.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => 'required|in:provider_employee,provider_admin',
            'status' => 'nullable|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make('password123'),
            'status' => $validated['status'] ?? 'active'
        ]);

        $user->assignRole($validated['role']);

        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        $user->parsed_role = $validated['role'];
        $user->direct_permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'message' => 'Team member created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * PUT/PATCH /api/provider/users/{user}
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasRole('provider_admin')) {
            abort(403, 'Only Provider Admins can update employees.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:provider_employee,provider_admin',
            'status' => 'sometimes|string',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string'
        ]);

        $user->update($request->only(['name', 'email', 'status']));

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        $user->parsed_role = $user->roles->first()->name ?? 'provider_employee';
        $user->direct_permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'message' => 'Team member updated successfully',
            'data' => $user
        ]);
    }

    /**
     * DELETE /api/provider/users/{user}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasRole('provider_admin')) {
            abort(403, 'Only Provider Admins can delete employees.');
        }

        $user->delete();
        return response()->json(['message' => 'Team member deleted']);
    }
}
