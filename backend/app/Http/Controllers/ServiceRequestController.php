<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Events\ServiceRequestAccepted;
use App\Events\ServiceRequestCompleted;
use App\Services\ServiceRequestCreationService;
use App\Services\ServiceRequestStatusService;
use App\Services\ServiceRequestQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Controller;

class ServiceRequestController extends Controller
{
    use AuthorizesRequests;

    protected ServiceRequestCreationService $creationService;
    protected ServiceRequestStatusService $statusService;
    protected ServiceRequestQueryService $queryService;

    public function __construct(
        ServiceRequestCreationService $creationService,
        ServiceRequestStatusService $statusService,
        ServiceRequestQueryService $queryService
    ) {
        $this->creationService = $creationService;
        $this->statusService = $statusService;
        $this->queryService = $queryService;
    }
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

        return $this->queryService->getRequests($request);
    }

    /**
     * POST /api/requests
     * Create a new pending service request (customers only, limit enforced by middleware).
     */
    public function store(StoreServiceRequestRequest $request): JsonResponse
    {
        // Authorization is handled inside StoreServiceRequestRequest::authorize()
        // Subscription limit is enforced by CheckRequestLimit middleware on this route

        $serviceRequest = $this->creationService->create([
            'customer_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'attachments' => $request->attachments ?? [],
            'category' => $request->category ?? null,
            'urgency' => $request->urgency ?? null,
            'enhance_with_ai' => $request->boolean('enhance_with_ai'),
        ]);

        return (new ServiceRequestResource($serviceRequest))
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

        return $this->queryService->getSingleRequest($serviceRequest);
    }

    /**
     * PATCH /api/requests/{serviceRequest}/accept
     * Provider accepts a pending request — guarded against race conditions with DB lock.
     */
    public function accept(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('accept', $serviceRequest);

        return $this->statusService->acceptRequest($request, $serviceRequest);
    }

    /**
     * PATCH /api/requests/{serviceRequest}/complete
     * Provider marks their accepted request as completed.
     */
    public function complete(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        $this->authorize('complete', $serviceRequest);

        return $this->statusService->completeRequest($request, $serviceRequest);
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
