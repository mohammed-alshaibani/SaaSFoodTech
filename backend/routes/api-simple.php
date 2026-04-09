<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceRequestControllerSimple;
use App\Http\Controllers\AIControllerSimple;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;

// API Documentation (outside security middleware)
Route::get('/docs/api-docs.yaml', function () {
    $yamlPath = storage_path('api-docs/api-docs.yaml');
    if (!file_exists($yamlPath)) {
        $yamlPath = storage_path('api-docs.yaml');
    }
    
    if (!file_exists($yamlPath)) {
        return response()->json(['error' => 'API documentation not found'], 404);
    }

    $content = file_get_contents($yamlPath);
    return response($content, 200, [
        'Content-Type' => 'application/yaml',
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Origin, Content-Type, Authorization'
    ]);
});

Route::get('/docs/api-docs.json', function () {
    $yamlPath = storage_path('api-docs/api-docs.yaml');
    if (!file_exists($yamlPath)) {
        $yamlPath = storage_path('api-docs.yaml');
    }
    
    if (!file_exists($yamlPath)) {
        return response()->json(['error' => 'API documentation not found'], 404);
    }

    $content = file_get_contents($yamlPath);
    return response($content, 200, [
        'Content-Type' => 'application/json',
        'Access-Control-Allow-Origin' => '*'
    ]);
});

// Apply security middleware to all API routes
Route::middleware('api-security')->group(function () {

    // Public routes - no auth required
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Protected routes - require Sanctum token
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Service Requests (Simple Controller)
        Route::get('/requests/nearby', [ServiceRequestControllerSimple::class, 'nearby'])
            ->middleware('check.permission:request.view_nearby');

        Route::get('/requests', [ServiceRequestControllerSimple::class, 'index']);
        Route::get('/requests/{serviceRequest}', [ServiceRequestControllerSimple::class, 'show']);
        Route::post('/requests', [ServiceRequestControllerSimple::class, 'store'])
            ->middleware('check.limit');
        Route::patch('/requests/{serviceRequest}/accept', [ServiceRequestControllerSimple::class, 'accept']);
        Route::patch('/requests/{serviceRequest}/complete', [ServiceRequestControllerSimple::class, 'complete']);

        // AI Features (Simple Controller)
        Route::post('/ai/enhance', [AIControllerSimple::class, 'enhance'])
            ->middleware('throttle:10,1');
        Route::post('/ai/categorize', [AIControllerSimple::class, 'categorize'])
            ->middleware('throttle:10,1');
        Route::post('/ai/suggest-pricing', [AIControllerSimple::class, 'suggestPricing'])
            ->middleware('throttle:10,1');

        // Subscription Management
        Route::prefix('subscription')->group(function () {
            Route::get('/plans', [SubscriptionController::class, 'plans']);
            Route::get('/usage', [SubscriptionController::class, 'usage']);
            Route::post('/upgrade', [SubscriptionController::class, 'upgrade']);
        });

        // Advanced RBAC Routes
        Route::prefix('permissions')->group(function () {
            Route::get('/', [PermissionController::class, 'index'])
                ->middleware('check.permission:permission.view');
            Route::get('/{permission}', [PermissionController::class, 'show'])
                ->middleware('check.permission:permission.view');
            Route::post('/', [PermissionController::class, 'store'])
                ->middleware('check.permission:permission.create');
            Route::put('/{permission}', [PermissionController::class, 'update'])
                ->middleware('check.permission:permission.update');
            Route::delete('/{permission}', [PermissionController::class, 'destroy'])
                ->middleware('check.permission:permission.delete');
            Route::get('/categories', [PermissionController::class, 'categories'])
                ->middleware('check.permission:permission.view');
            Route::post('/categories', [PermissionController::class, 'storeCategory'])
                ->middleware('check.permission:permission.create');
            Route::get('/audit', [PermissionController::class, 'auditLogs'])
                ->middleware('check.permission:system.logs');
        });

        Route::prefix('roles')->group(function () {
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('check.permission:role.view');
            Route::get('/hierarchy', [RoleController::class, 'hierarchy'])
                ->middleware('check.permission:role.view');
            Route::get('/{role}', [RoleController::class, 'show'])
                ->middleware('check.permission:role.view');
            Route::post('/', [RoleController::class, 'store'])
                ->middleware('check.permission:role.create');
            Route::put('/{role}', [RoleController::class, 'update'])
                ->middleware('check.permission:role.update');
            Route::delete('/{role}', [RoleController::class, 'destroy'])
                ->middleware('check.permission:role.delete');
            Route::post('/{role}/permissions/grant', [RoleController::class, 'grantPermission'])
                ->middleware('check.permission:role.update');
            Route::post('/{role}/permissions/revoke', [RoleController::class, 'revokePermission'])
                ->middleware('check.permission:role.update');
            Route::post('/{role}/hierarchy/add', [RoleController::class, 'addChildRole'])
                ->middleware('check.permission:role.hierarchy.manage');
            Route::delete('/{role}/hierarchy/remove', [RoleController::class, 'removeChildRole'])
                ->middleware('check.permission:role.hierarchy.manage');
            Route::get('/audit', [RoleController::class, 'auditLogs'])
                ->middleware('check.permission:system.logs');
        });

        Route::prefix('users/{user}/permissions')->group(function () {
            Route::post('/', [AdminController::class, 'grantDirectPermission'])
                ->middleware('check.permission:user.grant.permissions');
            Route::delete('/{permission}', [AdminController::class, 'revokeDirectPermission'])
                ->middleware('check.permission:user.grant.permissions');
            Route::get('/', [AdminController::class, 'getUserPermissions'])
                ->middleware('check.permission:user.view.any');
        });

        // Admin Routes
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/users/{user}', [AdminController::class, 'showUser']);
            Route::patch('/users/{user}/plan', [AdminController::class, 'updatePlan']);
            Route::post('/users/{user}/permissions', [AdminController::class, 'syncPermissions']);
            Route::delete('/users/{user}/permissions', [AdminController::class, 'revokeAllPermissions']);
            Route::get('/permissions', [AdminController::class, 'permissions']);
            Route::get('/stats', [AdminController::class, 'stats']);
        });
    });

    // Monitoring endpoints
    Route::prefix('monitoring')->group(function () {
        Route::get('/dashboard', [MonitoringController::class, 'dashboard']);
        Route::get('/health', [MonitoringController::class, 'health']);
        Route::get('/metrics', [MonitoringController::class, 'metrics']);
        Route::get('/metrics/all', [MonitoringController::class, 'allMetrics']);
        Route::post('/metrics/record', [MonitoringController::class, 'recordMetric']);
        Route::post('/metrics/collect', [MonitoringController::class, 'collectSystemMetrics']);
        Route::post('/metrics/cleanup', [MonitoringController::class, 'cleanup']);
    });
});
