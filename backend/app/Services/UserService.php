<?php

namespace App\Services;

use App\Repositories\UserRepository;
use App\Services\Contracts\ServiceInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService implements ServiceInterface
{
    protected UserRepository $repository;

    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get all users.
     */
    public function getAll(array $filters = []): Collection
    {
        return $this->repository->getWithFilters($filters)->getCollection();
    }

    /**
     * Get paginated users.
     */
    public function getPaginated(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->getWithFilters($filters);
    }

    /**
     * Find user by ID.
     */
    public function findById(int $id): ?User
    {
        return $this->repository->find($id);
    }

    /**
     * Create new user.
     */
    public function create(array $data): User
    {
        $this->validate($data);

        $this->repository->beginTransaction();

        try {
            $user = $this->repository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'plan' => $data['plan'] ?? 'free',
                'phone' => $data['phone'] ?? null,
                'company_name' => $data['company_name'] ?? null,
            ]);

            // Assign role based on request
            $role = $data['role'] ?? 'customer';
            if ($role === 'provider') {
                $user->assignRole('provider_admin');
            } else {
                $user->assignRole($role);
            }

            $this->repository->commit();

            return $user->load(['roles', 'permissions']);

        } catch (\Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Update existing user.
     */
    public function update(int $id, array $data): User
    {
        $this->validate($data, $id);

        $user = $this->repository->findOrFail($id);

        $this->repository->beginTransaction();

        try {
            // Don't update password if not provided
            if (empty($data['password'])) {
                unset($data['password']);
            } else {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            // Update role if provided
            if (!empty($data['role'])) {
                $user->syncRoles([]);
                if ($data['role'] === 'provider') {
                    $user->assignRole('provider_admin');
                } else {
                    $user->assignRole($data['role']);
                }
            }

            $this->repository->commit();

            return $user->load(['roles', 'permissions']);

        } catch (\Exception $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    /**
     * Delete user.
     */
    public function delete(int $id): bool
    {
        $user = $this->repository->findOrFail($id);

        // Business rule: Cannot delete users with active service requests
        if ($user->serviceRequests()->whereIn('status', ['pending', 'accepted'])->exists()) {
            throw new \InvalidArgumentException('Cannot delete user with active service requests.');
        }

        return $this->repository->delete($id);
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->repository->findByEmail($email);
    }

    /**
     * Authenticate user.
     */
    public function authenticate(string $email, string $password): ?User
    {
        $user = $this->findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        // Update last login
        $this->repository->updateLastLogin($user->id);

        return $user->load(['roles', 'permissions']);
    }

    /**
     * Update user plan.
     */
    public function updatePlan(int $id, string $plan): User
    {
        // Validate plan
        if (!in_array($plan, ['free', 'basic', 'premium', 'enterprise'])) {
            throw new \InvalidArgumentException('Invalid plan type.');
        }

        return $this->repository->updatePlan($id, $plan);
    }

    /**
     * Get users by role.
     */
    public function getByRole(string $role): Collection
    {
        return $this->repository->findByRole($role);
    }

    /**
     * Get users by plan.
     */
    public function getByPlan(string $plan): Collection
    {
        return $this->repository->findByPlan($plan);
    }

    /**
     * Get active providers.
     */
    public function getActiveProviders(): Collection
    {
        return $this->repository->getActiveProviders();
    }

    /**
     * Get customers with service requests.
     */
    public function getCustomersWithRequests(): Collection
    {
        return $this->repository->getCustomersWithRequests();
    }

    /**
     * Search users.
     */
    public function search(string $term): Collection
    {
        return $this->repository->search($term);
    }

    /**
     * Get inactive users.
     */
    public function getInactiveUsers(): Collection
    {
        return $this->repository->getInactiveUsers();
    }

    /**
     * Get top providers.
     */
    public function getTopProviders(int $limit = 10): Collection
    {
        return $this->repository->getTopProviders($limit);
    }

    /**
     * Get recent users.
     */
    public function getRecent(int $limit = 10): Collection
    {
        return $this->repository->getRecent($limit);
    }

    /**
     * Validate business rules.
     */
    public function validate(array $data, ?int $id = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email' . ($id ? ",{$id}" : ''),
            'password' => $id ? 'nullable|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/' : 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            'plan' => 'nullable|in:free,basic,premium,enterprise',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'role' => 'nullable|in:customer,provider,provider_admin,provider_employee,admin',
        ];

        $messages = [
            'name.required' => 'Name is required.',
            'name.max' => 'Name may not exceed 255 characters.',
            'email.required' => 'Email is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already in use.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'plan.in' => 'Invalid plan type.',
            'role.in' => 'Invalid role type.',
        ];

        $validator = Validator::make($data, $rules, $messages);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Get resource statistics.
     */
    public function getStatistics(): array
    {
        return $this->repository->getStatistics();
    }

    /**
     * Get user analytics.
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
            'total_users' => $baseQuery->count(),
            'by_plan' => $baseQuery->selectRaw('plan, COUNT(*) as count')
                ->groupBy('plan')
                ->pluck('count', 'plan')
                ->toArray(),
            'by_role' => $baseQuery->select('roles.name')
                ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                ->where('model_has_roles.model_type', User::class)
                ->groupBy('roles.name')
                ->selectRaw('roles.name, COUNT(*) as count')
                ->pluck('count', 'name')
                ->toArray(),
            'new_this_month' => $baseQuery->whereMonth('created_at', now()->month)->count(),
            'new_this_year' => $baseQuery->whereYear('created_at', now()->year)->count(),
            'active_this_month' => $baseQuery->where('last_login_at', '>=', now()->subMonth())->count(),
            'inactive_users' => $baseQuery->where('last_login_at', '<', now()->subDays(30))->orWhereNull('last_login_at')->count(),
            'conversion_rate' => $this->calculateConversionRate($baseQuery),
        ];
    }

    /**
     * Calculate user conversion rate (customers vs providers).
     */
    protected function calculateConversionRate($query): float
    {
        $total = $query->count();
        
        if ($total === 0) {
            return 0;
        }

        $customers = $query->role('customer')->count();
        $providers = $query->role(['provider_admin', 'provider_employee'])->count();

        return round(($customers + $providers) / $total * 100, 2);
    }

    /**
     * Get user engagement metrics.
     */
    public function getEngagementMetrics(): array
    {
        return [
            'daily_active_users' => $this->repository->query()
                ->where('last_login_at', '>=', now()->subDay())
                ->count(),
            'weekly_active_users' => $this->repository->query()
                ->where('last_login_at', '>=', now()->subWeek())
                ->count(),
            'monthly_active_users' => $this->repository->query()
                ->where('last_login_at', '>=', now()->subMonth())
                ->count(),
            'average_session_duration' => $this->calculateAverageSessionDuration(),
            'user_retention_rate' => $this->calculateRetentionRate(),
        ];
    }

    /**
     * Calculate average session duration (placeholder).
     */
    protected function calculateAverageSessionDuration(): float
    {
        // In a real implementation, you'd track login/logout times
        // For now, return a placeholder value
        return 25.5; // minutes
    }

    /**
     * Calculate user retention rate.
     */
    protected function calculateRetentionRate(): float
    {
        $totalUsers = $this->repository->query()->count();
        
        if ($totalUsers === 0) {
            return 0;
        }

        $activeUsers = $this->repository->query()
            ->where('last_login_at', '>=', now()->subMonth())
            ->count();

        return round($activeUsers / $totalUsers * 100, 2);
    }

    /**
     * Deactivate user account.
     */
    public function deactivate(int $id): User
    {
        $user = $this->repository->findOrFail($id);

        // Business rule: Cannot deactivate users with active service requests
        if ($user->serviceRequests()->whereIn('status', ['pending', 'accepted'])->exists()) {
            throw new \InvalidArgumentException('Cannot deactivate user with active service requests.');
        }

        $user->update(['active' => false]);

        return $user;
    }

    /**
     * Reactivate user account.
     */
    public function reactivate(int $id): User
    {
        $user = $this->repository->findOrFail($id);
        $user->update(['active' => true]);

        return $user;
    }
}
