<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class MetricsService
{
    protected array $metrics = [];
    protected string $prefix = 'metrics:';

    /**
     * Record a metric value.
     */
    public function record(string $name, float $value, array $tags = []): void
    {
        $key = $this->buildKey($name, $tags);
        $timestamp = now()->timestamp;

        if (config('cache.default') === 'redis') {
            // Store in Redis with timestamp for time series data
            Redis::zadd($key, $timestamp, json_encode([
                'value' => $value,
                'timestamp' => $timestamp,
                'tags' => $tags
            ]));

            // Keep only last 1000 data points per metric
            Redis::zremrangebyrank($key, 0, -1001);
        } else {
            // Fallback to array storage
            $this->metrics[$key][] = [
                'value' => $value,
                'timestamp' => $timestamp,
                'tags' => $tags
            ];
        }
    }

    /**
     * Increment a counter metric.
     */
    public function increment(string $name, array $tags = [], float $value = 1.0): void
    {
        $this->record($name, $value, $tags);
    }

    /**
     * Record timing metric.
     */
    public function timing(string $name, float $duration, array $tags = []): void
    {
        $this->record($name . '_duration', $duration, $tags);
    }

    /**
     * Record gauge metric (current value).
     */
    public function gauge(string $name, float $value, array $tags = []): void
    {
        $key = $this->buildKey($name, $tags);
        
        if (config('cache.default') === 'redis') {
            Redis::set($key . ':gauge', json_encode([
                'value' => $value,
                'timestamp' => now()->timestamp,
                'tags' => $tags
            ]));
        } else {
            $this->metrics[$key . ':gauge'] = [
                'value' => $value,
                'timestamp' => now()->timestamp,
                'tags' => $tags
            ];
        }
    }

    /**
     * Get metric statistics.
     */
    public function getStats(string $name, array $tags = [], int $period = 3600): array
    {
        $key = $this->buildKey($name, $tags);
        $now = now()->timestamp;
        $periodStart = $now - $period;

        if (config('cache.default') === 'redis') {
            $data = Redis::zrangebyscore($key, $periodStart, $now);
            $values = [];

            foreach ($data as $item) {
                $decoded = json_decode($item, true);
                $values[] = $decoded['value'];
            }

            return $this->calculateStats($values);
        } else {
            $values = array_filter($this->metrics[$key] ?? [], function ($item) use ($periodStart) {
                return $item['timestamp'] >= $periodStart;
            });

            $values = array_column($values, 'value');
            return $this->calculateStats($values);
        }
    }

    /**
     * Get current gauge value.
     */
    public function getGauge(string $name, array $tags = []): ?float
    {
        $key = $this->buildKey($name, $tags) . ':gauge';

        if (config('cache.default') === 'redis') {
            $data = Redis::get($key);
            if ($data) {
                $decoded = json_decode($data, true);
                return $decoded['value'] ?? null;
            }
        } else {
            return $this->metrics[$key]['value'] ?? null;
        }

        return null;
    }

    /**
     * Get all metrics with their current values.
     */
    public function getAllMetrics(): array
    {
        $metrics = [];

        if (config('cache.default') === 'redis') {
            $keys = Redis::keys($this->prefix . '*');
            
            foreach ($keys as $key) {
                $name = str_replace($this->prefix, '', $key);
                
                if (str_ends_with($key, ':gauge')) {
                    $data = Redis::get($key);
                    if ($data) {
                        $decoded = json_decode($data, true);
                        $metrics[$name] = [
                            'type' => 'gauge',
                            'value' => $decoded['value'],
                            'timestamp' => $decoded['timestamp'],
                            'tags' => $decoded['tags'] ?? []
                        ];
                    }
                } else {
                    $count = Redis::zcard($key);
                    $latest = Redis::zrange($key, -1, -1);
                    
                    if ($latest) {
                        $decoded = json_decode($latest[0], true);
                        $metrics[$name] = [
                            'type' => 'counter',
                            'count' => $count,
                            'latest_value' => $decoded['value'],
                            'latest_timestamp' => $decoded['timestamp'],
                            'tags' => $decoded['tags'] ?? []
                        ];
                    }
                }
            }
        } else {
            foreach ($this->metrics as $key => $data) {
                $name = str_replace($this->prefix, '', $key);
                
                if (str_ends_with($key, ':gauge')) {
                    $metrics[$name] = [
                        'type' => 'gauge',
                        'value' => $data['value'],
                        'timestamp' => $data['timestamp'],
                        'tags' => $data['tags'] ?? []
                    ];
                } else {
                    $metrics[$name] = [
                        'type' => 'counter',
                        'count' => count($data),
                        'latest_value' => end($data)['value'],
                        'latest_timestamp' => end($data)['timestamp'],
                        'tags' => end($data)['tags'] ?? []
                    ];
                }
            }
        }

        return $metrics;
    }

    /**
     * Record API request metrics.
     */
    public function recordApiRequest(string $endpoint, string $method, int $statusCode, float $duration, array $tags = []): void
    {
        $baseTags = array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => (string)$statusCode
        ], $tags);

        $this->increment('api_requests_total', $baseTags);
        $this->timing('api_request_duration', $duration, $baseTags);

        // Record error rates
        if ($statusCode >= 400) {
            $this->increment('api_errors_total', $baseTags);
        }

        // Record 2xx success rates
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->increment('api_success_total', $baseTags);
        }
    }

    /**
     * Record database query metrics.
     */
    public function recordDatabaseQuery(string $query, float $duration, bool $success = true): void
    {
        $tags = [
            'query_type' => $this->getQueryType($query),
            'success' => $success ? 'true' : 'false'
        ];

        $this->increment('db_queries_total', $tags);
        $this->timing('db_query_duration', $duration, $tags);

        if (!$success) {
            $this->increment('db_errors_total', $tags);
        }
    }

    /**
     * Record cache operation metrics.
     */
    public function recordCacheOperation(string $operation, string $key, bool $hit = null, float $duration = null): void
    {
        $tags = [
            'operation' => $operation,
            'key_type' => $this->getKeyType($key)
        ];

        $this->increment('cache_operations_total', $tags);

        if ($hit !== null) {
            $hitTags = array_merge($tags, ['hit' => $hit ? 'true' : 'false']);
            $this->increment('cache_hits_total', $hitTags);
        }

        if ($duration !== null) {
            $this->timing('cache_operation_duration', $duration, $tags);
        }
    }

    /**
     * Record queue job metrics.
     */
    public function recordQueueJob(string $job, string $status, float $duration = null): void
    {
        $tags = [
            'job' => $job,
            'status' => $status
        ];

        $this->increment('queue_jobs_total', $tags);

        if ($duration !== null) {
            $this->timing('queue_job_duration', $duration, $tags);
        }

        if ($status === 'failed') {
            $this->increment('queue_job_failures_total', $tags);
        }
    }

    /**
     * Record system resource metrics.
     */
    public function recordSystemMetrics(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $cpuLoad = sys_getloadavg()[0] ?? 0;

        $this->gauge('system_memory_usage', $memoryUsage, ['unit' => 'bytes']);
        $this->gauge('system_memory_peak', $memoryPeak, ['unit' => 'bytes']);
        $this->gauge('system_cpu_load', $cpuLoad, ['unit' => 'load_average']);

        // Disk usage (if available)
        if (function_exists('disk_free_space')) {
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskUsed = $diskTotal - $diskFree;

            $this->gauge('system_disk_free', $diskFree, ['unit' => 'bytes']);
            $this->gauge('system_disk_used', $diskUsed, ['unit' => 'bytes']);
            $this->gauge('system_disk_usage_percent', ($diskUsed / $diskTotal) * 100, ['unit' => 'percent']);
        }
    }

    /**
     * Record business metrics.
     */
    public function recordBusinessMetrics(): void
    {
        // User metrics
        $totalUsers = \App\Models\User::count();
        $activeUsers = \App\Models\User::where('last_login_at', '>=', now()->subDays(30))->count();
        $newUsersToday = \App\Models\User::whereDate('created_at', today())->count();

        $this->gauge('users_total', $totalUsers);
        $this->gauge('users_active', $activeUsers);
        $this->increment('users_new_today', [], $newUsersToday);

        // Service request metrics
        $totalRequests = \App\Models\ServiceRequest::count();
        $pendingRequests = \App\Models\ServiceRequest::where('status', 'pending')->count();
        $acceptedRequests = \App\Models\ServiceRequest::where('status', 'accepted')->count();
        $completedRequests = \App\Models\ServiceRequest::where('status', 'completed')->count();
        $newRequestsToday = \App\Models\ServiceRequest::whereDate('created_at', today())->count();

        $this->gauge('service_requests_total', $totalRequests);
        $this->gauge('service_requests_pending', $pendingRequests);
        $this->gauge('service_requests_accepted', $acceptedRequests);
        $this->gauge('service_requests_completed', $completedRequests);
        $this->increment('service_requests_new_today', [], $newRequestsToday);

        // Attachment metrics
        $totalAttachments = \App\Models\Attachment::count();
        $totalAttachmentSize = \App\Models\Attachment::sum('file_size');

        $this->gauge('attachments_total', $totalAttachments);
        $this->gauge('attachments_total_size', $totalAttachmentSize, ['unit' => 'bytes']);
    }

    /**
     * Get health check metrics.
     */
    public function getHealthCheck(): array
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => []
        ];

        // Database health
        try {
            \DB::select('SELECT 1');
            $health['checks']['database'] = [
                'status' => 'healthy',
                'response_time' => $this->measureDatabaseResponseTime()
            ];
        } catch (\Exception $e) {
            $health['checks']['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $health['status'] = 'unhealthy';
        }

        // Cache health
        try {
            $cacheKey = 'health_check_' . time();
            $testValue = 'test';
            
            if (config('cache.default') === 'redis') {
                Redis::set($cacheKey, $testValue, 'EX', 10);
                $retrieved = Redis::get($cacheKey);
                Redis::del($cacheKey);
            } else {
                \Cache::put($cacheKey, $testValue, 10);
                $retrieved = \Cache::get($cacheKey);
                \Cache::forget($cacheKey);
            }

            $health['checks']['cache'] = [
                'status' => $retrieved === $testValue ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            $health['checks']['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
            $health['status'] = 'unhealthy';
        }

        // Queue health
        try {
            $failedJobs = \DB::table('failed_jobs')->count();
            $health['checks']['queue'] = [
                'status' => $failedJobs < 100 ? 'healthy' : 'degraded',
                'failed_jobs' => $failedJobs
            ];
        } catch (\Exception $e) {
            $health['checks']['queue'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        // Storage health
        try {
            $storagePath = storage_path();
            $freeSpace = disk_free_space($storagePath);
            $totalSpace = disk_total_space($storagePath);
            $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;

            $health['checks']['storage'] = [
                'status' => $usagePercent < 90 ? 'healthy' : 'critical',
                'free_space' => $freeSpace,
                'total_space' => $totalSpace,
                'usage_percent' => round($usagePercent, 2)
            ];
        } catch (\Exception $e) {
            $health['checks']['storage'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        return $health;
    }

    /**
     * Get performance dashboard data.
     */
    public function getDashboard(): array
    {
        return [
            'overview' => [
                'uptime' => $this->getUptime(),
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
                'timestamp' => now()->toISOString()
            ],
            'metrics' => $this->getAllMetrics(),
            'health' => $this->getHealthCheck(),
            'performance' => [
                'api_response_time' => $this->getStats('api_request_duration', [], 3600),
                'db_query_time' => $this->getStats('db_query_duration', [], 3600),
                'cache_hit_rate' => $this->calculateCacheHitRate(),
                'error_rate' => $this->calculateErrorRate(),
            ],
            'business' => [
                'users' => $this->getGauge('users_total'),
                'active_users' => $this->getGauge('users_active'),
                'service_requests' => $this->getGauge('service_requests_total'),
                'pending_requests' => $this->getGauge('service_requests_pending'),
                'attachments' => $this->getGauge('attachments_total'),
            ]
        ];
    }

    /**
     * Build metric key with tags.
     */
    protected function buildKey(string $name, array $tags): string
    {
        $key = $this->prefix . $name;
        
        if (!empty($tags)) {
            ksort($tags);
            $key .= ':' . http_build_query($tags);
        }
        
        return $key;
    }

    /**
     * Calculate statistics from values.
     */
    protected function calculateStats(array $values): array
    {
        if (empty($values)) {
            return [
                'count' => 0,
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'sum' => 0,
                'median' => 0,
                'p95' => 0,
                'p99' => 0
            ];
        }

        sort($values);
        $count = count($values);
        $sum = array_sum($values);
        $avg = $sum / $count;

        return [
            'count' => $count,
            'min' => min($values),
            'max' => max($values),
            'avg' => round($avg, 2),
            'sum' => $sum,
            'median' => $this->calculatePercentile($values, 50),
            'p95' => $this->calculatePercentile($values, 95),
            'p99' => $this->calculatePercentile($values, 99)
        ];
    }

    /**
     * Calculate percentile.
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        $index = ($percentile / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);

        if ($lower === $upper) {
            return $values[$lower];
        }

        $weight = $index - $lower;
        return round($values[$lower] * (1 - $weight) + $values[$upper] * $weight, 2);
    }

    /**
     * Get query type from SQL string.
     */
    protected function getQueryType(string $query): string
    {
        $query = strtoupper(trim($query));
        
        if (str_starts_with($query, 'SELECT')) {
            return 'select';
        } elseif (str_starts_with($query, 'INSERT')) {
            return 'insert';
        } elseif (str_starts_with($query, 'UPDATE')) {
            return 'update';
        } elseif (str_starts_with($query, 'DELETE')) {
            return 'delete';
        } elseif (str_starts_with($query, 'CREATE')) {
            return 'create';
        } elseif (str_starts_with($query, 'DROP')) {
            return 'drop';
        } elseif (str_starts_with($query, 'ALTER')) {
            return 'alter';
        }
        
        return 'other';
    }

    /**
     * Get key type from cache key.
     */
    protected function getKeyType(string $key): string
    {
        if (str_contains($key, 'user')) {
            return 'user';
        } elseif (str_contains($key, 'service_request')) {
            return 'service_request';
        } elseif (str_contains($key, 'statistics')) {
            return 'statistics';
        } elseif (str_contains($key, 'response')) {
            return 'api_response';
        }
        
        return 'other';
    }

    /**
     * Measure database response time.
     */
    protected function measureDatabaseResponseTime(): float
    {
        $start = microtime(true);
        \DB::select('SELECT 1');
        return round((microtime(true) - $start) * 1000, 2); // milliseconds
    }

    /**
     * Calculate cache hit rate.
     */
    protected function calculateCacheHitRate(): float
    {
        $hits = $this->getStats('cache_hits_total', ['hit' => 'true'], 3600);
        $misses = $this->getStats('cache_hits_total', ['hit' => 'false'], 3600);
        
        $totalHits = $hits['sum'] ?? 0;
        $totalMisses = $misses['sum'] ?? 0;
        $total = $totalHits + $totalMisses;
        
        return $total > 0 ? round(($totalHits / $total) * 100, 2) : 0;
    }

    /**
     * Calculate error rate.
     */
    protected function calculateErrorRate(): float
    {
        $errors = $this->getStats('api_errors_total', [], 3600);
        $success = $this->getStats('api_success_total', [], 3600);
        
        $totalErrors = $errors['sum'] ?? 0;
        $totalSuccess = $success['sum'] ?? 0;
        $total = $totalErrors + $totalSuccess;
        
        return $total > 0 ? round(($totalErrors / $total) * 100, 2) : 0;
    }

    /**
     * Get application uptime.
     */
    protected function getUptime(): string
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : time();
        $uptime = time() - $startTime;
        
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        
        return "{$days}d {$hours}h {$minutes}m";
    }

    /**
     * Clean up old metrics data.
     */
    public function cleanup(int $retentionPeriod = 86400): void
    {
        $cutoff = now()->timestamp - $retentionPeriod;

        if (config('cache.default') === 'redis') {
            $keys = Redis::keys($this->prefix . '*');
            
            foreach ($keys as $key) {
                if (!str_ends_with($key, ':gauge')) {
                    Redis::zremrangebyscore($key, 0, $cutoff);
                }
            }
        } else {
            // Clean up array-based metrics
            foreach ($this->metrics as $key => &$data) {
                if (!str_ends_with($key, ':gauge')) {
                    $data = array_filter($data, function ($item) use ($cutoff) {
                        return $item['timestamp'] >= $cutoff;
                    });
                }
            }
        }
    }
}
