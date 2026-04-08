<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPermissionsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $action, // 'granted' | 'revoked'
        public ?string $permission = null
    ) {
        $this->user->loadMissing(['roles', 'permissions']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('service-requests'), // Broadcast to the existing admin channel
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserPermissionsUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'userId' => $this->user->id,
            'userName' => $this->user->name,
            'action' => $this->action,
            'permission' => $this->permission,
            'timestamp' => now()->toISOString(),
        ];
    }
}
