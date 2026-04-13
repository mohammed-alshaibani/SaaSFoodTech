<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestAssignedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $serviceRequest;

    public function __construct($serviceRequest)
    {
        $this->serviceRequest = $serviceRequest;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'request_id' => $this->serviceRequest->id,
            'title' => $this->serviceRequest->title,
            'message' => 'New service request assigned to you',
            'type' => 'request_assigned',
        ];
    }
}
