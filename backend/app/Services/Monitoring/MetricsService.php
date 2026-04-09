<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MetricsService
{
    /**
     * Record system metrics.
     */
    public function recordMetric(string $type, array $data): void
    {
        $metric = [
            'type' => $type,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        Cache::put("metric_{$type}_{$data['id']}", $metric, 3600);
        
        Log::info('Metric recorded', $metric);
    }

    /**
     * Get system metrics.
     */
    public function getMetrics(): array
    {
        return [
            'system' => $this->getSystemMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'api' => $this->getApiMetrics(),
        ];
    }

    /**
     * Get system metrics.
     */
    private function getSystemMetrics(): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
        ];
    }

    /**
     * Get database metrics.
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $connections = DB::select('SHOW STATUS LIKE "Threads_connected"');
            $queries = DB::select('SHOW STATUS LIKE "Queries"');
            
            return [
                'active_connections' => $connections[0]->Value ?? 0,
                'total_queries' => $queries[0]->Value ?? 0,
                'connection_status' => 'connected',
            ];
        } catch (\Exception $e) {
            return [
                'connection_status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache metrics.
     */
    private function getCacheMetrics(): array
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $health = Cache::get('health_check');
            
            return [
                'status' => $health === 'ok' ? 'connected' : 'error',
                'hit_rate' => $this->getCacheHitRate(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get API metrics.
     */
    private function getApiMetrics(): array
    {
        return [
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'average_response_time' => $this->getAverageResponseTime(),
            'error_rate' => $this->getErrorRate(),
        ];
    }

    /**
     * Get CPU usage.
     */
    private function getCpuUsage(): float
    {
        // Simplified CPU usage calculation
        $load = sys_getloadavg();
        return $load ? $load[0] : 0.0;
    }

    /**
     * Get memory usage.
     */
    private function getMemoryUsage(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        return [
            'used' => $memoryUsage,
            'limit' => $memoryLimit,
            'percentage' => $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0,
        ];
    }

    /**
     * Get disk usage.
     */
    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percentage' => $total > 0 ? ($used / $total) * 100 : 0,
        ];
    }

    /**
     * Get cache hit rate.
     */
    private function getCacheHitRate(): float
    {
        // Simplified cache hit rate calculation
        return 85.0; // Placeholder
    }

    /**
     * Get requests per minute.
     */
    private function getRequestsPerMinute(): int
    {
        // Simplified requests per minute calculation
        return 120; // Placeholder
    }

    /**
     * Get average response time.
     */
    private function getAverageResponseTime(): float
    {
        // Simplified average response time calculation
        return 150.5; // Placeholder in milliseconds
    }

    /**
     * Get error rate.
     */
    private function getErrorRate(): float
    {
        // Simplified error rate calculation
        return 2.5; // Placeholder in percentage
    }

    /**
     * Get health check status.
     */
    public function getHealthCheck(): array
    {
        return [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [
                'database' => $this->getDatabaseMetrics()['connection_status'] ?? 'error',
                'cache' => $this->getCacheMetrics()['status'] ?? 'error',
                'redis' => $this->testRedisConnection(),
            ],
        ];
    }

    /**
     * Test Redis connection.
     */
    private function testRedisConnection(): string
    {
        try {
            \Illuminate\Support\Facades\Redis::ping();
            return 'connected';
        } catch (\Exception $e) {
            return 'error: ' . $e->getMessage();
        }
    }

    /**
     * Parse memory limit string.
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower($limit);
        $multiplier = 1;
        
        if (str_ends_with($limit, 'g')) {
            $multiplier = 1024 * 1024 * 1024;
            $limit = substr($limit, 0, -1);
        } elseif (str_ends_with($limit, 'm')) {
            $multiplier = 1024 * 1024;
            $limit = substr($limit, 0, -1);
        } elseif (str_ends_with($limit, 'k')) {
            $multiplier = 1024;
            $limit = substr($limit, 0, -1);
        }
        
        return (int) $limit * $multiplier;
    }
}
