<?php

namespace App\Services;

use App\Repositories\ServiceRequestRepository;
use App\Services\Contracts\ServiceInterface;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Events\ServiceRequestCreated;
use App\Events\ServiceRequestAccepted;
use App\Events\ServiceRequestCompleted;
use App\Jobs\ProcessAIEnhancementJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ServiceRequestService implements ServiceInterface
{
    protected ServiceRequestRepository $repository;

    public function __construct(ServiceRequestRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all service requests.
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->repository->getWithFilters($filters)->getCollection();
    }

    /**
     * Get paginated service requests.
     */
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->getWithFilters($filters);
    }

    /**
     * Find service request by ID.
     */
    public function findById(int $id): ?ServiceRequest
    {
        return $this->repository->find($id);
    }

    /**
     * Create new service request.
     */
    public function create(array $data): ServiceRequest
    {
        $this->validate($data);

        $this->repository->beginTransaction();

        try {
            $serviceRequest = $this->repository->create([
                'customer_id' => $data['customer_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'status' => 'pending',
                'metadata' => [
                    'category' => $data['category'] ?? null,
                    'urgency' => $data['urgency'] ?? 'normal',
                ],
            ]);

            // Fire event for service request creation
            event(new ServiceRequestCreated($serviceRequest, $data['metadata'] ?? []));

            // Queue AI enhancement job (if requested)
            if (!empty($data['enhance_with_ai'])) {
                ProcessAIEnhancementJob::dispatch(
                    $serviceRequest,
                    $data['title'],
                    $data['description'],
                    $data['customer_id']
                );
            }

            $this->repository->commit();

            return $serviceRequest->load(['customer', 'attachments']);

        } catch (\Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Update existing service request.
     */
    public function update(int $id, array $data): ServiceRequest
    {
        $this->validate($data, $id);

        $serviceRequest = $this->repository->findOrFail($id);

        $this->repository->beginTransaction();

        try {
            $serviceRequest->update($data);
            $this->repository->commit();

            return $serviceRequest->load(['customer', 'provider', 'attachments']);

        } catch (\Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Delete service request.
     */
    public function delete(int $id): bool
    {
        $serviceRequest = $this->repository->findOrFail($id);

        // Business rule: Cannot delete accepted or completed requests
        if (in_array($serviceRequest->status, ['accepted', 'completed'])) {
            throw new \InvalidArgumentException('Cannot delete accepted or completed service requests.');
        }

        return $this->repository->delete($id);
    }

    /**
     * Accept service request.
     */
    public function accept(int $id, int $providerId, array $data = []): ServiceRequest
    {
        $serviceRequest = $this->repository->findOrFail($id);

        // Business rules for acceptance
        $this->validateAcceptance($serviceRequest, $providerId);

        $this->repository->beginTransaction();

        try {
            $serviceRequest = $this->repository->updateStatus($id, 'accepted', $providerId);

            // Update additional acceptance data
            if (!empty($data)) {
                $serviceRequest->update([
                    'metadata' => array_merge($serviceRequest->metadata ?? [], [
                        'provider_notes' => $data['provider_notes'] ?? null,
                        'estimated_completion' => $data['estimated_completion'] ?? null,
                    ])
                ]);
            }

            // Fire event for service request acceptance
            event(new ServiceRequestAccepted($serviceRequest, $providerId, $data));

            $this->repository->commit();

            return $serviceRequest->load(['customer', 'provider', 'attachments']);

        } catch (\Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Complete service request.
     */
    public function complete(int $id, array $data = []): ServiceRequest
    {
        $serviceRequest = $this->repository->findOrFail($id);

        // Business rules for completion
        $this->validateCompletion($serviceRequest);

        $this->repository->beginTransaction();

        try {
            $serviceRequest = $this->repository->updateStatus($id, 'completed');

            // Update completion data
            if (!empty($data)) {
                $serviceRequest->update([
                    'metadata' => array_merge($serviceRequest->metadata ?? [], [
                        'completion_notes' => $data['completion_notes'] ?? null,
                        'final_attachments' => $data['final_attachments'] ?? [],
                        'rating' => $data['rating'] ?? null,
                        'completed_at' => now()->toISOString(),
                    ])
                ]);
            }

            // Fire event for service request completion
            event(new ServiceRequestCompleted($serviceRequest, $data));

            $this->repository->commit();

            return $serviceRequest->load(['customer', 'provider', 'attachments']);

        } catch (\Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Get nearby service requests.
     */
    public function getNearby(float $latitude, float $longitude, float $radius = 50): Collection
    {
        return $this->repository->findNearby($latitude, $longitude, $radius);
    }

    /**
     * Get service requests for customer.
     */
    public function getForCustomer(int $customerId): Collection
    {
        return $this->repository->findByCustomer($customerId);
    }

    /**
     * Get service requests for provider.
     */
    public function getForProvider(int $providerId): Collection
    {
        return $this->repository->findByProvider($providerId);
    }

    /**
     * Search service requests.
     */
    public function search(string $term): Collection
    {
        return $this->repository->search($term);
    }

    /**
     * Get service requests needing attention.
     */
    public function getNeedingAttention(): Collection
    {
        return $this->repository->getNeedingAttention();
    }

    /**
     * Validate business rules.
     */
    public function validate(array $data, ?int $id = null): array
    {
        $rules = [
            'customer_id' => 'required|exists:users,id',
            'title' => 'required|string|min:5|max:255',
            'description' => 'required|string|min:20|max:2000',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'category' => 'nullable|string|max:50',
            'urgency' => 'nullable|in:low,normal,high,urgent',
        ];

        if ($id) {
            // For updates, make some fields optional
            $rules = array_intersect_key($rules, $data);
        }

        $validator = Validator::make($data, $rules, [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Selected customer does not exist.',
            'title.required' => 'Title is required.',
            'title.min' => 'Title must be at least 5 characters.',
            'title.max' => 'Title may not exceed 255 characters.',
            'description.required' => 'Description is required.',
            'description.min' => 'Description must be at least 20 characters.',
            'description.max' => 'Description may not exceed 2000 characters.',
            'latitude.required' => 'Latitude is required.',
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.required' => 'Longitude is required.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Validate service request acceptance.
     */
    protected function validateAcceptance(ServiceRequest $serviceRequest, int $providerId): void
    {
        // Can only accept pending requests
        if ($serviceRequest->status !== 'pending') {
            throw new \InvalidArgumentException('Only pending requests can be accepted.');
        }

        // Provider cannot accept their own request
        if ($serviceRequest->customer_id === $providerId) {
            throw new \InvalidArgumentException('You cannot accept your own service request.');
        }

        // Check if provider exists and has appropriate role
        $provider = User::find($providerId);
        if (!$provider || !$provider->hasRole(['provider_admin', 'provider_employee'])) {
            throw new \InvalidArgumentException('Invalid provider.');
        }
    }

    /**
     * Validate service request completion.
     */
    protected function validateCompletion(ServiceRequest $serviceRequest): void
    {
        // Can only complete accepted requests
        if ($serviceRequest->status !== 'accepted') {
            throw new \InvalidArgumentException('Only accepted requests can be completed.');
        }

        // Check if it's been at least 1 hour since acceptance (business rule)
        if ($serviceRequest->updated_at->diffInHours(now()) < 1) {
            throw new \InvalidArgumentException('Service request must be accepted for at least 1 hour before completion.');
        }
    }

    /**
     * Get resource statistics.
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     * Get service request analytics.
     */
    public function getAnalytics(array $filters = []): array
    {
        $baseQuery = $this->repository->query();

        // Apply date filters
        if (!empty($filters['start_date'])) {
            $baseQuery->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $baseQuery->whereDate('created_at', '<=', $filters['end_date']);
        }

        return [
            'total_requests' => $baseQuery->count(),
            'by_status' => $baseQuery->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_category' => $baseQuery->selectRaw('JSON_EXTRACT(metadata, "$.category") as category, COUNT(*) as count')
                ->whereNotNull('metadata->category')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'by_urgency' => $baseQuery->selectRaw('JSON_EXTRACT(metadata, "$.urgency") as urgency, COUNT(*) as count')
                ->whereNotNull('metadata->urgency')
                ->groupBy('urgency')
                ->pluck('count', 'urgency')
                ->toArray(),
            'average_completion_time' => $this->calculateAverageCompletionTime($baseQuery),
            'top_categories' => $this->getTopCategories($baseQuery),
        ];
    }

    /**
     * Calculate average completion time.
     */
    protected function calculateAverageCompletionTime($query): float
    {
        $completed = $query->where('status', 'completed')->get();
        
        if ($completed->isEmpty()) {
            return 0;
        }

        $totalTime = $completed->sum(function ($request) {
            return $request->created_at->diffInHours($request->updated_at);
        });

        return round($totalTime / $completed->count(), 2);
    }

    /**
     * Get top categories.
     */
    protected function getTopCategories($query): array
    {
        return $query->selectRaw('JSON_EXTRACT(metadata, "$.category") as category, COUNT(*) as count')
            ->whereNotNull('metadata->category')
            ->groupBy('category')
            ->orderByDesc('count')
            ->limit(5)
            ->pluck('count', 'category')
            ->toArray();
    }
}
