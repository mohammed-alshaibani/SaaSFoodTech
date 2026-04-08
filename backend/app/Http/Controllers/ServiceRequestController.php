<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Events\ServiceRequestCreated;
use App\Events\ServiceRequestAccepted;
use App\Events\ServiceRequestCompleted;
use App\Events\ServiceRequestUpdated;
use App\Jobs\ProcessAIEnhancementJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ServiceRequestController extends Controller
{
    use AuthorizesRequests;
    /**
     * GET /api/requests
     * Role-scoped list:
     *  - Customer       -> only their own orders
     *  - Provider*      -> pending orders (+ their accepted/completed); nearby filter optional
     *  - Admin          -> all orders (before() in policy grants full access)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceRequest::class);

        $user = $request->user();
        $query = ServiceRequest::with('customer', 'provider', 'attachments');

        if ($user->hasRole('customer')) {
            // Customer sees only their own requests
            $query->where('customer_id', $user->id);

        } elseif ($user->hasRole(['provider_admin', 'provider_employee'])) {
            // Providers see pending + requests they own
            $query->where(function ($q) use ($user) {
                $q->where('status', 'pending')
                    ->orWhere('provider_id', $user->id);
            });

            // Optional nearby filter
            if ($request->filled(['latitude', 'longitude'])) {
                $query->nearby(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) ($request->radius ?? 50)
                );
            }
        }
        // Admin: no additional scope - sees everything (handled by before() bypass in policy)

        return ServiceRequestResource::collection($query->latest()->paginate(15));
    }

    /**
     * POST /api/requests
     * Create a new pending service request (customers only, limit enforced by middleware).
     */
    public function store(StoreServiceRequestRequest $request): JsonResponse
    {
        // Authorization is handled inside StoreServiceRequestRequest::authorize()
        // Subscription limit is enforced by CheckRequestLimit middleware on this route

        $serviceRequest = ServiceRequest::create([
            'customer_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'attachments' => $request->attachments ?? [], // Save optional file pointers
            'status' => 'pending',
        ]);

        // Fire event for service request creation + real-time broadcast
        $metadata = [
            'category' => $request->category ?? null,
            'urgency' => $request->urgency ?? null,
        ];
        event(new ServiceRequestCreated($serviceRequest, $metadata));
        event(new ServiceRequestUpdated($serviceRequest, 'created', $metadata));

        // Queue AI enhancement job (if requested)
        if ($request->boolean('enhance_with_ai')) {
            ProcessAIEnhancementJob::dispatch(
                $serviceRequest,
                $request->title,
                $request->description,
                $request->user()->id
            );
        }

        return (new ServiceRequestResource($serviceRequest->load('customer', 'attachments')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/requests/{serviceRequest}
     * Show a single request - Policy enforces who can view it.
     */
    public function show(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $this->authorize('view', $serviceRequest);

        return new ServiceRequestResource($serviceRequest->load('customer', 'provider', 'attachments'));
    }

    /**
     * PATCH /api/requests/{serviceRequest}/accept
     * Provider accepts a pending request — guarded against race conditions with DB lock.
     */
    public function accept(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('accept', $serviceRequest);

        // ── Atomic acceptance using DB lock ──────────────────────────────────
        // Re-fetch inside a transaction with a write lock so two concurrent providers
        // cannot both succeed in accepting the same pending request.
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

        // Fire legacy event + new unified broadcast
        $metadata = [
            'provider_notes' => $request->provider_notes ?? null,
            'estimated_completion' => $request->estimated_completion ?? null,
        ];
        event(new ServiceRequestAccepted($updated, $request->user()->id, $metadata));
        event(new ServiceRequestUpdated($updated, 'accepted', $metadata));

        return response()->json([
            'success' => true,
            'message' => 'Request accepted successfully.',
            'data' => new ServiceRequestResource($updated),
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * PATCH /api/requests/{serviceRequest}/complete
     * Provider marks their accepted request as completed.
     */
    public function complete(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('complete', $serviceRequest);

        $serviceRequest->update(['status' => 'completed']);

        // Fire legacy event + new unified broadcast
        $metadata = [
            'completion_notes' => $request->completion_notes ?? null,
            'final_attachments' => $request->final_attachments ?? [],
            'rating' => $request->rating ?? null,
        ];
        event(new ServiceRequestCompleted($serviceRequest, $metadata));
        event(new ServiceRequestUpdated($serviceRequest->fresh()->load('customer', 'provider'), 'completed', $metadata));

        return response()->json([
            'success' => true,
            'message' => 'Request marked as completed.',
            'data' => new ServiceRequestResource($serviceRequest->load('customer', 'provider')),
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * GET /api/requests/nearby
     * Get nearby pending service requests within specified radius.
     * Requires latitude, longitude, and optional radius parameters.
     */
    public function nearby(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewNearby', ServiceRequest::class);

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:500', // Max 500km radius
            'status' => 'nullable|in:pending,accepted,completed',
        ]);

        $query = ServiceRequest::with('customer', 'attachments')
            ->nearby(
                (float) $request->latitude,
                (float) $request->longitude,
                (float) ($request->radius ?? 50) // Default 50km radius
            );

        // Filter by status if specified, default to pending only
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->pending(); // Default to pending requests only
        }

        // Exclude user's own requests
        $query->where('customer_id', '!=', $request->user()->id);

        // Exclude requests already accepted by this user
        $query->where(function ($q) use ($request) {
            $q->whereNull('provider_id')
                ->orWhere('provider_id', '!=', $request->user()->id);
        });

        return ServiceRequestResource::collection($query->latest()->paginate(20));
    }
}
