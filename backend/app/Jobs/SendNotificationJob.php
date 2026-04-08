<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $channel,
        public string $title,
        public string $message,
        public array $data = []
    ) {
        $this->onQueue('notifications');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Processing notification', [
                'channel' => $this->channel,
                'title' => $this->title,
                'data_keys' => array_keys($this->data),
            ]);

            // In a real implementation, you would:
            // 1. Send push notifications via Firebase/OneSignal
            // 2. Send emails if configured
            // 3. Send SMS if configured
            // 4. Send WebSocket notifications

            // For demo purposes, we'll just log the notification
            $this->sendWebSocketNotification();
            $this->sendEmailNotification();
            $this->sendPushNotification();

        } catch (\Exception $e) {
            Log::error('Notification job failed', [
                'channel' => $this->channel,
                'title' => $this->title,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send WebSocket notification (simulated).
     */
    protected function sendWebSocketNotification(): void
    {
        // In production, use Laravel Reverb or Pusher
        Log::info('WebSocket notification sent', [
            'channel' => $this->channel,
            'title' => $this->title,
            'message' => $this->message,
        ]);
    }

    /**
     * Send email notification (simulated).
     */
    protected function sendEmailNotification(): void
    {
        // In production, use Laravel Mail with proper templates
        if (str_contains($this->channel, 'customer.') || str_contains($this->channel, 'provider.')) {
            Log::info('Email notification would be sent', [
                'channel' => $this->channel,
                'title' => $this->title,
                'message' => $this->message,
            ]);
        }
    }

    /**
     * Send push notification (simulated).
     */
    protected function sendPushNotification(): void
    {
        // In production, use Firebase Cloud Messaging or OneSignal
        Log::info('Push notification would be sent', [
            'channel' => $this->channel,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job permanently failed', [
            'channel' => $this->channel,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
