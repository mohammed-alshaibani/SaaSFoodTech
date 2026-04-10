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
            
            // Get subscriptions where the customer has this provider as their assigned provider
            // This is a simplified implementation - adjust based on your actual data model
            $subscriptions = UserSubscription::with(['user', 'subscriptionPlan'])
                ->whereHas('user', function ($query) use ($provider) {
                    // Assuming there's a relationship between customers and providers
                    // This might need adjustment based on your actual schema
                    $query->where('provider_id', $provider->id);
                })
                ->latest()
                ->get();

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
            
            // Get customers assigned to this provider
            $totalCustomers = User::where('provider_id', $provider->id)->count();
            
            // Get subscriptions
            $subscriptions = UserSubscription::whereHas('user', function ($query) use ($provider) {
                $query->where('provider_id', $provider->id);
            })->get();
            
            $activeSubscriptions = $subscriptions->where('status', 'active')->count();
            $pendingSubscriptions = $subscriptions->where('status', 'pending')->count();
            
            // Calculate monthly revenue (simplified)
            $monthlyRevenue = $subscriptions
                ->where('status', 'active')
                ->sum(function ($sub) {
                    return $sub->subscriptionPlan->price ?? 0;
                });

            return response()->json([
                'data' => [
                    'total_customers' => $totalCustomers,
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
                'amount' => 'required|numeric|min:0',
                'status' => 'nullable|in:pending,active,expired,cancelled',
            ]);

            $subscription = UserSubscription::create([
                'user_id' => $validated['customer_id'],
                'subscription_plan_id' => $validated['plan_id'],
                'status' => $validated['status'] ?? 'pending',
                'starts_at' => now(),
                'amount' => $validated['amount'],
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
                'amount' => 'nullable|numeric|min:0',
                'plan_id' => 'nullable|exists:subscription_plans,id',
            ]);

            $updateData = [];
            if (isset($validated['status'])) $updateData['status'] = $validated['status'];
            if (isset($validated['amount'])) $updateData['amount'] = $validated['amount'];
            if (isset($validated['plan_id'])) $updateData['subscription_plan_id'] = $validated['plan_id'];

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
