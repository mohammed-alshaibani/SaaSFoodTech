<?php

namespace App\Observers;

use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;

class UserSubscriptionObserver
{
    /**
     * Handle the UserSubscription "created" event.
     */
    public function created(UserSubscription $userSubscription): void
    {
        if ($userSubscription->status === 'active') {
             $this->handleActivation($userSubscription);
        }
    }

    /**
     * Handle the UserSubscription "updated" event.
     */
    public function updated(UserSubscription $userSubscription): void
    {
        // If status changed to active, handle activation
        if ($userSubscription->isDirty('status') && $userSubscription->status === 'active') {
            $this->handleActivation($userSubscription);
        }
    }

    /**
     * Logic to perform when a subscription is activated.
     */
    protected function handleActivation(UserSubscription $userSubscription): void
    {
        $user = $userSubscription->user;
        $plan = $userSubscription->subscriptionPlan;

        Log::info("Processing subscription activation for user {$user->id}", [
            'plan' => $plan->name,
            'subscription_id' => $userSubscription->id,
        ]);

        // 1. Update user's legacy plan field (for backward compatibility with existing middlewares)
        $user->update(['plan' => $plan->name]);

        // 2. Perform RBAC updates
        // Grant premium-specific permissions directly for instant access
        if ($plan->name === 'premium' || $plan->name === 'enterprise') {
            $user->grantPermission('ai.enhance.description', 'Subscription Upgrade: ' . $plan->name);
            $user->grantPermission('ai.suggest.pricing', 'Subscription Upgrade: ' . $plan->name);
            $user->grantPermission('unlimited_requests', 'Subscription Upgrade: ' . $plan->name);
        }

        if ($plan->name === 'enterprise') {
            $user->grantPermission('analytics.view', 'Enterprise Subscription Activation');
            $user->grantPermission('analytics.export', 'Enterprise Subscription Activation');
        }

        // 3. Log the history record
        Log::info('Subscription RBAC refreshed', [
            'user_id' => $user->id,
            'permissions_granted' => ($plan->name === 'premium' || $plan->name === 'enterprise') ? ['ai.enhance', 'suggest.pricing'] : []
        ]);
        
        // 4. Future-ready: Trigger Email/Dashboard Notification
        // $user->notify(new \App\Notifications\SubscriptionActivated($plan));
    }
}
