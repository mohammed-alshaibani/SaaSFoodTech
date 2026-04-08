<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MonitoringService
{
    /**
     * Record API performance metrics.
     */
    public function recordApiMetrics($endpoint, $method, $responseTime, $statusCode, $userId = null): void
    {
        $key = "api_metrics_{$endpoint}_{$method}";
        $timestamp = now()->format('Y-m-d H:i');
        
        $metrics = [
            'response_time' => $responseTime,
            'status_code' => $statusCode,
            'timestamp' => $timestamp,
            'user_id' => $userId,
        ];

        // Store in Redis for real-time monitoring
        Redis::lpush($key, json_encode($metrics));
        Redis::expire($key, 86400); // Keep for 24 hours

        // Update aggregated metrics
        $this->updateAggregatedMetrics($endpoint, $method, $metrics);
    }

    /**
     * Record database query performance.
     */
    public function recordQueryMetrics($query, $executionTime, $affectedRows = null): void
    {
        $key = 'query_metrics';
        $metrics = [
            'query_type' => $this->getQueryType($query),
            'execution_time' => $executionTime,
            'affected_rows' => $affectedRows,
            'timestamp' => now()->timestamp,
        ];

        Redis::lpush($key, json_encode($metrics));
        Redis::expire($key, 3600); // Keep for 1 hour
    }

    /**
     * Record cache hit/miss metrics.
     */
    public function recordCacheMetrics($operation, $key, $hit): void
    {
        $cacheKey = 'cache_metrics';
        $metrics = [
            'operation' => $operation,
            'key_pattern' => $this->getKeyPattern($key),
            'hit' => $hit,
            'timestamp' => now()->timestamp,
        ];

        Redis::lpush($cacheKey, json_encode($metrics));
        Redis::expire($cacheKey, 3600);
    }

    /**
     * Record system resource usage.
     */
    public function recordSystemMetrics(): void
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'cpu_load' => $this->getCpuLoad(),
            'disk_usage' => $this->getDiskUsage(),
            'active_connections' => $this->getActiveConnections(),
            'timestamp' => now()->timestamp,
        ];

        Redis::lpush('system_metrics', json_encode($metrics));
        Redis::expire('system_metrics', 300); // Keep for 5 minutes
    }

    /**
     * Get real-time API metrics.
     */
    public function getApiMetrics($endpoint = null, $hours = 24): array
    {
        if ($endpoint) {
            $keys = ["api_metrics_{$endpoint}_GET", "api_metrics_{$endpoint}_POST", "api_metrics_{$endpoint}_PUT", "api_metrics_{$endpoint}_DELETE"];
        } else {
            $keys = Redis::keys('api_metrics_*');
        }

        $allMetrics = [];
        $cutoff = now()->subHours($hours)->timestamp;

        foreach ($keys as $key) {
            $metrics = Redis::lrange($key, 0, -1);
            foreach ($metrics as $metric) {
                $data = json_decode($metric, true);
                if ($data['timestamp'] >= $cutoff) {
                    $allMetrics[] = $data;
                }
            }
        }

        return $this->aggregateApiMetrics($allMetrics);
    }

    /**
     * Get database performance metrics.
     */
    public function getDatabaseMetrics($hours = 1): array
    {
        $metrics = Redis::lrange('query_metrics', 0, -1);
        $cutoff = now()->subHours($hours)->timestamp;
        
        $filteredMetrics = collect($metrics)
            ->map(fn($metric) => json_decode($metric, true))
            ->filter(fn($metric) => $metric['timestamp'] >= $cutoff);

        return [
            'total_queries' => $filteredMetrics->count(),
            'avg_execution_time' => $filteredMetrics->avg('execution_time'),
            'slow_queries' => $filteredMetrics->filter(fn($m) => $m['execution_time'] > 1000)->count(),
            'query_types' => $filteredMetrics->groupBy('query_type')->map(fn($group) => $group->count()),
        ];
    }

    /**
     * Get cache performance metrics.
     */
    public function getCacheMetrics($hours = 1): array
    {
        $metrics = Redis::lrange('cache_metrics', 0, -1);
        $cutoff = now()->subHours($hours)->timestamp;
        
        $filteredMetrics = collect($metrics)
            ->map(fn($metric) => json_decode($metric, true))
            ->filter(fn($metric) => $metric['timestamp'] >= $cutoff);

        $hits = $filteredMetrics->where('hit', true)->count();
        $misses = $filteredMetrics->where('hit', false)->count();
        $total = $hits + $misses;

        return [
            'total_operations' => $total,
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 2) : 0,
            'operations_by_type' => $filteredMetrics->groupBy('operation')->map(fn($group) => $group->count()),
        ];
    }

    /**
     * Get system resource metrics.
     */
    public function getSystemMetrics(): array
    {
        $metrics = Redis::lrange('system_metrics', 0, -1);
        
        if (empty($metrics)) {
            return $this->recordSystemMetrics();
        }

        $latest = json_decode(end($metrics), true);

        return [
            'memory_usage_mb' => round($latest['memory_usage'] / 1024 / 1024, 2),
            'memory_peak_mb' => round($latest['memory_peak'] / 1024 / 1024, 2),
            'cpu_load' => $latest['cpu_load'],
            'disk_usage_percent' => $latest['disk_usage'],
            'active_connections' => $latest['active_connections'],
            'timestamp' => $latest['timestamp'],
        ];
    }

    /**
     * Get application health status.
     */
    public function getHealthStatus(): array
    {
        $checks = [
            'database' => $this->checkDatabaseHealth(),
            'redis' => $this->checkRedisHealth(),
            'cache' => $this->checkCacheHealth(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemoryUsage(),
            'response_time' => $this->checkResponseTime(),
        ];

        $overallStatus = collect($checks)->every('status');

        return [
            'overall_status' => $overallStatus ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ];
    }

    /**
     * Get performance dashboard data.
     */
    public function getDashboardData(): array
    {
        return [
            'api_metrics' => $this->getApiMetrics(null, 24),
            'database_metrics' => $this->getDatabaseMetrics(24),
            'cache_metrics' => $this->getCacheMetrics(24),
            'system_metrics' => $this->getSystemMetrics(),
            'health_status' => $this->getHealthStatus(),
            'top_slow_endpoints' => $this->getTopSlowEndpoints(),
            'error_rates' => $this->getErrorRates(),
        ];
    }

    /**
     * Update aggregated metrics.
     */
    private function updateAggregatedMetrics($endpoint, $method, $metrics): void
    {
        $key = "aggregated_{$endpoint}_{$method}";
        $date = now()->format('Y-m-d');
        
        $aggregated = Cache::get($key, [
            'total_requests' => 0,
            'total_response_time' => 0,
            'error_count' => 0,
            'status_codes' => [],
        ]);

        $aggregated['total_requests']++;
        $aggregated['total_response_time'] += $metrics['response_time'];
        
        if ($metrics['status_code'] >= 400) {
            $aggregated['error_count']++;
        }

        $aggregated['status_codes'][$metrics['status_code']] = 
            ($aggregated['status_codes'][$metrics['status_code']] ?? 0) + 1;

        Cache::put($key, $aggregated, now()->addDays(7));
    }

    /**
     * Get query type from SQL string.
     */
    private function getQueryType($query): string
    {
        $query = strtoupper(trim($query));
        
        if (str_starts_with($query, 'SELECT')) return 'SELECT';
        if (str_starts_with($query, 'INSERT')) return 'INSERT';
        if (str_starts_with($query, 'UPDATE')) return 'UPDATE';
        if (str_starts_with($query, 'DELETE')) return 'DELETE';
        if (str_starts_with($query, 'CREATE')) return 'CREATE';
        if (str_starts_with($query, 'ALTER')) return 'ALTER';
        if (str_starts_with($query, 'DROP')) return 'DROP';
        
        return 'OTHER';
    }

    /**
     * Get key pattern for cache metrics.
     */
    private function getKeyPattern($key): string
    {
        if (str_contains($key, 'user_')) return 'user_data';
        if (str_contains($key, 'api_response_')) return 'api_response';
        if (str_contains($key, 'ai_result_')) return 'ai_result';
        if (str_contains($key, 'nearby_')) return 'location_data';
        
        return 'other';
    }

    /**
     * Get CPU load.
     */
    private function getCpuLoad(): float
    {
        $load = sys_getloadavg();
        return $load[0] ?? 0;
    }

    /**
     * Get disk usage.
     */
    private function getDiskUsage(): float
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        
        return $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0;
    }

    /**
     * Get active database connections.
     */
    private function getActiveConnections(): int
    {
        try {
            return DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Aggregate API metrics.
     */
    private function aggregateApiMetrics($metrics): array
    {
        $grouped = collect($metrics)->groupBy('status_code');
        
        return [
            'total_requests' => count($metrics),
            'avg_response_time' => collect($metrics)->avg('response_time'),
            'min_response_time' => collect($metrics)->min('response_time'),
            'max_response_time' => collect($metrics)->max('response_time'),
            'status_codes' => $grouped->map(fn($group) => $group->count())->toArray(),
            'error_rate' => $this->calculateErrorRate($metrics),
        ];
    }

    /**
     * Calculate error rate.
     */
    private function calculateErrorRate($metrics): float
    {
        $total = count($metrics);
        $errors = collect($metrics)->filter(fn($m) => $m['status_code'] >= 400)->count();
        
        return $total > 0 ? round(($errors / $total) * 100, 2) : 0;
    }

    /**
     * Check database health.
     */
    private function checkDatabaseHealth(): array
    {
        try {
            DB::select('SELECT 1');
            return ['status' => true, 'message' => 'Database connection healthy'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check Redis health.
     */
    private function checkRedisHealth(): array
    {
        try {
            Redis::ping();
            return ['status' => true, 'message' => 'Redis connection healthy'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Redis connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check cache health.
     */
    private function checkCacheHealth(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $result = Cache::get('health_check');
            return $result === 'ok' 
                ? ['status' => true, 'message' => 'Cache working properly']
                : ['status' => false, 'message' => 'Cache not working'];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Cache check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check disk space.
     */
    private function checkDiskSpace(): array
    {
        $usage = $this->getDiskUsage();
        
        return [
            'status' => $usage < 90,
            'message' => $usage < 90 
                ? "Disk usage: {$usage}%"
                : "Disk usage critical: {$usage}%",
        ];
    }

    /**
     * Check memory usage.
     */
    private function checkMemoryUsage(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->getMemoryLimit();
        
        $usagePercent = $limit > 0 ? ($usage / $limit) * 100 : 0;
        
        return [
            'status' => $usagePercent < 80,
            'message' => $usagePercent < 80 
                ? "Memory usage: " . round($usagePercent, 2) . "%"
                : "Memory usage high: " . round($usagePercent, 2) . "%",
        ];
    }

    /**
     * Check response time.
     */
    private function checkResponseTime(): array
    {
        $metrics = $this->getApiMetrics(null, 1);
        $avgTime = $metrics['avg_response_time'] ?? 0;
        
        return [
            'status' => $avgTime < 1000, // Less than 1 second
            'message' => $avgTime < 1000 
                ? "Average response time: " . round($avgTime, 2) . "ms"
                : "Response time slow: " . round($avgTime, 2) . "ms",
        ];
    }

    /**
     * Get memory limit.
     */
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return -1;
        
        return $this->parseMemoryLimit($limit);
    }

    /**
     * Parse memory limit string.
     */
    private function parseMemoryLimit($limit): int
    {
        $unit = strtoupper(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        return match ($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Get top slow endpoints.
     */
    private function getTopSlowEndpoints($limit = 10): array
    {
        $metrics = $this->getApiMetrics(null, 24);
        
        return collect($metrics['all_metrics'] ?? [])
            ->sortByDesc('response_time')
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get error rates by endpoint.
     */
    private function getErrorRates(): array
    {
        $metrics = $this->getApiMetrics(null, 24);
        
        return collect($metrics['all_metrics'] ?? [])
            ->groupBy(fn($m) => $m['endpoint'] ?? 'unknown')
            ->map(fn($group) => [
                'total' => $group->count(),
                'errors' => $group->filter(fn($m) => $m['status_code'] >= 400)->count(),
                'error_rate' => $this->calculateErrorRate($group->toArray()),
            ])
            ->toArray();
    }
}
