<?php

namespace App\Repositories;

use App\Models\ServiceRequest;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceRequestRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct(ServiceRequest $model)
    {
        parent::__construct($model);
    }

    /**
     * Get service requests for a specific customer.
     */
    public function findByCustomer(int $customerId): Collection
    {
        return $this->query
            ->where('customer_id', $customerId)
            ->with(['customer', 'provider', 'attachments'])
            ->get();
    }

    /**
     * Get service requests for a specific provider.
     */
    public function findByProvider(int $providerId): Collection
    {
        return $this->query
            ->where('provider_id', $providerId)
            ->with(['customer', 'provider', 'attachments'])
            ->get();
    }

    /**
     * Get pending service requests.
     */
    public function findPending(): Collection
    {
        return $this->query
            ->where('status', 'pending')
            ->with(['customer', 'attachments'])
            ->get();
    }

    /**
     * Get nearby service requests.
     */
    public function findNearby(float $latitude, float $longitude, float $radius = 50): Collection
    {
        // Using Haversine formula for distance calculation
        return $this->query
            ->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radius)
            ->where('status', 'pending')
            ->with(['customer', 'attachments'])
            ->orderBy('distance')
            ->get();
    }

    /**
     * Get service requests by status.
     */
    public function findByStatus(string $status): Collection
    {
        return $this->query
            ->where('status', $status)
            ->with(['customer', 'provider', 'attachments'])
            ->get();
    }

    /**
     * Get service requests by category.
     */
    public function findByCategory(string $category): Collection
    {
        return $this->query
            ->whereJsonContains('metadata->category', $category)
            ->with(['customer', 'provider', 'attachments'])
            ->get();
    }

    /**
     * Get service requests within date range.
     */
    public function findByDateRange(string $startDate, string $endDate): Collection
    {
        return $this->query
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['customer', 'provider', 'attachments'])
            ->get();
    }

    /**
     * Get service requests with filters applied.
     */
    public function getWithFilters(array $filters): LengthAwarePaginator
    {
        $query = $this->query->with(['customer', 'provider', 'attachments']);

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply customer filter
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        // Apply provider filter
        if (!empty($filters['provider_id'])) {
            $query->where('provider_id', $filters['provider_id']);
        }

        // Apply category filter
        if (!empty($filters['category'])) {
            $query->whereJsonContains('metadata->category', $filters['category']);
        }

        // Apply date range filter
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        // Apply location filter
        if (!empty($filters['latitude']) && !empty($filters['longitude'])) {
            $radius = $filters['radius'] ?? 50;
            $query->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', 
                [$filters['latitude'], $filters['longitude'], $filters['latitude']])
                ->having('distance', '<=', $radius);
        }

        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get statistics for service requests.
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->query->count(),
            'by_status' => $this->query->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_category' => $this->query->selectRaw('JSON_EXTRACT(metadata, "$.category") as category, COUNT(*) as count')
                ->whereNotNull('metadata->category')
                ->groupBy('category')
                ->pluck('count', 'category')
                ->toArray(),
            'this_month' => $this->query->whereMonth('created_at', now()->month)->count(),
            'this_year' => $this->query->whereYear('created_at', now()->year)->count(),
        ];
    }

    /**
     * Update service request status.
     */
    public function updateStatus(int $id, string $status, ?int $providerId = null): ServiceRequest
    {
        $serviceRequest = $this->findOrFail($id);
        
        $updateData = ['status' => $status];
        
        if ($providerId && in_array($status, ['accepted', 'completed'])) {
            $updateData['provider_id'] = $providerId;
        }
        
        $serviceRequest->update($updateData);
        
        return $serviceRequest;
    }

    /**
     * Get service requests with attachments.
     */
    public function withAttachments(): Collection
    {
        return $this->query
            ->has('attachments')
            ->with(['customer', 'provider', 'attachments'])
            ->get();
    }

    /**
     * Search service requests by title or description.
     */
    public function search(string $term): Collection
    {
        return $this->query
            ->where(function ($query) use ($term) {
                $query->where('title', 'LIKE', "%{$term}%")
                      ->orWhere('description', 'LIKE', "%{$term}%");
            })
            ->with(['customer', 'provider', 'attachments'])
            ->get();
    }

    /**
     * Get service requests that need attention (overdue, etc.).
     */
    public function getNeedingAttention(): Collection
    {
        return $this->query
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(3))
            ->with(['customer', 'attachments'])
            ->get();
    }

    /**
     * Filter by status.
     */
    protected function filterByStatus(string $status): void
    {
        $this->query->where('status', $status);
    }

    /**
     * Filter by customer.
     */
    protected function filterByCustomer(int $customerId): void
    {
        $this->query->where('customer_id', $customerId);
    }

    /**
     * Filter by provider.
     */
    protected function filterByProvider(int $providerId): void
    {
        $this->query->where('provider_id', $providerId);
    }

    /**
     * Filter by category.
     */
    protected function filterByCategory(string $category): void
    {
        $this->query->whereJsonContains('metadata->category', $category);
    }

    /**
     * Filter by date range.
     */
    protected function filterByDateRange(array $dates): void
    {
        if (!empty($dates['start'])) {
            $this->query->whereDate('created_at', '>=', $dates['start']);
        }
        
        if (!empty($dates['end'])) {
            $this->query->whereDate('created_at', '<=', $dates['end']);
        }
    }
}
