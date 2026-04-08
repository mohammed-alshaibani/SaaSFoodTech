<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\ServiceRequestCreated;
use App\Events\ServiceRequestAccepted;
use App\Events\ServiceRequestCompleted;
use App\Listeners\ServiceRequestCreatedListener;
use App\Listeners\ServiceRequestAcceptedListener;
use App\Listeners\ServiceRequestCompletedListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        
        ServiceRequestCreated::class => [
            ServiceRequestCreatedListener::class,
        ],
        
        ServiceRequestAccepted::class => [
            ServiceRequestAcceptedListener::class,
        ],
        
        ServiceRequestCompleted::class => [
            ServiceRequestCompletedListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
