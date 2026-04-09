<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceRequestRequest;
use App\Http\Requests\UpdateServiceRequestRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Events\ServiceRequestCreated;
use App\Events\ServiceRequestAccepted;
use App\Events\ServiceRequestCompleted;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Services\MainService;
use App\DTOs\ServiceRequestDTO;

class ServiceRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * GET /api/requests
     * Role-scoped list of service requests
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceRequest::class);

        $user = $request->user();
        $query = ServiceRequest::with(['customer', 'provider']);

        // Role-based filtering
        if ($user->hasRole('customer')) {
            // Customers see only their own requests
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(['provider_admin', 'provider_employee'])) {
            // Providers see pending requests + their own accepted/completed
            $query->where(function ($q) use ($user) {
                $q->where('status', 'pending')
                    ->orWhere('provider_id', $user->id);
            });
        }
        // Admins see all (handled by policy before() method)

        // Apply filters
        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        return ServiceRequestResource::collection(
            $query->latest()->paginate(20)
        );
    }

    /**
     * POST /api/requests
     * Create a new service request
     */
    public function store(StoreServiceRequestRequest $request, MainService $mainService): JsonResponse
    {
        $this->authorize('create', ServiceRequest::class);

        // Data mapping to strongly-typed DTO
        $dto = new ServiceRequestDTO(
            title: $request->title,
            description: $request->description,
            latitude: $request->latitude,
            longitude: $request->longitude,
            category: $request->category ?? null,
            attachments: $request->file('attachments', [])
        );

        // Delegate to central MainService
        $serviceRequest = $mainService->createServiceRequest($dto, $request->user()->id);

        // Fire native events (or rely on Observer)
        ServiceRequestCreated::dispatch($serviceRequest);

        // AI Enhancement (if requested)
        if ($request->boolean('enhance_with_ai')) {
            $this->enhanceWithAI($serviceRequest);
        }

        return (new ServiceRequestResource($serviceRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/requests/{serviceRequest}
     * Show a single service request
     */
    public function show(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $this->authorize('view', $serviceRequest);

        $serviceRequest->load(['customer', 'provider']);
        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * PATCH /api/requests/{serviceRequest}/accept
     * Provider accepts a pending request
     */
    public function accept(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest, MainService $mainService): JsonResponse
    {
        $this->authorize('accept', $serviceRequest);

        // Prevent race condition with database lock
        $serviceRequest = ServiceRequest::where('id', $serviceRequest->id)
            ->where('status', 'pending')
            ->whereNull('provider_id')
            ->lockForUpdate()
            ->firstOrFail();

        // Delegate to MainService
        $serviceRequest = $mainService->acceptServiceRequest($serviceRequest, $request->user()->id);

        // Fire native event
        ServiceRequestAccepted::dispatch($serviceRequest, $request->user()->id);

        return response()->json([
            'message' => 'Service request accepted successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * PATCH /api/requests/{serviceRequest}/complete
     * Provider marks a request as completed
     */
    public function complete(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest, MainService $mainService): JsonResponse
    {
        $this->authorize('complete', $serviceRequest);

        // Delegate to MainService
        $serviceRequest = $mainService->completeServiceRequest($serviceRequest);

        // Fire native event
        ServiceRequestCompleted::dispatch($serviceRequest, $request->user()->id);

        return response()->json([
            'message' => 'Service request completed successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * GET /api/requests/nearby
     * Get nearby pending service requests
     */
    public function nearby(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewNearby', ServiceRequest::class);

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1|max:500',
            'status' => 'nullable|in:pending,accepted,completed',
        ]);

        $user = $request->user();
        $lat = (float) $request->latitude;
        $lng = (float) $request->longitude;
        $radius = (float) ($request->radius ?? 50);

        // Get nearby requests using spatial query
        $query = ServiceRequest::with(['customer'])
            ->nearby($lat, $lng, $radius);

        // Filter by status (default to pending)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        // Exclude user's own requests
        $query->where('customer_id', '!=', $user->id);

        // Exclude requests already accepted by this user
        $query->where(function ($q) use ($user) {
            $q->whereNull('provider_id')
                ->orWhere('provider_id', '!=', $user->id);
        });

        return ServiceRequestResource::collection(
            $query->latest()->paginate(20)
        );
    }

    /**
     * Enhance service request with AI
     */
    private function enhanceWithAI(ServiceRequest $serviceRequest): void
    {
        try {
            // Simple AI enhancement (direct API call)
            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . config('services.gemini.api_key'), [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => "Enhance this service request description for clarity and professionalism: " . $serviceRequest->description
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $serviceRequest->update([
                    'description' => $data['candidates'][0]['content']['parts'][0]['text']
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('AI enhancement failed: ' . $e->getMessage());
        }
    }
}
