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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use App\Services\MainService;
use App\DTOs\ServiceRequestDTO;
use App\Notifications\RequestStatusNotification;
use Illuminate\Support\Facades\Notification;
use App\Models\User;

class ServiceRequestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = ServiceRequest::with(['customer', 'provider']);

        if ($user->hasRole('customer')) {
            $query->where('customer_id', $user->id);
        } elseif ($user->hasRole(['provider_admin', 'provider_employee', 'provider'])) {
            $query->where(function ($q) use ($user) {
                $q->where('status', 'pending')
                    ->orWhere('provider_id', $user->id);
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has(['latitude', 'longitude'])) {
            $query->nearby(
                (float) $request->latitude,
                (float) $request->longitude,
                (float) ($request->radius ?? 50)
            );
        }

        $requests = $query->latest()->paginate(20);

        return ServiceRequestResource::collection($requests)->additional(['success' => true]);
    }

    public function store(StoreServiceRequestRequest $request, MainService $mainService): JsonResponse
    {
        $this->authorize('create', ServiceRequest::class);

        $dto = new ServiceRequestDTO(
            title: $request->title,
            description: $request->description,
            latitude: $request->latitude,
            longitude: $request->longitude,
            category: $request->category ?? null,
            attachments: $request->file('attachments', [])
        );

        $serviceRequest = $mainService->createServiceRequest($dto, $request->user()->id);

        ServiceRequestCreated::dispatch($serviceRequest);

        // Notify Admins
        $admins = User::role('admin')->get();
        Notification::send($admins, new RequestStatusNotification(
            $serviceRequest,
            "New service request created: {$serviceRequest->title}",
            'created'
        ));

        // Notify targeted provider if exists
        if ($dto->provider_id) {
            $targetedProvider = User::find($dto->provider_id);
            if ($targetedProvider) {
                $targetedProvider->notify(new RequestStatusNotification(
                    $serviceRequest,
                    "You have a new targeted service request: {$serviceRequest->title}",
                    'targeted_request'
                ));
            }
        } else {
            // Notify nearby providers (Optional: could be throttled or limited)
            $providers = User::role(['provider_admin', 'provider_employee'])
                ->whereNotNull('latitude')
                ->get(); // In production, we'd filter by radius here

            Notification::send($providers, new RequestStatusNotification(
                $serviceRequest,
                "New pending service request in your area: {$serviceRequest->title}",
                'nearby_request'
            ));
        }

        $hasProviders = \App\Models\User::role(['provider_admin', 'provider_employee'])
            ->whereNotNull('latitude')
            ->count() > 0;

        $warnings = [];
        if (!$hasProviders) {
            $warnings = ['No providers are currently registered with locations in your area. Acceptance may be delayed.'];
        }

        return response()->json([
            'success' => true,
            'message' => 'Service request created successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer'])),
            'warnings' => $warnings,
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString()
        ], 201);
    }

    /**
     * Show a single service request
     */
    public function show($id): ServiceRequestResource
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        $this->authorize('view', $serviceRequest);
        $serviceRequest->load(['customer', 'provider']);
        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * Provider accepts a pending request
     */
    public function accept(Request $request, $id, MainService $mainService): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        $user = $request->user();

        if (!$user->hasRole(['provider_admin', 'provider_employee', 'provider', 'admin'])) {
            return response()->json(['message' => 'Only providers can accept requests.'], 403);
        }

        if ((int) $serviceRequest->customer_id === (int) $user->id) {
            return response()->json(['message' => 'You cannot accept your own request.'], 403);
        }

        // Case 1: You already own this request
        if ((int) $serviceRequest->provider_id === (int) $user->id) {
            return response()->json([
                'message' => 'You have already accepted this request.',
                'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
            ]);
        }

        // Case 2: Someone else accepted it
        if ($serviceRequest->provider_id !== null) {
            return response()->json(['message' => 'This request has already been accepted by another provider.'], 409);
        }

        // Case 3: Status is not pending (but wasn't caught by provider check)
        if ($serviceRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be accepted.'], 422);
        }

        // Prevent race condition with database lock
        $serviceRequest = ServiceRequest::where('id', $serviceRequest->id)
            ->where('status', 'pending')
            ->whereNull('provider_id')
            ->lockForUpdate()
            ->first();

        if (!$serviceRequest) {
            return response()->json(['message' => 'Request is no longer available.'], 410);
        }

        $serviceRequest = $mainService->acceptServiceRequest($serviceRequest, $request->user()->id);
        ServiceRequestAccepted::dispatch($serviceRequest, $request->user()->id);
        ServiceRequestUpdated::dispatch($serviceRequest, 'accepted');

        // Notify Admins
        $admins = User::role('admin')->get();
        Notification::send($admins, new RequestStatusNotification(
            $serviceRequest,
            "Service request accepted by provider: {$serviceRequest->title}",
            'accepted_admin'
        ));

        // Notify Customer
        if ($serviceRequest->customer) {
            $serviceRequest->customer->notify(new RequestStatusNotification(
                $serviceRequest,
                "Your request has been accepted by a provider: {$serviceRequest->title}",
                'accepted_customer'
            ));
        }

        return response()->json([
            'message' => 'Service request accepted successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Provider marks an accepted request as work done
     */
    public function workDone(Request $request, $id, MainService $mainService): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        if ($serviceRequest->provider_id !== $request->user()->id || $serviceRequest->status !== 'accepted') {
            abort(403, 'Unauthorized sequence');
        }

        $serviceRequest = $mainService->workDoneServiceRequest($serviceRequest);
        ServiceRequestCompleted::dispatch($serviceRequest);
        ServiceRequestUpdated::dispatch($serviceRequest, 'work_done');

        // Notify Customer
        if ($serviceRequest->customer) {
            $serviceRequest->customer->notify(new RequestStatusNotification(
                $serviceRequest,
                "Provider marked work as done for: {$serviceRequest->title}",
                'work_done_customer'
            ));
        }

        // Notify Admin
        Notification::send(User::role('admin')->get(), new RequestStatusNotification(
            $serviceRequest,
            "Work marked as done for request: {$serviceRequest->title}",
            'work_done_admin'
        ));

        return response()->json([
            'message' => 'Service request work marked as done',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Customer marks a request as completed (approved)
     */
    public function complete(Request $request, $id, MainService $mainService): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);

        // Allow BOTH customer and provider to complete, AND Admin
        $user = $request->user();
        $isCustomer = $serviceRequest->customer_id === $user->id;
        $isProvider = $serviceRequest->provider_id === $user->id;
        $isAdmin = $user->hasRole('admin');

        if ((!$isCustomer && !$isProvider && !$isAdmin) || ($serviceRequest->status !== 'accepted' && $serviceRequest->status !== 'work_done')) {
            abort(403, 'Unauthorized sequence');
        }

        $serviceRequest = $mainService->completeServiceRequest($serviceRequest);
        ServiceRequestUpdated::dispatch($serviceRequest, 'completed');

        // Notify Provider
        if ($serviceRequest->provider) {
            $serviceRequest->provider->notify(new RequestStatusNotification(
                $serviceRequest,
                "Request officially completed by customer: {$serviceRequest->title}",
                'completed_provider'
            ));
        }

        // Notify Admin
        Notification::send(User::role('admin')->get(), new RequestStatusNotification(
            $serviceRequest,
            "Service request completed: {$serviceRequest->title}",
            'completed_admin'
        ));

        return response()->json([
            'message' => 'Service request officially completed',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Customer cancels a request
     */
    public function cancel(Request $request, $id, MainService $mainService): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        if ($serviceRequest->customer_id !== $request->user()->id || $serviceRequest->status !== 'pending') {
            abort(403, 'Only pending requests can be cancelled.');
        }

        $serviceRequest = $mainService->cancelServiceRequest($serviceRequest);
        ServiceRequestUpdated::dispatch($serviceRequest, 'cancelled');

        // Notify Admin
        Notification::send(User::role('admin')->get(), new RequestStatusNotification(
            $serviceRequest,
            "Service request was cancelled by customer: {$serviceRequest->title}",
            'cancelled_admin'
        ));

        return response()->json([
            'message' => 'Service request cancelled successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Provider drops a request
     */
    public function drop(Request $request, $id, MainService $mainService): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        if ($serviceRequest->provider_id !== $request->user()->id || $serviceRequest->status !== 'accepted') {
            abort(403, 'Unauthorized sequence.');
        }

        $serviceRequest = $mainService->dropServiceRequest($serviceRequest);
        ServiceRequestUpdated::dispatch($serviceRequest, 'dropped');

        // Notify Customer
        if ($serviceRequest->customer) {
            $serviceRequest->customer->notify(new RequestStatusNotification(
                $serviceRequest,
                "Provider has dropped your request: {$serviceRequest->title}. It is now pending again.",
                'dropped_customer'
            ));
        }

        // Notify Admin
        Notification::send(User::role('admin')->get(), new RequestStatusNotification(
            $serviceRequest,
            "Service request dropped by provider: {$serviceRequest->title}",
            'dropped_admin'
        ));

        return response()->json([
            'message' => 'Service request dropped successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Update an existing service request
     */
    public function update(UpdateServiceRequestRequest $request, $id): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        // Use policy for authorization (Policy has 'before' method for admins)
        $this->authorize('update', $serviceRequest);

        $serviceRequest->update($request->only([
            'title',
            'description',
            'latitude',
            'longitude',
            'category',
            'urgency',
            'business_area',
            'status', // Allow status update
            'provider_id'
        ]));

        ServiceRequestUpdated::dispatch($serviceRequest, 'updated');

        return response()->json([
            'success' => true,
            'message' => 'Service request updated successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Delete a service request
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $serviceRequest = ServiceRequest::findOrFail($id);
        try {
            // Use policy for authorization
            $this->authorize('delete', $serviceRequest);

            // Safely delete attachments first (ignore if relation doesn't exist)
            try {
                $serviceRequest->attachments()->delete();
            } catch (\Exception $e) {
                Log::warning("Could not delete attachments for request #{$serviceRequest->id}: " . $e->getMessage());
            }

            // Perform the deletion
            $serviceRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service request deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error("Delete failed for request #{$serviceRequest->id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
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

        $query = ServiceRequest::with(['customer', 'provider'])->nearby($lat, $lng, $radius);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        $query->where('customer_id', '!=', $user->id);

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
            $apiKey = config('services.gemini.api_key');

            if (!$apiKey || $apiKey === 'your_gemini_api_key_here') {
                throw new \Exception('Gemini API key not configured.');
            }

            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->post('https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => "Enhance this service request description for clarity and professionalism. Keep the original meaning but make it sound more like a professional work order. Return ONLY the enhanced text: " . $serviceRequest->description
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $serviceRequest->update([
                    'description' => trim($data['candidates'][0]['content']['parts'][0]['text'])
                ]);
                return;
            }
        } catch (\Exception $e) {
            \Log::warning('External AI enhancement failed, using local fallback: ' . $e->getMessage());
        }

        // Local Fallback: apply a static professionalizer
        $original = $serviceRequest->description;
        $replacements = [
            'fix' => 'repair and restore',
            'broken' => 'malfunctioning',
            'help' => 'assistance required for',
            'wont' => 'fails to',
            'bad' => 'non-functional',
            'fast' => 'as soon as possible',
        ];

        $enhanced = str_ireplace(array_keys($replacements), array_values($replacements), $original);
        $enhanced = ucfirst(trim($enhanced));
        if (!fnmatch('*[.!]', $enhanced)) {
            $enhanced .= '.';
        }

        $serviceRequest->update([
            'description' => "Professional Request: {$enhanced}"
        ]);
    }
}
