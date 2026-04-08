<?php

namespace App\Providers;

use App\Services\Payment\PaymentProcessorFactory;
use App\Services\Subscription\PlanResolverManager;
use App\Services\Subscription\SubscriptionPlanResolver;
use App\Services\Subscription\LegacyPlanResolver;
use Illuminate\Support\ServiceProvider;

class StrategyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
        // Register PaymentProcessorFactory as singleton
        $this->app->singleton(PaymentProcessorFactory::class, function ($app) {
            return new PaymentProcessorFactory();
        });

        // Register plan resolvers
        $this->app->singleton(SubscriptionPlanResolver::class, function ($app) {
            return new SubscriptionPlanResolver();
        });

        $this->app->singleton(LegacyPlanResolver::class, function ($app) {
            return new LegacyPlanResolver();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Initialize payment processors
        PaymentProcessorFactory::initialize();

        // Initialize plan resolvers
        PlanResolverManager::initialize();

        // Log initialization
        \Illuminate\Support\Facades\Log::info('Strategy patterns initialized', [
            'payment_processors' => array_keys(PaymentProcessorFactory::getAvailable()),
            'plan_resolvers' => array_map(fn($r) => $r->getName(), PlanResolverManager::getResolvers()),
        ]);
    }
}
