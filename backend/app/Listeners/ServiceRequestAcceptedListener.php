<?php

namespace App\Listeners;

use App\Events\ServiceRequestAccepted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ServiceRequestAcceptedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ServiceRequestAccepted $event): void
    {
        Log::info('Service request accepted', [
            'request_id' => $event->serviceRequest->id,
            'provider_id' => $event->serviceRequest->provider_id,
            'customer_id' => $event->serviceRequest->customer_id,
            'accepted_at' => now(),
        ]);

        // Here you could add:
        // - Send notification to customer
        // - Update provider metrics
        // - Trigger webhooks
        // - Send email notifications
    }
}
