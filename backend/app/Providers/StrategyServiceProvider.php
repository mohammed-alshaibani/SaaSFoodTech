<?php

namespace App\Providers;

use App\Services\Payment\PaymentProcessorFactory;
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
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        // Initialize payment processors
        PaymentProcessorFactory::initialize();

        // Log initialization
        \Illuminate\Support\Facades\Log::info('Strategy patterns initialized', [
            'payment_processors' => array_keys(PaymentProcessorFactory::getAvailable()),
        ]);
    }
}
