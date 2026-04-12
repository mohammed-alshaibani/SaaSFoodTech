<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\DTOs\ServiceRequestDTO;
use Illuminate\Support\Facades\Log;

class MainService
{
    /**
     * Create a new service request and attach any files.
     * 
     * @param ServiceRequestDTO $dto
     * @param int $userId
     * @return ServiceRequest
     */
    public function createServiceRequest(ServiceRequestDTO $dto, int $userId): ServiceRequest
    {
        Log::info("Processing new service request creation for user: {$userId}");

        $user = \App\Models\User::find($userId);
        if ($user->hasExceededRequestLimit()) {
            abort(403, 'Subscription limit reached. Please upgrade to Pro.');
        }

        // Direct Eloquent usage natively. No over-engineered Repositories needed here.
        $serviceRequest = ServiceRequest::create([
            'customer_id' => $userId,
            'title' => $dto->title,
            'description' => $dto->description,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'category' => $dto->category,
            'status' => 'pending',
        ]);

        // Handled naturally via the HasFileUploads Trait if attachments exist
        if (!empty($dto->attachments)) {
            $serviceRequest->addAttachments($dto->attachments);
        }

        return $serviceRequest;
    }

    /**
     * Accept a service request by a provider.
     */
    public function acceptServiceRequest(ServiceRequest $serviceRequest, int $providerId): ServiceRequest
    {
        Log::info("Provider {$providerId} accepting service request {$serviceRequest->id}");

        $serviceRequest->update([
            'provider_id' => $providerId,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        return $serviceRequest;
    }

    /**
     * Provider marks request as work done.
     */
    public function workDoneServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Provider finished work on service request {$serviceRequest->id}");

        $serviceRequest->update([
            'status' => 'work_done',
        ]);

        return $serviceRequest;
    }

    /**
     * Customer marks service request as completed/approved.
     */
    public function completeServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Completing service request {$serviceRequest->id}");

        $serviceRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $serviceRequest;
    }

    /**
     * Customer cancels a service request.
     */
    public function cancelServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Canceling service request {$serviceRequest->id}");

        $serviceRequest->update([
            'status' => 'cancelled',
        ]);

        return $serviceRequest;
    }

    /**
     * Provider drops an accepted service request.
     */
    public function dropServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Provider dropping service request {$serviceRequest->id}");

        $serviceRequest->update([
            'provider_id' => null,
            'status' => 'pending',
            'accepted_at' => null,
        ]);

        return $serviceRequest;
    }
}
