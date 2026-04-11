<?php

namespace App\Events;

use App\Models\ServiceRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServiceRequestCompleted implements ShouldBroadcast
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
            new PrivateChannel('user.' . $this->serviceRequest->provider_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'service.request.completed';
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
            'completed_at' => $this->serviceRequest->updated_at?->toISOString(),
            'customer' => [
                'id' => $this->serviceRequest->customer->id,
                'name' => $this->serviceRequest->customer->name,
            ],
            'provider' => $this->serviceRequest->provider ? [
                'id' => $this->serviceRequest->provider->id,
                'name' => $this->serviceRequest->provider->name,
            ] : null,
            'completion_notes' => $this->metadata['completion_notes'] ?? null,
            'final_attachments' => $this->metadata['final_attachments'] ?? [],
            'rating' => $this->metadata['rating'] ?? null,
            'duration_minutes' => $this->calculateDuration(),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Calculate the duration from acceptance to completion.
     */
    protected function calculateDuration(): ?int
    {
        if (!$this->serviceRequest->updated_at) {
            return null;
        }

        // In a real implementation, you'd store the accepted_at timestamp
        // For now, we'll use a placeholder calculation
        return $this->serviceRequest->created_at?->diffInMinutes($this->serviceRequest->updated_at);
    }
}
