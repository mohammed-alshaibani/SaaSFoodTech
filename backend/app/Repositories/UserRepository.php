<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository implements RepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->query->where('email', $email)->first();
    }

    /**
     * Find users by role.
     */
    public function findByRole(string $role): Collection
    {
        return $this->query
            ->role($role)
            ->with(['roles', 'permissions'])
            ->get();
    }

    /**
     * Find users by plan.
     */
    public function findByPlan(string $plan): Collection
    {
        return $this->query
            ->where('plan', $plan)
            ->with(['roles', 'permissions'])
            ->get();
    }

    /**
     * Get active providers.
     */
    public function getActiveProviders(): Collection
    {
        return $this->query
            ->role(['provider_admin', 'provider_employee'])
            ->with(['roles', 'permissions'])
            ->whereHas('serviceRequests', function ($query) {
                $query->whereIn('status', ['accepted', 'completed']);
            })
            ->get();
    }

    /**
     * Get customers with service requests.
     */
    public function getCustomersWithRequests(): Collection
    {
        return $this->query
            ->role('customer')
            ->with(['roles', 'permissions', 'serviceRequests'])
            ->has('serviceRequests')
            ->get();
    }

    /**
     * Get user statistics.
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->query->count(),
            'by_plan' => $this->query->selectRaw('plan, COUNT(*) as count')
                ->groupBy('plan')
                ->pluck('count', 'plan')
                ->toArray(),
            'by_role' => $this->query->select('roles.name')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', User::class)
                ->groupBy('roles.name')
                ->selectRaw('roles.name, COUNT(*) as count')
                ->pluck('count', 'name')
                ->toArray(),
            'this_month' => $this->query->whereMonth('created_at', now()->month)->count(),
            'this_year' => $this->query->whereYear('created_at', now()->year)->count(),
            'active_providers' => $this->getActiveProviders()->count(),
            'customers_with_requests' => $this->getCustomersWithRequests()->count(),
        ];
    }

    /**
     * Search users by name or email.
     */
    public function search(string $term): Collection
    {
        return $this->query
            ->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', "%{$term}%")
                      ->orWhere('email', 'LIKE', "%{$term}%");
            })
            ->with(['roles', 'permissions'])
            ->get();
    }

    /**
     * Get users with filters applied.
     */
    public function getWithFilters(array $filters): LengthAwarePaginator
    {
        $query = $this->query->with(['roles', 'permissions']);

        // Apply role filter
        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }

        // Apply plan filter
        if (!empty($filters['plan'])) {
            $query->where('plan', $filters['plan']);
        }

        // Apply email filter
        if (!empty($filters['email'])) {
            $query->where('email', 'LIKE', "%{$filters['email']}%");
        }

        // Apply name filter
        if (!empty($filters['name'])) {
            $query->where('name', 'LIKE', "%{$filters['name']}%");
        }

        // Apply date range filter
        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Update user plan.
     */
    public function updatePlan(int $id, string $plan): User
    {
        $user = $this->findOrFail($id);
        $user->update(['plan' => $plan]);
        
        return $user;
    }

    /**
     * Get users by registration date.
     */
    public function findByRegistrationDate(string $date): Collection
    {
        return $this->query
            ->whereDate('created_at', $date)
            ->with(['roles', 'permissions'])
            ->get();
    }

    /**
     * Get users with specific permissions.
     */
    public function findByPermission(string $permission): Collection
    {
        return $this->query
            ->permission($permission)
            ->with(['roles', 'permissions'])
            ->get();
    }

    /**
     * Get inactive users (no activity in last 30 days).
     */
    public function getInactiveUsers(): Collection
    {
        return $this->query
            ->where('last_login_at', '<', now()->subDays(30))
            ->orWhereNull('last_login_at')
            ->with(['roles', 'permissions'])
            ->get();
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(int $id): User
    {
        $user = $this->findOrFail($id);
        $user->update(['last_login_at' => now()]);
        
        return $user;
    }

    /**
     * Filter by role.
     */
    protected function filterByRole(string $role): void
    {
        $this->query->role($role);
    }

    /**
     * Filter by plan.
     */
    protected function filterByPlan(string $plan): void
    {
        $this->query->where('plan', $plan);
    }

    /**
     * Filter by email.
     */
    protected function filterByEmail(string $email): void
    {
        $this->query->where('email', 'LIKE', "%{$email}%");
    }

    /**
     * Filter by name.
     */
    protected function filterByName(string $name): void
    {
        $this->query->where('name', 'LIKE', "%{$name}%");
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

    /**
     * Get users with their service request counts.
     */
    public function withServiceRequestCounts(): Collection
    {
        return $this->query
            ->with(['roles', 'permissions'])
            ->withCount(['serviceRequests'])
            ->get();
    }

    /**
     * Get top providers by completed requests.
     */
    public function getTopProviders(int $limit = 10): Collection
    {
        return $this->query
            ->role(['provider_admin', 'provider_employee'])
            ->with(['roles', 'permissions'])
            ->withCount(['serviceRequests' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->orderByDesc('service_requests_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent users.
     */
    public function getRecent(int $limit = 10): Collection
    {
        return $this->query
            ->with(['roles', 'permissions'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}
