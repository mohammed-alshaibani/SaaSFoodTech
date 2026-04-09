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
        ]);

        return $serviceRequest;
    }

    /**
     * Complete a service request.
     */
    public function completeServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Completing service request {$serviceRequest->id}");

        $serviceRequest->update([
            'status' => 'completed',
        ]);

        return $serviceRequest;
    }
}
