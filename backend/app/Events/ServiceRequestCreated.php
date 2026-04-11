<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ServiceRequest $serviceRequest,
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
            new PrivateChannel('user.' . $this->serviceRequest->customer_id),
            new PrivateChannel('providers'),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'service.request.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->serviceRequest->id,
            'title' => $this->serviceRequest->title,
            'description' => $this->serviceRequest->description,
            'status' => $this->serviceRequest->status,
            'latitude' => $this->serviceRequest->latitude,
            'longitude' => $this->serviceRequest->longitude,
            'category' => $this->metadata['category'] ?? null,
            'urgency' => $this->metadata['urgency'] ?? null,
            'customer' => [
                'id' => $this->serviceRequest->customer->id,
                'name' => $this->serviceRequest->customer->name,
            ],
            'attachments_count' => is_array($this->serviceRequest->attachments) ? count($this->serviceRequest->attachments) : 0,
            'created_at' => $this->serviceRequest->created_at?->toISOString(),
            'metadata' => $this->metadata,
        ];
    }

}
