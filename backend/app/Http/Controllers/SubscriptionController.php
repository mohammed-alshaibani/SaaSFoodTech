<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpgradePlanRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * POST /api/subscription/upgrade
     * Upgrade user's subscription plan.
     */
    public function upgrade(UpgradePlanRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Find the requested plan
        $newPlan = SubscriptionPlan::where('name', $request->plan)->first();
        
        if (!$newPlan) {
            return response()->json([
                'success' => false,
                'error' => 'Requested plan not found.',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], 404);
        }

        // Get current plan
        $currentSubscription = $user->activeSubscription();
        $currentPlan = $currentSubscription?->subscriptionPlan;
        $currentPlanName = $currentPlan?->name ?? $user->plan ?? 'free';

        // Validate current plan (prevent downgrades and same plan)
        if ($currentPlanName === $newPlan->name) {
            return response()->json([
                'success' => false,
                'error' => 'You are already on this plan.',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], 400);
        }

        // Plan hierarchy validation
        $planHierarchy = [
            'free' => 0,
            'basic' => 1,
            'premium' => 2,
            'enterprise' => 3,
        ];

        $currentLevel = $planHierarchy[$currentPlanName] ?? 0;
        $newLevel = $planHierarchy[$newPlan->name] ?? 0;

        if ($newLevel <= $currentLevel) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot downgrade to a lower or equal plan.',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], 400);
        }

        // Process payment
        $paymentProcessed = $this->paymentService->processPayment($request);

        if (!$paymentProcessed) {
            return response()->json([
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], 402);
        }

        // Create new subscription
        $subscription = $user->subscribeTo($newPlan, [
            'metadata' => [
                'payment_method' => $request->payment_method,
                'upgraded_from' => $currentPlanName,
            ],
        ]);

        // Update legacy plan field for backward compatibility
        $user->update(['plan' => $newPlan->name]);

        // Log the upgrade
        Log::info('User upgraded plan', [
            'user_id' => $user->id,
            'from_plan' => $currentPlanName,
            'to_plan' => $newPlan->name,
            'subscription_id' => $subscription->id,
            'payment_method' => $request->payment_method,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan upgraded successfully.',
            'data' => [
                'user' => new UserResource($user),
                'subscription' => $subscription->load('subscriptionPlan'),
                'new_plan' => $newPlan->name,
                'previous_plan' => $currentPlanName,
            ],
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * GET /api/subscription/plans
     * Get available subscription plans.
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()
            ->ordered()
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'display_name' => $plan->display_name,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'formatted_price' => $plan->formatted_price,
                    'billing_cycle' => $plan->billing_cycle,
                    'features' => $plan->features,
                    'limits' => $plan->limits,
                    'sort_order' => $plan->sort_order,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $plans,
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * GET /api/subscription/usage
     * Get current user's subscription usage.
     */
    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['roles', 'permissions', 'activeSubscription.subscriptionPlan']);

        // Get usage data from user model
        $usage = $user->getCurrentMonthUsage();
        $currentPlan = $user->getCurrentPlan();
        $activeSubscription = $user->activeSubscription();

        return response()->json([
            'success' => true,
            'data' => [
                'current_plan' => $currentPlan,
                'current_month' => now()->format('Y-m'),
                'requests_used' => $usage['used'],
                'requests_limit' => $usage['limit'],
                'requests_remaining' => $usage['remaining'],
                'limit_reached' => $user->hasExceededRequestLimit(),
                'percentage_used' => $usage['percentage'],
                'reset_date' => now()->endOfMonth()->format('Y-m-d'),
                'subscription' => $activeSubscription ? [
                    'id' => $activeSubscription->id,
                    'status' => $activeSubscription->status,
                    'starts_at' => $activeSubscription->starts_at,
                    'ends_at' => $activeSubscription->ends_at,
                    'is_in_trial' => $activeSubscription->isInTrial(),
                    'days_remaining' => $activeSubscription->getDaysRemaining(),
                    'plan' => $activeSubscription->subscriptionPlan,
                ] : null,
            ],
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
