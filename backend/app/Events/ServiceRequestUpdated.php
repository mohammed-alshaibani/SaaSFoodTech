<?php

namespace App\Events;

use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic lifecycle event — fired on every status transition.
 * Implements ShouldBroadcastNow so updates arrive in real-time
 * without waiting for the queue worker.
 */
class ServiceRequestUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ServiceRequest $serviceRequest,
        public readonly string $action,         // 'accepted' | 'completed' | 'created'
        public readonly array $metadata = [],
    ) {
        $this->serviceRequest->loadMissing(['customer', 'provider']);
    }

    /**
     * Channels:
     *  - private global feed (admins)
     *  - per-customer channel (owner gets live status updates)
     *  - per-provider channel (assigned provider)
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('service-requests'),                                        // admin feed
            new PrivateChannel('user.' . $this->serviceRequest->customer_id),             // customer
        ];

        if ($this->serviceRequest->provider_id) {
            $channels[] = new PrivateChannel('user.' . $this->serviceRequest->provider_id); // provider
        }

        return $channels;
    }

    /** Event name seen by the JS client */
    public function broadcastAs(): string
    {
        return 'ServiceRequestUpdated';
    }

    /** Payload sent to the frontend */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'request' => (new ServiceRequestResource($this->serviceRequest))->toArray(request()),
            'metadata' => $this->metadata,
            'timestamp' => now()->toISOString(),
        ];
    }
}
