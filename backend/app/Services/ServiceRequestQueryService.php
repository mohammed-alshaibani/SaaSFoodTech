<?php

namespace App\Services;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\ServiceRequestResource;

class ServiceRequestQueryService
{
    /**
     * Get service requests based on user role and filters.
     */
    public function getRequests(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = ServiceRequest::with('customer', 'provider', 'attachments');

        $this->applyRoleScoping($query, $user, $request);
        $this->applyFilters($query, $request);

        return ServiceRequestResource::collection($query->latest()->paginate(15));
    }

    /**
     * Apply role-based scoping to the query.
     */
    protected function applyRoleScoping($query, $user, Request $request): void
    {
        if ($user->hasRole('customer')) {
            // Customer sees only their own requests
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(['provider_admin', 'provider_employee'])) {
            // Providers see pending + requests they own
            $query->where(function ($q) use ($user) {
                $q->where('status', 'pending')
                    ->orWhere('provider_id', $user->id);
            });
        }
        // Admin: no additional scope - sees everything (handled by policy)
    }

    /**
     * Apply filters to the query.
     */
    protected function applyFilters($query, Request $request): void
    {
        // Nearby filter for providers
        if ($request->filled(['latitude', 'longitude'])) {
            $query->nearby(
                (float) $request->latitude,
                (float) $request->longitude,
                (float) ($request->radius ?? 50)
            );
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Category filter
        if ($request->filled('category')) {
            $query->whereJsonContains('metadata->category', $request->category);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
    }

    /**
     * Get single service request with proper authorization check.
     */
    public function getSingleRequest(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        return new ServiceRequestResource($serviceRequest->load('customer', 'provider', 'attachments'));
    }

    /**
     * Get request statistics for dashboard.
     */
    public function getRequestStatistics(Request $request): array
    {
        $user = $request->user();
        $query = ServiceRequest::query();

        // Apply same role scoping as main query
        if ($user->hasRole('customer')) {
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(['provider_admin', 'provider_employee'])) {
            $query->where(function ($q) use ($user) {
                $q->where('status', 'pending')
                    ->orWhere('provider_id', $user->id);
            });
        }

        return [
            'total' => $query->count(),
            'pending' => $query->where('status', 'pending')->count(),
            'accepted' => $query->where('status', 'accepted')->count(),
            'completed' => $query->where('status', 'completed')->count(),
            'this_month' => $query->whereMonth('created_at', now()->month)->count(),
        ];
    }
}
