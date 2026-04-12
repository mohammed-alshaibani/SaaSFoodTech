<?php

namespace App\Http\Controllers;

use App\Models\UserSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProviderController extends Controller
{
    /**
     * GET /provider/subscriptions
     * List subscriptions for the provider's customers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $provider = $request->user();

            // Get all subscriptions (simplified - in production, filter by provider's customers)
            $subscriptions = UserSubscription::with(['user', 'subscriptionPlan'])
                ->latest()
                ->get()
                ->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'customer_name' => $sub->user->name ?? 'Unknown',
                        'customer_email' => $sub->user->email ?? '',
                        'customer_id' => $sub->user_id,
                        'plan_id' => $sub->subscription_plan_id,
                        'plan_name' => $sub->subscriptionPlan?->name ?? 'Unknown',
                        'status' => $sub->status,
                        'starts_at' => $sub->starts_at,
                        'ends_at' => $sub->ends_at,
                        'amount' => $sub->subscriptionPlan?->price ?? 0,
                    ];
                });

            return response()->json([
                'data' => $subscriptions
            ]);
        } catch (\Exception $e) {
            Log::error('[ProviderController] Subscriptions error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load subscriptions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * GET /provider/stats
     * Get provider statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $provider = $request->user();

            // Get all subscriptions (simplified - in production, filter by provider's customers)
            $subscriptions = UserSubscription::with('subscriptionPlan')->get();

            $activeSubscriptions = $subscriptions->where('status', 'active')->count();
            $pendingSubscriptions = $subscriptions->where('status', 'pending')->count();

            // Calculate monthly revenue from subscription plans
            $monthlyRevenue = $subscriptions
                ->where('status', 'active')
                ->sum(function ($sub) {
                    return $sub->subscriptionPlan?->price ?? 0;
                });

            return response()->json([
                'data' => [
                    'total_customers' => $subscriptions->pluck('user_id')->unique()->count(),
                    'active_subscriptions' => $activeSubscriptions,
                    'pending_subscriptions' => $pendingSubscriptions,
                    'monthly_revenue' => $monthlyRevenue,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('[ProviderController] Stats error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to load stats',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * POST /provider/subscriptions
     * Create a new subscription
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'required|exists:users,id',
                'plan_id' => 'required|exists:subscription_plans,id',
                'status' => 'nullable|in:pending,active,expired,cancelled',
            ]);

            $subscription = UserSubscription::create([
                'user_id' => $validated['customer_id'],
                'subscription_plan_id' => $validated['plan_id'],
                'status' => $validated['status'] ?? 'pending',
                'starts_at' => now(),
            ]);

            return response()->json([
                'message' => 'Subscription created successfully',
                'data' => $subscription->fresh(['user', 'subscriptionPlan'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('[ProviderController] Create subscription error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * PUT/PATCH /provider/subscriptions/{id}
     * Update a subscription
     */
    public function update(Request $request, UserSubscription $subscription): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|in:pending,active,expired,cancelled',
                'plan_id' => 'nullable|exists:subscription_plans,id',
            ]);

            $updateData = [];
            if (isset($validated['status']))
                $updateData['status'] = $validated['status'];
            if (isset($validated['plan_id']))
                $updateData['subscription_plan_id'] = $validated['plan_id'];

            if (!empty($updateData)) {
                $subscription->update($updateData);
            }

            return response()->json([
                'message' => 'Subscription updated successfully',
                'data' => $subscription->fresh(['user', 'subscriptionPlan'])
            ]);
        } catch (\Exception $e) {
            Log::error('[ProviderController] Update subscription error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * DELETE /provider/subscriptions/{id}
     * Delete a subscription
     */
    public function destroy(UserSubscription $subscription): JsonResponse
    {
        try {
            $subscription->delete();
            return response()->json([
                'message' => 'Subscription deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('[ProviderController] Delete subscription error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete subscription',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
