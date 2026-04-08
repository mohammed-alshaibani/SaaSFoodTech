<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateStatisticsJob implements ShouldQueue
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
        public array $statsData
    ) {
        $this->onQueue('statistics');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $type = $this->statsData['type'];
            $data = $this->statsData['data'];

            Log::info('Updating statistics', [
                'type' => $type,
                'data_keys' => array_keys($data),
            ]);

            switch ($type) {
                case 'service_request_created':
                    $this->updateServiceRequestStats($data);
                    break;
                case 'service_request_accepted':
                    $this->updateAcceptanceStats($data);
                    break;
                case 'service_request_completed':
                    $this->updateCompletionStats($data);
                    break;
                case 'user_registered':
                    $this->updateUserStats($data);
                    break;
                default:
                    Log::warning('Unknown statistics type', ['type' => $type]);
            }

        } catch (\Exception $e) {
            Log::error('Statistics update job failed', [
                'type' => $this->statsData['type'],
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Update service request creation statistics.
     */
    protected function updateServiceRequestStats(array $data): void
    {
        $cacheKey = 'stats:service_requests';
        $stats = Cache::get($cacheKey, [
            'total' => 0,
            'by_category' => [],
            'by_urgency' => [],
            'by_date' => [],
        ]);

        $stats['total']++;
        
        // Update category stats
        $category = $data['category'] ?? 'other';
        $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;

        // Update urgency stats
        $urgency = $data['urgency'] ?? 'normal';
        $stats['by_urgency'][$urgency] = ($stats['by_urgency'][$urgency] ?? 0) + 1;

        // Update daily stats
        $today = now()->toDateString();
        $stats['by_date'][$today] = ($stats['by_date'][$today] ?? 0) + 1;

        Cache::put($cacheKey, $stats, 86400); // Cache for 24 hours

        // Update database aggregates (async)
        $this->updateDatabaseAggregates('service_requests_created');
    }

    /**
     * Update service request acceptance statistics.
     */
    protected function updateAcceptanceStats(array $data): void
    {
        $cacheKey = 'stats:acceptances';
        $stats = Cache::get($cacheKey, [
            'total' => 0,
            'by_provider' => [],
            'average_response_time' => 0,
        ]);

        $stats['total']++;

        if (isset($data['provider_id'])) {
            $providerId = $data['provider_id'];
            $stats['by_provider'][$providerId] = ($stats['by_provider'][$providerId] ?? 0) + 1;
        }

        Cache::put($cacheKey, $stats, 86400);
        $this->updateDatabaseAggregates('service_requests_accepted');
    }

    /**
     * Update service request completion statistics.
     */
    protected function updateCompletionStats(array $data): void
    {
        $cacheKey = 'stats:completions';
        $stats = Cache::get($cacheKey, [
            'total' => 0,
            'average_completion_time' => 0,
            'by_rating' => [],
        ]);

        $stats['total']++;

        if (isset($data['rating'])) {
            $rating = $data['rating'];
            $stats['by_rating'][$rating] = ($stats['by_rating'][$rating] ?? 0) + 1;
        }

        Cache::put($cacheKey, $stats, 86400);
        $this->updateDatabaseAggregates('service_requests_completed');
    }

    /**
     * Update user registration statistics.
     */
    protected function updateUserStats(array $data): void
    {
        $cacheKey = 'stats:users';
        $stats = Cache::get($cacheKey, [
            'total' => 0,
            'by_role' => [],
            'by_date' => [],
        ]);

        $stats['total']++;

        if (isset($data['role'])) {
            $role = $data['role'];
            $stats['by_role'][$role] = ($stats['by_role'][$role] ?? 0) + 1;
        }

        $today = now()->toDateString();
        $stats['by_date'][$today] = ($stats['by_date'][$today] ?? 0) + 1;

        Cache::put($cacheKey, $stats, 86400);
        $this->updateDatabaseAggregates('users_registered');
    }

    /**
     * Update database aggregates (for reporting).
     */
    protected function updateDatabaseAggregates(string $type): void
    {
        // In production, you might want to update materialized views
        // or aggregate tables for reporting performance
        Log::info('Database aggregates updated', ['type' => $type]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Statistics update job permanently failed', [
            'type' => $this->statsData['type'],
            'error' => $exception->getMessage(),
        ]);
    }
}
