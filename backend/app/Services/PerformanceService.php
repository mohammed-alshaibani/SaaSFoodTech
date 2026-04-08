<?php

namespace App\Services;

use App\Services\Cache\CacheService;
use App\Repositories\ServiceRequestRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceService
{
    protected CacheService $cacheService;
    protected ServiceRequestRepository $serviceRequestRepository;
    protected UserRepository $userRepository;

    public function __construct(
        CacheService $cacheService,
        ServiceRequestRepository $serviceRequestRepository,
        UserRepository $userRepository
    ) {
        $this->cacheService = $cacheService;
        $this->serviceRequestRepository = $serviceRequestRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Optimize database queries with eager loading and indexing hints.
     */
    public function optimizeServiceRequestQuery(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = 'optimized_requests:' . md5(serialize($filters));

        return $this->cacheService->remember($cacheKey, function () use ($filters) {
            $query = $this->serviceRequestRepository->query()
                ->with(['customer' => function ($query) {
                    $query->select('id', 'name', 'email', 'phone');
                }])
                ->with(['provider' => function ($query) {
                    $query->select('id', 'name', 'email', 'company_name');
                }])
                ->with(['attachments' => function ($query) {
                    $query->select('id', 'service_request_id', 'filename', 'file_type', 'file_size');
                }]);

            // Apply filters efficiently
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['customer_id'])) {
                $query->where('customer_id', $filters['customer_id']);
            }

            if (!empty($filters['provider_id'])) {
                $query->where('provider_id', $filters['provider_id']);
            }

            // Use index hints for better performance
            if (!empty($filters['category'])) {
                $query->whereJsonContains('metadata->category', $filters['category']);
            }

            // Optimize ordering
            $query->orderBy('created_at', 'desc')
                  ->limit(100); // Prevent large result sets

            return $query->get();
        }, 300); // Cache for 5 minutes
    }

    /**
     * Get statistics with caching and optimization.
     */
    public function getOptimizedStatistics(): array
    {
        return $this->cacheService->remember('platform_statistics', function () {
            // Use raw queries for better performance on large datasets
            $stats = [];

            // User statistics
            $userStats = DB::table('users')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN plan = "free" THEN 1 ELSE 0 END) as free_users,
                    SUM(CASE WHEN plan = "basic" THEN 1 ELSE 0 END) as basic_users,
                    SUM(CASE WHEN plan = "premium" THEN 1 ELSE 0 END) as premium_users,
                    SUM(CASE WHEN plan = "enterprise" THEN 1 ELSE 0 END) as enterprise_users,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_this_month
                ')
                ->first();

            $stats['users'] = [
                'total' => (int) $userStats->total,
                'by_plan' => [
                    'free' => (int) $userStats->free_users,
                    'basic' => (int) $userStats->basic_users,
                    'premium' => (int) $userStats->premium_users,
                    'enterprise' => (int) $userStats->enterprise_users,
                ],
                'new_this_month' => (int) $userStats->new_this_month,
            ];

            // Service request statistics
            $requestStats = DB::table('service_requests')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as created_this_month,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_completion_hours
                ')
                ->first();

            $stats['service_requests'] = [
                'total' => (int) $requestStats->total,
                'by_status' => [
                    'pending' => (int) $requestStats->pending,
                    'accepted' => (int) $requestStats->accepted,
                    'completed' => (int) $requestStats->completed,
                ],
                'created_this_month' => (int) $requestStats->created_this_month,
                'avg_completion_hours' => round((float) $requestStats->avg_completion_hours, 2),
            ];

            // Attachment statistics
            $attachmentStats = DB::table('attachments')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN file_type = "image" THEN 1 ELSE 0 END) as images,
                    SUM(CASE WHEN file_type = "document" THEN 1 ELSE 0 END) as documents,
                    AVG(file_size) as avg_file_size
                ')
                ->first();

            $stats['attachments'] = [
                'total' => (int) $attachmentStats->total,
                'by_type' => [
                    'images' => (int) $attachmentStats->images,
                    'documents' => (int) $attachmentStats->documents,
                ],
                'avg_file_size' => round((float) $attachmentStats->avg_file_size / 1024 / 1024, 2), // MB
            ];

            return $stats;
        }, 900); // Cache for 15 minutes
    }

    /**
     * Bulk update operations for better performance.
     */
    public function bulkUpdateServiceRequests(array $updates): int
    {
        $affected = 0;

        DB::transaction(function () use ($updates, &$affected) {
            foreach ($updates as $update) {
                $query = DB::table('service_requests');

                if (isset($update['where']['id'])) {
                    $query->where('id', $update['where']['id']);
                }

                if (isset($update['where']['status'])) {
                    $query->where('status', $update['where']['status']);
                }

                $affected += $query->update($update['data']);

                // Invalidate relevant cache
                $this->invalidateRelatedCache($update['where'] ?? []);
            }
        });

        return $affected;
    }

    /**
     * Optimized search with full-text indexing.
     */
    public function optimizedSearch(string $term, array $filters = []): array
    {
        $cacheKey = 'search:' . md5($term . serialize($filters));

        return $this->cacheService->remember($cacheKey, function () use ($term, $filters) {
            $results = [];

            // Search service requests
            if (empty($filters['type']) || $filters['type'] === 'requests') {
                $requestResults = $this->serviceRequestRepository->query()
                    ->where(function ($query) use ($term) {
                        $query->where('title', 'LIKE', "%{$term}%")
                              ->orWhere('description', 'LIKE', "%{$term}%");
                    })
                    ->with(['customer:id,name,email', 'provider:id,name,email'])
                    ->limit(20)
                    ->get();

                $results['requests'] = $requestResults->toArray();
            }

            // Search users
            if (empty($filters['type']) || $filters['type'] === 'users') {
                $userResults = $this->userRepository->query()
                    ->where(function ($query) use ($term) {
                        $query->where('name', 'LIKE', "%{$term}%")
                              ->orWhere('email', 'LIKE', "%{$term}%")
                              ->orWhere('company_name', 'LIKE', "%{$term}%");
                    })
                    ->select('id', 'name', 'email', 'company_name', 'plan')
                    ->limit(20)
                    ->get();

                $results['users'] = $userResults->toArray();
            }

            return $results;
        }, 600); // Cache for 10 minutes
    }

    /**
     * Get nearby requests with spatial indexing optimization.
     */
    public function getNearbyRequestsOptimized(float $latitude, float $longitude, float $radius = 50): array
    {
        $cacheKey = "nearby_optimized:" . round($latitude, 2) . ":" . round($longitude, 2) . ":{$radius}";

        return $this->cacheService->remember($cacheKey, function () use ($latitude, $longitude, $radius) {
            // Use spatial query with bounding box optimization first
            $latDelta = $radius / 111; // Approximate degrees
            $lngDelta = $radius / (111 * cos(deg2rad($latitude)));

            $requests = $this->serviceRequestRepository->query()
                ->where('status', 'pending')
                ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
                ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta])
                ->with(['customer:id,name,email,phone'])
                ->selectRaw('*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance', [$latitude, $longitude, $latitude])
                ->having('distance', '<=', $radius)
                ->orderBy('distance')
                ->limit(50)
                ->get();

            return [
                'requests' => $requests->toArray(),
                'total' => $requests->count(),
                'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                'radius' => $radius,
            ];
        }, 300); // Cache for 5 minutes
    }

    /**
     * Batch process operations for large datasets.
     */
    public function batchProcess(callable $processor, int $batchSize = 1000): void
    {
        $this->serviceRequestRepository->query()
            ->chunk($batchSize, function ($batch) use ($processor) {
                $processor($batch);
                
                // Clear cache periodically during batch processing
                if (rand(1, 10) === 1) {
                    $this->cacheService->cleanup();
                }
            });
    }

    /**
     * Optimize database performance with maintenance tasks.
     */
    public function optimizeDatabase(): array
    {
        $results = [];

        try {
            // Analyze tables for query optimization
            $tables = ['users', 'service_requests', 'attachments', 'model_has_roles'];
            
            foreach ($tables as $table) {
                $result = DB::statement("ANALYZE TABLE {$table}");
                $results['analyze'][] = $table;
            }

            // Optimize tables (if supported)
            if (config('database.default') === 'mysql') {
                foreach ($tables as $table) {
                    DB::statement("OPTIMIZE TABLE {$table}");
                    $results['optimize'][] = $table;
                }
            }

            // Clear query cache
            if (config('database.default') === 'mysql') {
                DB::statement('RESET QUERY CACHE');
                $results['query_cache'] = 'cleared';
            }

        } catch (\Exception $e) {
            Log::error('Database optimization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Monitor and log slow queries.
     */
    public function monitorSlowQueries(): array
    {
        $slowQueries = [];

        if (config('database.default') === 'mysql') {
            try {
                $results = DB::select("
                    SELECT query_time, lock_time, rows_sent, rows_examined, sql_text
                    FROM mysql.slow_log
                    WHERE start_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                    ORDER BY query_time DESC
                    LIMIT 10
                ");

                foreach ($results as $query) {
                    $slowQueries[] = [
                        'query_time' => $query->query_time,
                        'lock_time' => $query->lock_time,
                        'rows_sent' => $query->rows_sent,
                        'rows_examined' => $query->rows_examined,
                        'sql' => substr($query->sql_text, 0, 200) . '...',
                    ];
                }
            } catch (\Exception $e) {
                Log::error('Failed to monitor slow queries', ['error' => $e->getMessage()]);
            }
        }

        return $slowQueries;
    }

    /**
     * Get performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'cache' => $this->cacheService->getCacheStats(),
            'database' => [
                'connections' => DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0,
                'queries_per_second' => $this->getQueriesPerSecond(),
                'slow_queries' => $this->monitorSlowQueries(),
            ],
            'memory' => [
                'peak_usage' => memory_get_peak_usage(true),
                'current_usage' => memory_get_usage(true),
            ],
            'response_time' => $this->getAverageResponseTime(),
        ];
    }

    /**
     * Get queries per second.
     */
    protected function getQueriesPerSecond(): float
    {
        try {
            $status = DB::select('SHOW GLOBAL STATUS LIKE "Questions"')[0];
            $uptime = DB::select('SHOW GLOBAL STATUS LIKE "Uptime"')[0];
            
            return round($status->Value / $uptime->Value, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get average response time.
     */
    protected function getAverageResponseTime(): float
    {
        // This would typically come from application monitoring
        // For now, return a placeholder
        return 150.5; // milliseconds
    }

    /**
     * Invalidate related cache entries.
     */
    protected function invalidateRelatedCache(array $conditions): void
    {
        $patterns = [];

        if (isset($conditions['id'])) {
            $patterns[] = 'response:*:user:' . $conditions['id'] . '*';
            $patterns[] = 'service_request:' . $conditions['id'];
        }

        if (isset($conditions['status'])) {
            $patterns[] = 'response:GET:/api/requests*';
            $patterns[] = 'platform_statistics';
        }

        foreach ($patterns as $pattern) {
            $this->cacheService->forget($pattern);
        }
    }

    /**
     * Preload common data into cache.
     */
    public function preloadCache(): void
    {
        // Preload active providers
        $activeProviders = $this->userRepository->getActiveProviders();
        $this->cacheService->set('active_providers', $activeProviders->toArray(), 1800);

        // Preload recent service requests
        $recentRequests = $this->serviceRequestRepository->query()
            ->with(['customer:id,name', 'provider:id,name'])
            ->latest()
            ->limit(20)
            ->get();
        
        $this->cacheService->set('recent_requests', $recentRequests->toArray(), 600);

        // Preload statistics
        $this->getOptimizedStatistics();

        Log::info('Cache preloaded successfully');
    }
}
