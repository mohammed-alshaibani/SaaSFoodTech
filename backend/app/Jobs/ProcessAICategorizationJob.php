<?php

namespace App\Jobs;

use App\Models\ServiceRequest;
use App\Services\OrderCategorizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessAICategorizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [10, 30, 60];

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected ServiceRequest $serviceRequest,
        protected array $options = []
    ) {
        $this->onQueue('ai-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(OrderCategorizationService $aiService): void
    {
        try {
            // Check if already processed
            if ($this->serviceRequest->ai_processed_at) {
                Log::info('AI already processed for request', [
                    'request_id' => $this->serviceRequest->id,
                ]);
                return;
            }

            // Process AI categorization and pricing
            $result = $aiService->categorizeAndPrice(
                $this->serviceRequest->title,
                $this->serviceRequest->description,
                $this->options
            );

            // Update service request with AI results
            $this->serviceRequest->update([
                'category' => $result['category'] ?? null,
                'ai_suggested_price' => $result['suggested_price'] ?? null,
                'ai_confidence_score' => $result['confidence_score'] ?? null,
                'ai_keywords' => $result['keywords'] ?? [],
                'ai_processed_at' => now(),
            ]);

            // Cache the result for quick retrieval
            $this->cacheAIResult($result);

            // Send notification to customer about AI processing
            if ($this->shouldNotifyCustomer()) {
                SendNotificationJob::dispatch(
                    $this->serviceRequest->customer,
                    'ai.processed',
                    [
                        'title' => $this->serviceRequest->title,
                        'category' => $result['category'],
                        'suggested_price' => $result['suggested_price'],
                    ]
                );
            }

            Log::info('AI processing completed successfully', [
                'request_id' => $this->serviceRequest->id,
                'category' => $result['category'] ?? null,
                'confidence_score' => $result['confidence_score'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('AI processing failed', [
                'request_id' => $this->serviceRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mark as failed but don't fail the job completely
            $this->serviceRequest->update([
                'ai_processing_failed' => true,
                'ai_processing_error' => $e->getMessage(),
                'ai_processed_at' => now(),
            ]);

            // Notify about AI processing failure
            SendNotificationJob::dispatch(
                $this->serviceRequest->customer,
                'ai.failed',
                [
                    'title' => $this->serviceRequest->title,
                    'error' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Cache AI result for quick retrieval.
     */
    private function cacheAIResult(array $result): void
    {
        $cacheKey = "ai_result_{$this->serviceRequest->id}";
        
        Cache::put($cacheKey, [
            'result' => $result,
            'processed_at' => now(),
            'request_id' => $this->serviceRequest->id,
        ], now()->addDays(7)); // Cache for 7 days
    }

    /**
     * Determine if customer should be notified.
     */
    private function shouldNotifyCustomer(): bool
    {
        return $this->options['notify_customer'] ?? true;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'ai-processing',
            'request:' . $this->serviceRequest->id,
            'customer:' . $this->serviceRequest->customer_id,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI categorization job failed', [
            'request_id' => $this->serviceRequest->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update request with failure information
        $this->serviceRequest->update([
            'ai_processing_failed' => true,
            'ai_processing_error' => $exception->getMessage(),
        ]);
    }
}
