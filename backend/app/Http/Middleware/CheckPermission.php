<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ], 401);
        }

        // Check if user has the required permission (using sanctum guard)
        if (!$user->hasPermissionTo($permission, 'sanctum')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "You don't have permission to: {$permission}",
                'required_permission' => $permission,
                'user_permissions' => $this->getUserPermissions($user)
            ], 403);
        }

        return $next($request);
    }

    /**
     * Get user's effective permissions for response.
     */
    private function getUserPermissions($user): array
    {
        return $user->getAllPermissions()->pluck('name')->toArray();
    }
}
