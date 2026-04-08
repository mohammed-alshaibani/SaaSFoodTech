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

        // Check if user has the required permission
        if (!$user->hasPermissionTo($permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "You don't have permission to: {$permission}",
                'required_permission' => $permission,
                'user_permissions' => $this->getUserPermissions($user)
            ], 403);
        }

        // Check scoped permissions if applicable
        if ($this->isScopedPermission($permission)) {
            if (!$this->checkScopedPermission($request, $user, $permission)) {
                return response()->json([
                    'error' => 'Forbidden',
                    'message' => "Permission '{$permission}' is scoped and you don't have access to this resource"
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Get user's effective permissions for response.
     */
    private function getUserPermissions($user): array
    {
        return $user->getAllEffectivePermissions()->pluck('name')->toArray();
    }

    /**
     * Check if permission is scoped.
     */
    private function isScopedPermission(string $permission): bool
    {
        $permissionModel = \App\Models\Permission::where('name', $permission)->first();
        return $permissionModel && $permissionModel->isScoped();
    }

    /**
     * Check scoped permission against request context.
     */
    private function checkScopedPermission(Request $request, $user, string $permission): bool
    {
        $permissionModel = \App\Models\Permission::where('name', $permission)->first();
        
        if (!$permissionModel || !$permissionModel->isScoped()) {
            return true;
        }

        foreach ($permissionModel->scopes as $scope) {
            if (!$this->checkScope($request, $user, $scope)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check individual scope.
     */
    private function checkScope(Request $request, $user, $scope): bool
    {
        switch ($scope->scope_type) {
            case 'location':
                return $this->checkLocationScope($request, $user, $scope);
            case 'department':
                return $this->checkDepartmentScope($request, $user, $scope);
            case 'team':
                return $this->checkTeamScope($request, $user, $scope);
            case 'self':
                return $this->checkSelfScope($request, $user);
            default:
                return true;
        }
    }

    /**
     * Check location-based scope.
     */
    private function checkLocationScope(Request $request, $user, $scope): bool
    {
        // For service requests, check if user is in the same location
        if ($request->route('service_request')) {
            $serviceRequest = $request->route('service_request');
            $distance = $this->calculateDistance(
                $user->latitude, 
                $user->longitude,
                $serviceRequest->latitude, 
                $serviceRequest->longitude
            );
            
            return $distance <= ($scope->scope_values['max_distance'] ?? 50); // Default 50km
        }

        return true;
    }

    /**
     * Check department-based scope.
     */
    private function checkDepartmentScope(Request $request, $user, $scope): bool
    {
        // Implementation depends on your department structure
        // This is a placeholder for department-based permission checking
        return in_array($user->department ?? null, $scope->scope_values);
    }

    /**
     * Check team-based scope.
     */
    private function checkTeamScope(Request $request, $user, $scope): bool
    {
        // Implementation depends on your team structure
        // This is a placeholder for team-based permission checking
        return in_array($user->team_id ?? null, $scope->scope_values);
    }

    /**
     * Check self-only scope (user can only access their own resources).
     */
    private function checkSelfScope(Request $request, $user): bool
    {
        $resourceId = $request->route('id') ?? $request->route('user');
        
        if ($resourceId) {
            return (int) $resourceId === $user->id;
        }

        // For service requests, check if user is the customer or provider
        if ($request->route('service_request')) {
            $serviceRequest = $request->route('service_request');
            return $serviceRequest->customer_id === $user->id || 
                   $serviceRequest->provider_id === $user->id;
        }

        return true;
    }

    /**
     * Calculate distance between two coordinates.
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
