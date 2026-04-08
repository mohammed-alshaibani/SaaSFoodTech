<?php

namespace App\Services;

use Illuminate\Contracts\Events\Dispatcher as EventDispatcherContract;
use Illuminate\Support\Facades\Log;

class EventDispatcherService
{
    protected EventDispatcherContract $eventDispatcher;

    public function __construct(EventDispatcherContract $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Dispatch an event with logging.
     */
    public function dispatch($event, array $metadata = []): void
    {
        try {
            $this->eventDispatcher->dispatch($event);
            
            $this->logEventDispatch($event, $metadata, 'success');
        } catch (\Exception $e) {
            $this->logEventDispatch($event, $metadata, 'error', $e);
            throw $e;
        }
    }

    /**
     * Dispatch multiple events.
     */
    public function dispatchMultiple(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }

    /**
     * Dispatch event with delay.
     */
    public function dispatchWithDelay($event, int $delay): void
    {
        // Implementation for delayed dispatch if needed
        $this->dispatch($event);
    }

    /**
     * Log event dispatch.
     */
    protected function logEventDispatch($event, array $metadata, string $status, ?\Exception $exception = null): void
    {
        $logData = [
            'event_class' => get_class($event),
            'event_data' => method_exists($event, 'toArray') ? $event->toArray() : [],
            'metadata' => $metadata,
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ];

        if ($exception) {
            $logData['error'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        if ($status === 'error') {
            Log::error('Event dispatch failed', $logData);
        } else {
            Log::info('Event dispatched successfully', $logData);
        }
    }

    /**
     * Get event statistics.
     */
    public function getEventStatistics(): array
    {
        // Mock implementation - would track actual statistics
        return [
            'total_events_dispatched' => 0,
            'events_today' => 0,
            'failed_events' => 0,
            'most_common_events' => [],
        ];
    }
}
