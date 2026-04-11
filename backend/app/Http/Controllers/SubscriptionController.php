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
     * Create a pending subscription and notify admins for approval.
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
            ], 404);
        }

        // Get current plan info
        $currentPlanName = $user->getCurrentPlan();

        // Validate plan hierarchy (prevent same plan or downgrade)
        $planHierarchy = ['free' => 0, 'basic' => 1, 'premium' => 2, 'enterprise' => 3];
        $currentLevel = $planHierarchy[$currentPlanName] ?? 0;
        $newLevel = $planHierarchy[$newPlan->name] ?? 0;

        if ($newLevel <= $currentLevel) {
            return response()->json([
                'success' => false,
                'error' => 'You are already on this plan or cannot downgrade through this portal.',
            ], 400);
        }

        // 1. Create a PENDING subscription record
        $subscription = $user->subscriptions()->create([
            'subscription_plan_id' => $newPlan->id,
            'status' => 'pending',
            'starts_at' => now(),
            'metadata' => [
                'upgraded_from' => $currentPlanName,
            ],
        ]);

        // 2. Dispatch the event for Admin notification
        event(new \App\Events\PlanUpgradeRequested($user, $newPlan, $subscription));

        return response()->json([
            'success' => true,
            'message' => 'Upgrade request submitted successfully. Waiting for admin approval.',
            'data' => [
                'subscription_id' => $subscription->id,
                'status' => 'pending'
            ],
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
        $user->load(['roles', 'permissions']);

        // Get usage data from user model
        $usage = $user->getCurrentMonthUsage();
        $currentPlan = $user->getCurrentPlan();
        $activeSubscription = $user->activeSubscription();

        // Load subscription plan if subscription exists
        if ($activeSubscription) {
            $activeSubscription->load('subscriptionPlan');
        }

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

    /**
     * GET /api/subscription/simulate-success
     * Simulate a successful payment callback (Mock only).
     */
    public function simulateSuccess(Request $request): JsonResponse
    {
        $id = $request->query('subscription_id');
        $token = $request->query('auth_token');

        // Verify token (Mock security)
        $expectedToken = md5($id . config('app.key'));
        if ($token !== $expectedToken) {
            return response()->json(['success' => false, 'error' => 'Invalid simulation token.'], 403);
        }

        $subscription = UserSubscription::findOrFail($id);
        
        if ($subscription->status !== 'pending') {
            return response()->json(['success' => false, 'error' => 'Subscription is not in pending state.'], 400);
        }

        // Activate subscription
        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
        ]);

        // Sync legacy plan field
        $subscription->user->update(['plan' => $subscription->subscriptionPlan->name]);

        Log::info('Subscription activated via simulation', [
            'subscription_id' => $id,
            'user_id' => $subscription->user_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment simulated successfully. Plan upgraded.',
            'data' => [
                'plan' => $subscription->subscriptionPlan->name,
                'status' => 'active'
            ]
        ]);
    }
}
