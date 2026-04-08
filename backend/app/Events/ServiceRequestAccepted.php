<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ServiceRequest $serviceRequest,
        public int $providerId,
        public array $metadata = []
    ) {
        $this->serviceRequest->load(['customer', 'provider']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('service-requests'),
            new PrivateChannel('customer.' . $this->serviceRequest->customer_id),
            new PrivateChannel('provider.' . $this->providerId),
            new PrivateChannel('user.' . $this->serviceRequest->customer_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'service.request.accepted';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->serviceRequest->id,
            'title' => $this->serviceRequest->title,
            'status' => $this->serviceRequest->status,
            'accepted_at' => $this->serviceRequest->updated_at->toISOString(),
            'provider' => $this->serviceRequest->provider ? [
                'id' => $this->serviceRequest->provider->id,
                'name' => $this->serviceRequest->provider->name,
            ] : null,
            'provider_notes' => $this->metadata['provider_notes'] ?? null,
            'estimated_completion' => $this->metadata['estimated_completion'] ?? null,
            'metadata' => $this->metadata,
        ];
    }
}
