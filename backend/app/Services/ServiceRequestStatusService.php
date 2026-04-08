<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Events\ServiceRequestAccepted;
use App\Events\ServiceRequestCompleted;
use App\Events\ServiceRequestUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\UpdateServiceRequestRequest;

class ServiceRequestStatusService
{
    protected EventDispatcherService $eventDispatcher;

    public function __construct(EventDispatcherService $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Accept a service request with atomic operation.
     */
    public function acceptRequest(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        // Atomic acceptance using DB lock to prevent race conditions
        $updated = DB::transaction(function () use ($request, $serviceRequest) {
            $fresh = ServiceRequest::lockForUpdate()->find($serviceRequest->id);

            if ($fresh->status !== 'pending') {
                return null; // Already accepted/completed by another provider
            }

            $fresh->update([
                'provider_id' => $request->user()->id,
                'status' => 'accepted',
            ]);

            return $fresh;
        });

        if ($updated === null) {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been accepted by another provider.',
            ], 409);
        }

        $updated->load('customer', 'provider');

        // Dispatch acceptance event
        $this->eventDispatcher->dispatch(new ServiceRequestAccepted($updated));
        $this->eventDispatcher->dispatch(new ServiceRequestUpdated($updated, 'accepted'));

        return response()->json([
            'success' => true,
            'message' => 'Request accepted successfully.',
            'data' => new \App\Http\Resources\ServiceRequestResource($updated),
        ]);
    }

    /**
     * Complete a service request.
     */
    public function completeRequest(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        if ($serviceRequest->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Only accepted requests can be completed.',
            ], 400);
        }

        $serviceRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $serviceRequest->load('customer', 'provider');

        // Dispatch completion events
        $this->eventDispatcher->dispatch(new ServiceRequestCompleted($serviceRequest));
        $this->eventDispatcher->dispatch(new ServiceRequestUpdated($serviceRequest, 'completed'));

        return response()->json([
            'success' => true,
            'message' => 'Request completed successfully.',
            'data' => new \App\Http\Resources\ServiceRequestResource($serviceRequest),
        ]);
    }

    /**
     * Cancel a service request.
     */
    public function cancelRequest(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        if ($serviceRequest->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed requests cannot be cancelled.',
            ], 400);
        }

        $serviceRequest->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
        ]);

        $serviceRequest->load('customer', 'provider');

        // Dispatch cancellation event
        $this->eventDispatcher->dispatch(new ServiceRequestUpdated($serviceRequest, 'cancelled'));

        return response()->json([
            'success' => true,
            'message' => 'Request cancelled successfully.',
            'data' => new \App\Http\Resources\ServiceRequestResource($serviceRequest),
        ]);
    }

    /**
     * Update service request details.
     */
    public function updateRequest(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $allowedFields = ['title', 'description', 'latitude', 'longitude'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if ($request->filled($field)) {
                $updateData[$field] = $request->$field;
            }
        }

        if (empty($updateData)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid fields to update.',
            ], 400);
        }

        $serviceRequest->update($updateData);
        $serviceRequest->load('customer', 'provider', 'attachments');

        // Dispatch update event
        $this->eventDispatcher->dispatch(new ServiceRequestUpdated($serviceRequest, 'updated'));

        return response()->json([
            'success' => true,
            'message' => 'Request updated successfully.',
            'data' => new \App\Http\Resources\ServiceRequestResource($serviceRequest),
        ]);
    }

    /**
     * Get available status transitions for a request.
     */
    public function getAvailableTransitions(ServiceRequest $serviceRequest, $user): array
    {
        $transitions = [];

        switch ($serviceRequest->status) {
            case 'pending':
                if ($user->hasRole(['provider_admin', 'provider_employee'])) {
                    $transitions[] = 'accept';
                }
                if ($user->id === $serviceRequest->customer_id || $user->hasRole('admin')) {
                    $transitions[] = 'cancel';
                }
                break;

            case 'accepted':
                if ($user->id === $serviceRequest->provider_id) {
                    $transitions[] = 'complete';
                }
                if ($user->id === $serviceRequest->customer_id || $user->hasRole('admin')) {
                    $transitions[] = 'cancel';
                }
                break;

            case 'completed':
            case 'cancelled':
                // No transitions available
                break;
        }

        return $transitions;
    }
}
