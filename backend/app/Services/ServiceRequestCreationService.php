<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Events\ServiceRequestCreated;
use App\Events\ServiceRequestUpdated;
use App\Jobs\ProcessAIEnhancementJob;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceRequestCreationService
{
    protected AIEnhancementService $aiService;
    protected EventDispatcherService $eventDispatcher;

    public function __construct(
        AIEnhancementService $aiService,
        EventDispatcherService $eventDispatcher
    ) {
        $this->aiService = $aiService;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Create a new service request with proper business logic separation.
     */
    public function create(array $data): ServiceRequest
    {
        $this->validateCreationData($data);

        $serviceRequest = ServiceRequest::create([
            'customer_id' => $data['customer_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'attachments' => $data['attachments'] ?? [],
            'status' => 'pending',
        ]);

        $metadata = [
            'category' => $data['category'] ?? null,
            'urgency' => $data['urgency'] ?? null,
        ];

        // Dispatch events
        $this->eventDispatcher->dispatch(new ServiceRequestCreated($serviceRequest, $metadata));
        $this->eventDispatcher->dispatch(new ServiceRequestUpdated($serviceRequest, 'created', $metadata));

        // Handle AI enhancement if requested
        if (!empty($data['enhance_with_ai'])) {
            $this->aiService->enhanceAsync($serviceRequest, $data);
        }

        return $serviceRequest->load(['customer', 'attachments']);
    }

    /**
     * Validate service request creation data.
     */
    protected function validateCreationData(array $data): void
    {
        $validator = Validator::make($data, [
            'customer_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'attachments' => 'array',
            'attachments.*' => 'string',
            'category' => 'nullable|string|max:100',
            'urgency' => 'nullable|in:low,normal,high,urgent',
            'enhance_with_ai' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Check if user can create more requests based on their subscription.
     */
    public function canUserCreateRequest(int $userId): bool
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            return false;
        }

        return !$user->hasExceededRequestLimit();
    }

    /**
     * Get remaining requests for user.
     */
    public function getRemainingRequests(int $userId): array
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            return ['remaining' => 0, 'limit' => 0, 'used' => 0];
        }

        return $user->getCurrentMonthUsage();
    }
}
