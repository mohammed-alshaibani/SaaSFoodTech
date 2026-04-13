<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class RequestStatusNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    public $request;
    public $message;
    public $action;

    /**
     * Create a new notification instance.
     */
    public function __construct(ServiceRequest $request, string $message, string $action)
    {
        $this->request = $request;
        $this->message = $message;
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->request->id,
            'title' => $this->request->title,
            'message' => $this->message,
            'action' => $this->action,
            'created_at' => now()->toISOString(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'request_id' => $this->request->id,
            'title' => $this->request->title,
            'message' => $this->message,
            'action' => $this->action,
            'created_at' => now()->toISOString(),
        ]);
    }
}
