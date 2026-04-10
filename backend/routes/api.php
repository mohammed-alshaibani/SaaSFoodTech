<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ProviderController;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;

// ═══════════════════════════════════════════════════════════
// Apply security middleware to all API routes
Route::middleware('api-security')->group(function () {

    // ═══════════════════════════════════════════════════════════
    // Public routes — no auth required but rate limited
    // ═══════════════════════════════════════════════════════════
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // ═══════════════════════════════════════════════════════════
    // Protected routes — require valid authentication token
    // ═══════════════════════════════════════════════════════════
    Route::middleware('api.auth:sanctum')->group(function () {

        // ── Auth ────────────────────────────────────────────────
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── Service Requests ────────────────────────────────────
        // Geolocation: Nearby requests endpoint — MUST be before {serviceRequest} to avoid binding conflict
        Route::get('/requests/nearby', [ServiceRequestController::class, 'nearby'])
            ->middleware('check.permission:request.view_nearby');

        // Index & show: role scoping is handled in controller + Policy
        Route::get('/requests', [ServiceRequestController::class, 'index']);
        Route::get('/requests/{serviceRequest}', [ServiceRequestController::class, 'show']);

        // Create: customers only + subscription limit gate
        Route::post('/requests', [ServiceRequestController::class, 'store'])
            ->middleware('check.limit');

        // Provider actions: enforced by Policy
        Route::patch('/requests/{serviceRequest}/accept', [ServiceRequestController::class, 'accept']);
        Route::patch('/requests/{serviceRequest}/complete', [ServiceRequestController::class, 'complete']);

        // throttle:10,1 = max 10 requests per 1 minute per user (per Architecture spec)
        Route::post('/ai/enhance', [AIController::class, 'enhance'])
            ->middleware('throttle:10,1');
        Route::post('/ai/categorize', [AIController::class, 'categorize'])
            ->middleware('throttle:10,1');
        Route::post('/ai/suggest-pricing', [AIController::class, 'suggestPricing'])
            ->middleware('throttle:10,1');

        // ── Subscription Management ─────────────────────────────────────
        Route::prefix('subscription')->group(function () {
            Route::get('/plans', [SubscriptionController::class, 'plans']);
            Route::get('/usage', [SubscriptionController::class, 'usage']);
            Route::post('/upgrade', [SubscriptionController::class, 'upgrade']);
            // Simulation route (public)
            Route::get('/simulate-success', [SubscriptionController::class, 'simulateSuccess'])
                ->withoutMiddleware('api.auth')
                ->name('subscription.simulate.success');
        });

        // ── Advanced RBAC Routes ────────────────────────────────────
        // Permission Management Routes
        Route::prefix('permissions')->group(function () {
            // View permissions (requires permission.view)
            Route::get('/', [PermissionController::class, 'index'])
                ->middleware('check.permission:permission.view');
            Route::get('/{permission}', [PermissionController::class, 'show'])
                ->middleware('check.permission:permission.view');

            // Create permissions (requires permission.create)
            Route::post('/', [PermissionController::class, 'store'])
                ->middleware('check.permission:permission.create');

            // Update permissions (requires permission.update)
            Route::put('/{permission}', [PermissionController::class, 'update'])
                ->middleware('check.permission:permission.update');

            // Delete permissions (requires permission.delete)
            Route::delete('/{permission}', [PermissionController::class, 'destroy'])
                ->middleware('check.permission:permission.delete');

            // Permission categories
            Route::get('/categories', [PermissionController::class, 'categories'])
                ->middleware('check.permission:permission.view');
            Route::post('/categories', [PermissionController::class, 'storeCategory'])
                ->middleware('check.permission:permission.create');

        });

        // Role Management Routes
        Route::prefix('roles')->group(function () {
            // View roles (requires role.view)
            Route::get('/', [RoleController::class, 'index'])
                ->middleware('check.permission:role.view');
            Route::get('/hierarchy', [RoleController::class, 'hierarchy'])
                ->middleware('check.permission:role.view');
            Route::get('/{role}', [RoleController::class, 'show'])
                ->middleware('check.permission:role.view');

            // Create roles (requires role.create)
            Route::post('/', [RoleController::class, 'store'])
                ->middleware('check.permission:role.create');

            // Update roles (requires role.update)
            Route::put('/{role}', [RoleController::class, 'update'])
                ->middleware('check.permission:role.update');

            // Delete roles (requires role.delete)
            Route::delete('/{role}', [RoleController::class, 'destroy'])
                ->middleware('check.permission:role.delete');

            // Permission management for roles
            Route::post('/{role}/permissions/grant', [RoleController::class, 'grantPermission'])
                ->middleware('check.permission:role.update');
            Route::post('/{role}/permissions/revoke', [RoleController::class, 'revokePermission'])
                ->middleware('check.permission:role.update');

            // Role hierarchy management
            Route::post('/{role}/hierarchy/add', [RoleController::class, 'addChildRole'])
                ->middleware('check.permission:role.hierarchy.manage');
            Route::delete('/{role}/hierarchy/remove', [RoleController::class, 'removeChildRole'])
                ->middleware('check.permission:role.hierarchy.manage');

        });

        // User Permission Management Routes
        Route::prefix('users/{user}/permissions')->group(function () {
            // Grant direct permission to user
            Route::post('/', [AdminController::class, 'grantDirectPermission'])
                ->middleware('check.permission:permission.assign');

            // Revoke direct permission from user
            Route::delete('/{permission}', [AdminController::class, 'revokeDirectPermission'])
                ->middleware('check.permission:permission.assign');

            // Get user's effective permissions
            Route::get('/', [AdminController::class, 'getUserPermissions'])
                ->middleware('check.permission:user.manage');
        });

        // ── Admin Routes ────────────────────────────────────────
        // All admin routes require 'admin' role (coarse gate at middleware level)
        // Fine-grained checks (permission.assign, user.manage) are inside AdminController
        Route::prefix('admin')->middleware('role:admin')->group(function () {

            // User management
            Route::get('/users', [AdminController::class, 'users']);
            Route::get('/users/{user}', [AdminController::class, 'showUser']);
            Route::put('/users/{user}', [AdminController::class, 'updateUser']);
            Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
            Route::patch('/users/{user}/plan', [AdminController::class, 'updatePlan']);

            // Dynamic permission assignment
            Route::post('/users/{user}/permissions', [AdminController::class, 'syncPermissions']);
            Route::delete('/users/{user}/permissions', [AdminController::class, 'revokeAllPermissions']);

            // Subscription approvals
            Route::get('/subscriptions/pending', [AdminController::class, 'pendingSubscriptions']);
            Route::post('/subscriptions/{subscription}/accept', [AdminController::class, 'acceptSubscription']);

            // Plans management
            Route::get('/plans', [AdminController::class, 'plans']);
            Route::post('/plans', [AdminController::class, 'createPlan']);
            Route::patch('/plans/{plan}', [AdminController::class, 'updatePlanDetails']);
            Route::delete('/plans/{plan}', [AdminController::class, 'deletePlan']);

            // Available permissions list (for frontend dropdown)
            Route::get('/permissions', [AdminController::class, 'permissions']);
            Route::get('/stats', [AdminController::class, 'stats']);
        });

        // ── Provider Routes ────────────────────────────────────────
        // Provider routes require provider role (provider_admin or provider_employee)
        Route::prefix('provider')->middleware('role:provider_admin,provider_employee')->group(function () {
            // Subscription management
            Route::get('/subscriptions', [ProviderController::class, 'index']);
            Route::get('/stats', [ProviderController::class, 'stats']);
            Route::post('/subscriptions', [ProviderController::class, 'store']);
            Route::put('/subscriptions/{subscription}', [ProviderController::class, 'update']);
            Route::patch('/subscriptions/{subscription}', [ProviderController::class, 'update']);
            Route::delete('/subscriptions/{subscription}', [ProviderController::class, 'destroy']);
        });
    });

});
