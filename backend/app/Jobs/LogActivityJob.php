<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 15;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $activityData
    ) {
        $this->onQueue('audit-logging');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $action = $this->activityData['action'];
            $userId = $this->activityData['user_id'] ?? null;
            $targetId = $this->activityData['target_id'] ?? null;
            $targetType = $this->activityData['target_type'] ?? null;
            $metadata = $this->activityData['metadata'] ?? [];

            Log::info('Logging activity', [
                'action' => $action,
                'user_id' => $userId,
                'target_id' => $targetId,
                'target_type' => $targetType,
            ]);

            // Store activity log in database
            $this->storeActivityLog([
                'action' => $action,
                'user_id' => $userId,
                'target_id' => $targetId,
                'target_type' => $targetType,
                'metadata' => json_encode($metadata),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
            ]);

            // Check for suspicious activities
            $this->checkForSuspiciousActivity($action, $userId, $metadata);

        } catch (\Exception $e) {
            Log::error('Activity logging job failed', [
                'action' => $this->activityData['action'],
                'error' => $e->getMessage(),
            ]);

            // Don't throw the exception - activity logging should not break the main flow
        }
    }

    /**
     * Store activity log in database.
     */
    protected function storeActivityLog(array $data): void
    {
        // In production, you would have an activity_logs table
        // For now, we'll log to file and simulate database storage
        Log::info('Activity logged', $data);

        // Simulate database insert
        // DB::table('activity_logs')->insert($data);
    }

    /**
     * Check for suspicious activities and alert if necessary.
     */
    protected function checkForSuspiciousActivity(string $action, ?int $userId, array $metadata): void
    {
        $suspiciousActions = [
            'multiple_failed_logins',
            'privilege_escalation',
            'bulk_data_export',
            'unusual_access_pattern',
        ];

        if (in_array($action, $suspiciousActions)) {
            Log::warning('Suspicious activity detected', [
                'action' => $action,
                'user_id' => $userId,
                'metadata' => $metadata,
                'requires_review' => true,
            ]);

            // In production, you might send alerts to security team
            // SendAlertJob::dispatch('security_team', 'Suspicious activity detected', $data);
        }

        // Check for rapid successive actions from same user
        $this->checkForRapidActions($action, $userId);
    }

    /**
     * Check for rapid successive actions (potential automation/abuse).
     */
    protected function checkForRapidActions(string $action, ?int $userId): void
    {
        if (!$userId) return;

        $cacheKey = "user_activity:{$userId}:{$action}";
        $recentCount = cache()->get($cacheKey, 0);

        if ($recentCount > 10) { // More than 10 actions in 5 minutes
            Log::warning('Rapid action pattern detected', [
                'user_id' => $userId,
                'action' => $action,
                'count' => $recentCount,
                'requires_review' => true,
            ]);
        }

        // Increment counter with 5 minute expiry
        cache()->put($cacheKey, $recentCount + 1, 300);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Activity logging job permanently failed', [
            'action' => $this->activityData['action'],
            'error' => $exception->getMessage(),
        ]);
    }
}
