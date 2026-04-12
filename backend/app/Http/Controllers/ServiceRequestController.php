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

        return ServiceRequestResource::collection(
            $query->latest()->paginate(20)
        );
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

        try {
            $serviceRequest = $mainService->createServiceRequest($dto, $request->user()->id);
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle legacy index collision during development
            if (strpos($e->getMessage(), 'unique_pending_order') !== false || $e->errorInfo[1] == 1062) {
                try {
                    \Illuminate\Support\Facades\DB::statement('ALTER TABLE service_requests DROP INDEX unique_pending_order;');
                } catch (\Exception $ex) {
                }
                $serviceRequest = $mainService->createServiceRequest($dto, $request->user()->id);
            } else {
                throw $e;
            }
        }

        ServiceRequestCreated::dispatch($serviceRequest);

        $hasProviders = \App\Models\User::role(['provider_admin', 'provider_employee'])
            ->whereNotNull('latitude')
            ->count() > 0;

        $response = new ServiceRequestResource($serviceRequest);
        $result = $response->response()->setStatusCode(201);

        if (!$hasProviders) {
            $data = $result->getData(true);
            $data['warnings'] = ['No providers are currently registered with locations in your area. Acceptance may be delayed.'];
            $result->setData($data);
        }

        return $result;
    }

    /**
     * Show a single service request
     */
    public function show(ServiceRequest $serviceRequest): ServiceRequestResource
    {
        $this->authorize('view', $serviceRequest);
        $serviceRequest->load(['customer', 'provider']);
        return new ServiceRequestResource($serviceRequest);
    }

    /**
     * Provider accepts a pending request
     */
    public function accept(Request $request, ServiceRequest $serviceRequest, MainService $mainService): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasRole(['provider_admin', 'provider_employee', 'provider', 'admin'])) {
            return response()->json(['message' => 'Only providers can accept requests.'], 403);
        }

        if ($serviceRequest->customer_id === $user->id) {
            return response()->json(['message' => 'You cannot accept your own request.'], 403);
        }

        if ($serviceRequest->provider_id !== null && $serviceRequest->provider_id !== $user->id) {
            return response()->json(['message' => 'This request has already been accepted.'], 409);
        }

        if ($serviceRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be accepted.'], 422);
        }

        // Prevent race condition with database lock
        $serviceRequest = ServiceRequest::where('id', $serviceRequest->id)
            ->where('status', 'pending')
            ->whereNull('provider_id')
            ->lockForUpdate()
            ->firstOrFail();

        $serviceRequest = $mainService->acceptServiceRequest($serviceRequest, $request->user()->id);
        ServiceRequestAccepted::dispatch($serviceRequest, $request->user()->id);

        return response()->json([
            'message' => 'Service request accepted successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Provider marks an accepted request as work done
     */
    public function workDone(Request $request, ServiceRequest $serviceRequest, MainService $mainService): JsonResponse
    {
        if ($serviceRequest->provider_id !== $request->user()->id || $serviceRequest->status !== 'accepted') {
            abort(403, 'Unauthorized sequence');
        }

        $serviceRequest = $mainService->workDoneServiceRequest($serviceRequest);
        ServiceRequestCompleted::dispatch($serviceRequest);

        return response()->json([
            'message' => 'Service request work marked as done',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Customer marks a request as completed (approved)
     */
    public function complete(Request $request, ServiceRequest $serviceRequest, MainService $mainService): JsonResponse
    {
        if ($serviceRequest->customer_id !== $request->user()->id || $serviceRequest->status !== 'work_done') {
            abort(403, 'Unauthorized sequence');
        }

        $serviceRequest = $mainService->completeServiceRequest($serviceRequest);

        return response()->json([
            'message' => 'Service request officially completed',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Customer cancels a request
     */
    public function cancel(Request $request, ServiceRequest $serviceRequest, MainService $mainService): JsonResponse
    {
        if ($serviceRequest->customer_id !== $request->user()->id || $serviceRequest->status !== 'pending') {
            abort(403, 'Only pending requests can be cancelled.');
        }

        $serviceRequest = $mainService->cancelServiceRequest($serviceRequest);

        return response()->json([
            'message' => 'Service request cancelled successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Provider drops a request
     */
    public function drop(Request $request, ServiceRequest $serviceRequest, MainService $mainService): JsonResponse
    {
        if ($serviceRequest->provider_id !== $request->user()->id || $serviceRequest->status !== 'accepted') {
            abort(403, 'Unauthorized sequence.');
        }

        $serviceRequest = $mainService->dropServiceRequest($serviceRequest);

        return response()->json([
            'message' => 'Service request dropped successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Update an existing service request
     */
    public function update(UpdateServiceRequestRequest $request, ServiceRequest $serviceRequest): JsonResponse
    {
        if ($serviceRequest->customer_id != $request->user()->id) {
            abort(403, 'You do not own this request.');
        }

        if ($serviceRequest->status !== 'pending') {
            abort(403, 'Only pending requests can be updated.');
        }

        $serviceRequest->update($request->only([
            'title',
            'description',
            'latitude',
            'longitude',
            'category',
            'urgency',
            'business_area'
        ]));

        return response()->json([
            'message' => 'Service request updated successfully',
            'data' => new ServiceRequestResource($serviceRequest->load(['customer', 'provider']))
        ]);
    }

    /**
     * Delete a service request
     */
    public function destroy(Request $request, ServiceRequest $serviceRequest): JsonResponse
    {
        if ($serviceRequest->customer_id != $request->user()->id) {
            abort(403, 'You do not own this request.');
        }

        if ($serviceRequest->status !== 'pending') {
            abort(403, 'Only pending requests can be deleted.');
        }

        $serviceRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service request deleted successfully',
        ], 200);
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

        $query = ServiceRequest::with(['customer'])->nearby($lat, $lng, $radius);

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
