<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpgradePlanRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * POST /api/subscription/upgrade
     * Upgrade user's subscription plan.
     */
    public function upgrade(UpgradePlanRequest $request): JsonResponse
    {
        $user = $request->user();
        
        // Validate current plan (prevent downgrades)
        if ($user->plan === $request->plan) {
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

        $currentLevel = $planHierarchy[$user->plan] ?? 0;
        $newLevel = $planHierarchy[$request->plan] ?? 0;

        if ($newLevel <= $currentLevel) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot downgrade to a lower or equal plan.',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], 400);
        }

        // Process payment (placeholder for real integration)
        $paymentProcessed = $this->processPayment($request);

        if (!$paymentProcessed) {
            return response()->json([
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
                'request_id' => $request->header('X-Request-ID'),
                'timestamp' => now()->toISOString(),
            ], 402);
        }

        // Update user plan
        $user->update(['plan' => $request->plan]);

        // Log the upgrade
        Log::info('User upgraded plan', [
            'user_id' => $user->id,
            'from_plan' => $user->getOriginal('plan'),
            'to_plan' => $request->plan,
            'payment_method' => $request->payment_method,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan upgraded successfully.',
            'data' => [
                'user' => new UserResource($user),
                'new_plan' => $request->plan,
                'previous_plan' => $user->getOriginal('plan'),
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
        $plans = [
            [
                'name' => 'free',
                'price' => 0,
                'features' => [
                    'max_requests_per_month' => 3,
                    'basic_support' => false,
                    'priority_support' => false,
                ],
                'limits' => [
                    'requests_per_month' => 3,
                    'attachments_per_request' => 5,
                ],
            ],
            [
                'name' => 'basic',
                'price' => 29.99,
                'features' => [
                    'max_requests_per_month' => 50,
                    'basic_support' => true,
                    'priority_support' => false,
                ],
                'limits' => [
                    'requests_per_month' => 50,
                    'attachments_per_request' => 10,
                ],
            ],
            [
                'name' => 'premium',
                'price' => 79.99,
                'features' => [
                    'max_requests_per_month' => 200,
                    'basic_support' => true,
                    'priority_support' => true,
                    'ai_enhancement' => true,
                ],
                'limits' => [
                    'requests_per_month' => 200,
                    'attachments_per_request' => 20,
                ],
            ],
            [
                'name' => 'enterprise',
                'price' => 199.99,
                'features' => [
                    'max_requests_per_month' => 'unlimited',
                    'basic_support' => true,
                    'priority_support' => true,
                    'ai_enhancement' => true,
                    'api_access' => true,
                    'custom_integrations' => true,
                ],
                'limits' => [
                    'requests_per_month' => 'unlimited',
                    'attachments_per_request' => 50,
                ],
            ],
        ];

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

        // Get current month's request count
        $currentMonth = now()->format('Y-m');
        $requestCount = \App\Models\ServiceRequest::where('customer_id', $user->id)
            ->whereMonth('created_at', $currentMonth)
            ->count();

        // Get plan limits
        $planLimits = $this->getPlanLimits($user->plan);

        return response()->json([
            'success' => true,
            'data' => [
                'current_plan' => $user->plan,
                'current_month' => $currentMonth,
                'requests_used' => $requestCount,
                'requests_limit' => $planLimits['requests_per_month'],
                'limit_reached' => $user->plan !== 'free' && $requestCount >= $planLimits['requests_per_month'],
                'percentage_used' => $planLimits['requests_per_month'] === 'unlimited' 
                    ? 0 
                    : round(($requestCount / $planLimits['requests_per_month']) * 100, 2),
                'reset_date' => now()->endOfMonth()->format('Y-m-d'),
            ],
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Process payment placeholder for real integration.
     */
    private function processPayment(UpgradePlanRequest $request): bool
    {
        // Placeholder for real payment integration (Stripe, PayPal, etc.)
        // In production, this would integrate with actual payment gateway
        
        Log::info('Processing payment', [
            'payment_method' => $request->payment_method,
            'plan' => $request->plan,
            'amount' => $this->getPlanPrice($request->plan),
        ]);

        // Simulate payment processing (90% success rate for demo)
        return rand(1, 10) <= 9;
    }

    /**
     * Get plan limits based on plan name.
     */
    private function getPlanLimits(string $plan): array
    {
        $limits = [
            'free' => [
                'requests_per_month' => 3,
                'attachments_per_request' => 5,
            ],
            'basic' => [
                'requests_per_month' => 50,
                'attachments_per_request' => 10,
            ],
            'premium' => [
                'requests_per_month' => 200,
                'attachments_per_request' => 20,
            ],
            'enterprise' => [
                'requests_per_month' => 'unlimited',
                'attachments_per_request' => 50,
            ],
        ];

        return $limits[$plan] ?? $limits['free'];
    }

    /**
     * Get plan price based on plan name.
     */
    private function getPlanPrice(string $plan): float
    {
        $prices = [
            'free' => 0,
            'basic' => 29.99,
            'premium' => 79.99,
            'enterprise' => 199.99,
        ];

        return $prices[$plan] ?? 0;
    }
}
