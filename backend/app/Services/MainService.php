<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\DTOs\ServiceRequestDTO;
use Illuminate\Support\Facades\Log;

class MainService
{
    public function createServiceRequest(ServiceRequestDTO $dto, int $userId): ServiceRequest
    {
        Log::info("Processing new service request creation for user: {$userId}");

        $user = \App\Models\User::find($userId);
        if ($user->hasExceededRequestLimit()) {
            abort(403, 'Subscription limit reached. Please upgrade to Pro.');
        }

        $serviceRequest = ServiceRequest::create([
            'customer_id' => $userId,
            'provider_id' => $dto->provider_id,
            'title' => $dto->title,
            'description' => $dto->description,
            'latitude' => $dto->latitude,
            'longitude' => $dto->longitude,
            'category' => $dto->category,
            'status' => 'pending',
        ]);

        if (!empty($dto->attachments)) {
            $serviceRequest->addAttachments($dto->attachments);
        }

        return $serviceRequest;
    }

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

    public function workDoneServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Provider finished work on service request {$serviceRequest->id}");

        $serviceRequest->update([
            'status' => 'work_done',
        ]);

        return $serviceRequest;
    }

    public function completeServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Completing service request {$serviceRequest->id}");

        $serviceRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $serviceRequest;
    }

    public function cancelServiceRequest(ServiceRequest $serviceRequest): ServiceRequest
    {
        Log::info("Canceling service request {$serviceRequest->id}");

        $serviceRequest->update([
            'status' => 'cancelled',
        ]);

        return $serviceRequest;
    }

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
