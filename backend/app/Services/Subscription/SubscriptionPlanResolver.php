<?php

namespace App\Services\Subscription;

use App\Models\User;

class SubscriptionPlanResolver implements PlanResolverInterface
{
    public function resolve(User $user): string
    {
        $activeSubscription = $user->activeSubscription();
        
        if ($activeSubscription && $activeSubscription->subscriptionPlan) {
            return $activeSubscription->subscriptionPlan->name;
        }

        // Fallback to default plan
        return 'free';
    }

    public function canHandle(User $user): bool
    {
        // Check if user has active subscription
        return $user->activeSubscription() !== null;
    }

    public function getPriority(): int
    {
        return 100; // High priority - subscription-based resolution
    }

    public function getName(): string
    {
        return 'subscription';
    }
}
