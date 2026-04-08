<?php

namespace App\Listeners;

use App\Events\ServiceRequestCreated;
use App\Jobs\SendNotificationJob;
use App\Jobs\UpdateStatisticsJob;
use App\Jobs\LogActivityJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ServiceRequestCreatedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(ServiceRequestCreated $event): void
    {
        Log::info('Service request created', [
            'service_request_id' => $event->serviceRequest->id,
            'customer_id' => $event->serviceRequest->customer_id,
            'title' => $event->serviceRequest->title,
        ]);

        // Send notification to nearby providers
        SendNotificationJob::dispatch(
            'providers.nearby',
            'New Service Request Available',
            "A new service request \"{$event->serviceRequest->title}\" is available in your area.",
            [
                'service_request_id' => $event->serviceRequest->id,
                'latitude' => $event->serviceRequest->latitude,
                'longitude' => $event->serviceRequest->longitude,
                'category' => $event->metadata['category'] ?? null,
            ]
        );

        // Send confirmation to customer
        SendNotificationJob::dispatch(
            'customer.' . $event->serviceRequest->customer_id,
            'Service Request Created',
            'Your service request has been successfully created and is now visible to providers.',
            [
                'service_request_id' => $event->serviceRequest->id,
                'title' => $event->serviceRequest->title,
            ]
        );

        // Update platform statistics
        UpdateStatisticsJob::dispatch([
            'type' => 'service_request_created',
            'data' => [
                'customer_id' => $event->serviceRequest->customer_id,
                'category' => $event->metadata['category'] ?? null,
                'urgency' => $event->metadata['urgency'] ?? null,
            ]
        ]);

        // Log activity for audit trail
        LogActivityJob::dispatch([
            'action' => 'service_request_created',
            'user_id' => $event->serviceRequest->customer_id,
            'target_id' => $event->serviceRequest->id,
            'target_type' => 'service_request',
            'metadata' => $event->metadata,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(ServiceRequestCreated $event, \Throwable $exception): void
    {
        Log::error('ServiceRequestCreatedListener failed', [
            'service_request_id' => $event->serviceRequest->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
