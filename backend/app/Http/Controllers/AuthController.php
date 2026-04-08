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
        if ($role === 'provider')
            $role = 'provider_admin';
        if (!in_array($role, ['admin', 'provider_admin', 'customer']))
            $role = 'customer';

        $user->assignRole($role);

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
            ? ServiceRequest::where('customer_id', $user->id)->count()
            : 0;

        $freeLimit = 3;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'plan' => $user->plan,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllEffectivePermissions()->pluck('name'),
            'request_count' => $requestCount,
            'limit_reached' => $user->plan === 'free' && $requestCount >= $freeLimit,
            'free_limit' => $freeLimit,
        ];
    }
}
