<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequestStatusChangedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $serviceRequest;
    protected $oldStatus;
    protected $newStatus;

    public function __construct($serviceRequest, $oldStatus, $newStatus)
    {
        $this->serviceRequest = $serviceRequest;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
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
            'message' => "Request status changed from {$this->oldStatus} to {$this->newStatus}",
            'type' => 'request_status_changed',
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
        ];
    }
}
