<?php

namespace App\Listeners;

use App\Events\ServiceRequestCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ServiceRequestCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ServiceRequestCompleted $event): void
    {
        Log::info('Service request completed', [
            'request_id' => $event->serviceRequest->id,
            'provider_id' => $event->serviceRequest->provider_id,
            'customer_id' => $event->serviceRequest->customer_id,
            'completed_at' => now(),
        ]);

        // Here you could add:
        // - Send completion notification to customer
        // - Request review/feedback
        // - Update provider statistics
        // - Process payments
        // - Update subscription usage
    }
}
