<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'plan' => 'free',
        ]);

        // Role assignment logic
        $role = $request->role;
        
        // Log the received role for debugging
        \Log::info('Registration role assignment', [
            'requested_role' => $role,
            'user_id' => $user->id
        ]);
        
        if ($role === 'provider')
            $role = 'provider_admin';
        
        // Ensure only valid roles are assigned
        $validRoles = ['admin', 'provider_admin', 'customer'];
        if (!in_array($role, $validRoles))
            $role = 'customer';

        $user->assignRole($role, 'sanctum');

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->buildUserPayload($user),
        ], 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke previous tokens on login to limit active session sprawl (optional)
        // $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->buildUserPayload($user),
        ]);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/me
     * Returns the authenticated user enriched with subscription gate data.
     * Frontend uses `request_count` and `limit_reached` to render the upgrade banner
     * and disable the "New Request" button.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json($this->buildUserPayload($request->user()));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build the standardized user payload used in auth responses and /me.
     *
     * @return array<string, mixed>
     */
    private function buildUserPayload(User $user): array
    {
        $user->load('roles');

        $requestCount = $user->hasRole('customer')
            ? \App\Models\ServiceRequest::where('customer_id', $user->id)->count()
            : 0;

        $currentPlan = $user->getCurrentPlan();
        $freeLimit = 3;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'plan' => $currentPlan,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'request_count' => $requestCount,
            'limit_reached' => $user->hasExceededRequestLimit(),
            'free_limit' => $freeLimit,
        ];
    }
}
