<?php

namespace App\Jobs;

use App\Services\DescriptionEnhancerService;
use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessAIEnhancementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ServiceRequest $serviceRequest,
        public string $title,
        public string $description,
        public ?int $userId = null
    ) {
        $this->onQueue('ai-processing');
    }

    /**
     * Execute the job.
     */
    public function handle(DescriptionEnhancerService $enhancer): void
    {
        try {
            // Check if the service request still exists and hasn't been updated
            $currentRequest = ServiceRequest::find($this->serviceRequest->id);
            if (!$currentRequest || $currentRequest->description !== $this->description) {
                Log::info('AI Enhancement skipped - request modified', [
                    'service_request_id' => $this->serviceRequest->id,
                    'user_id' => $this->userId,
                ]);
                return;
            }

            // Rate limiting check per user
            $cacheKey = "ai_enhancement:" . ($this->userId ?? 'anonymous');
            if (Cache::has($cacheKey)) {
                Log::warning('AI Enhancement rate limited', [
                    'service_request_id' => $this->serviceRequest->id,
                    'user_id' => $this->userId,
                ]);
                return;
            }

            // Set rate limit cache (5 minutes)
            Cache::put($cacheKey, true, 300);

            Log::info('Processing AI enhancement', [
                'service_request_id' => $this->serviceRequest->id,
                'user_id' => $this->userId,
                'title' => $this->title,
            ]);

            // Process the AI enhancement
            $enhancedDescription = $enhancer->enhance($this->title, $this->description);

            // Update the service request if enhancement succeeded
            if ($enhancedDescription !== $this->description) {
                $currentRequest->update([
                    'description' => $enhancedDescription,
                ]);

                Log::info('AI enhancement completed', [
                    'service_request_id' => $this->serviceRequest->id,
                    'user_id' => $this->userId,
                    'original_length' => strlen($this->description),
                    'enhanced_length' => strlen($enhancedDescription),
                ]);

                // Fire event for successful enhancement
                event(new \App\Events\AIEnhancementCompleted($currentRequest, $enhancedDescription));
            } else {
                Log::info('AI enhancement returned same content', [
                    'service_request_id' => $this->serviceRequest->id,
                    'user_id' => $this->userId,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('AI Enhancement job failed', [
                'service_request_id' => $this->serviceRequest->id,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Retry the job if we haven't exceeded max attempts
            if ($this->attempts() < $this->tries) {
                $this->release(30); // Wait 30 seconds before retry
            } else {
                // Fire event for failed enhancement
                event(new \App\Events\AIEnhancementFailed($this->serviceRequest, $e->getMessage()));
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AI Enhancement job permanently failed', [
            'service_request_id' => $this->serviceRequest->id,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Clean up rate limit cache on failure
        $cacheKey = "ai_enhancement:" . ($this->userId ?? 'anonymous');
        Cache::forget($cacheKey);
    }
}
