<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CacheService
{
    /**
     * Cache service requests for performance.
     */
    public function cacheServiceRequests($requests, $userId = null, $ttl = 300): void
    {
        $key = $userId 
            ? "user_requests_{$userId}" 
            : 'all_requests';
            
        Cache::put($key, $requests, $ttl);
    }

    /**
     * Get cached service requests.
     */
    public function getCachedServiceRequests($userId = null): mixed
    {
        $key = $userId 
            ? "user_requests_{$userId}" 
            : 'all_requests';
            
        return Cache::get($key);
    }

    /**
     * Cache nearby requests with location-based key.
     */
    public function cacheNearbyRequests($requests, $lat, $lng, $radius, $ttl = 600): void
    {
        $locationKey = $this->generateLocationKey($lat, $lng, $radius);
        Cache::put("nearby_{$locationKey}", $requests, $ttl);
    }

    /**
     * Get cached nearby requests.
     */
    public function getCachedNearbyRequests($lat, $lng, $radius): mixed
    {
        $locationKey = $this->generateLocationKey($lat, $lng, $radius);
        return Cache::get("nearby_{$locationKey}");
    }

    /**
     * Cache user permissions for RBAC performance.
     */
    public function cacheUserPermissions($userId, $permissions, $ttl = 3600): void
    {
        Cache::put("user_permissions_{$userId}", $permissions, $ttl);
    }

    /**
     * Get cached user permissions.
     */
    public function getCachedUserPermissions($userId): mixed
    {
        return Cache::get("user_permissions_{$userId}");
    }

    /**
     * Cache role hierarchy for performance.
     */
    public function cacheRoleHierarchy($hierarchy, $ttl = 7200): void
    {
        Cache::put('role_hierarchy', $hierarchy, $ttl);
    }

    /**
     * Get cached role hierarchy.
     */
    public function getCachedRoleHierarchy(): mixed
    {
        return Cache::get('role_hierarchy');
    }

    /**
     * Cache AI processing results.
     */
    public function cacheAIResult($requestId, $result, $ttl = 86400): void
    {
        Cache::put("ai_result_{$requestId}", $result, $ttl);
    }

    /**
     * Get cached AI result.
     */
    public function getCachedAIResult($requestId): mixed
    {
        return Cache::get("ai_result_{$requestId}");
    }

    /**
     * Cache subscription limits for rate limiting.
     */
    public function cacheSubscriptionLimits($userId, $limits, $ttl = 1800): void
    {
        Cache::put("subscription_limits_{$userId}", $limits, $ttl);
    }

    /**
     * Get cached subscription limits.
     */
    public function getCachedSubscriptionLimits($userId): mixed
    {
        return Cache::get("subscription_limits_{$userId}");
    }

    /**
     * Increment request counter for rate limiting.
     */
    public function incrementRequestCounter($userId, $window = 3600): int
    {
        $key = "request_counter_{$userId}";
        $count = Redis::incr($key);
        
        if ($count === 1) {
            Redis::expire($key, $window);
        }
        
        return $count;
    }

    /**
     * Get current request count for rate limiting.
     */
    public function getRequestCount($userId): int
    {
        $key = "request_counter_{$userId}";
        return (int) Redis::get($key) ?? 0;
    }

    /**
     * Cache API response for performance.
     */
    public function cacheApiResponse($key, $data, $ttl = 300): void
    {
        Cache::put("api_response_{$key}", $data, $ttl);
    }

    /**
     * Get cached API response.
     */
    public function getCachedApiResponse($key): mixed
    {
        return Cache::get("api_response_{$key}");
    }

    /**
     * Cache user statistics.
     */
    public function cacheUserStats($userId, $stats, $ttl = 1800): void
    {
        Cache::put("user_stats_{$userId}", $stats, $ttl);
    }

    /**
     * Get cached user statistics.
     */
    public function getCachedUserStats($userId): mixed
    {
        return Cache::get("user_stats_{$userId}");
    }

    /**
     * Cache system metrics.
     */
    public function cacheSystemMetrics($metrics, $ttl = 300): void
    {
        Cache::put('system_metrics', $metrics, $ttl);
    }

    /**
     * Get cached system metrics.
     */
    public function getCachedSystemMetrics(): mixed
    {
        return Cache::get('system_metrics');
    }

    /**
     * Invalidate user-specific cache.
     */
    public function invalidateUserCache($userId): void
    {
        $patterns = [
            "user_requests_{$userId}",
            "user_permissions_{$userId}",
            "subscription_limits_{$userId}",
            "user_stats_{$userId}",
        ];

        foreach ($patterns as $key) {
            Cache::forget($key);
        }

        // Also invalidate Redis counters
        Redis::del("request_counter_{$userId}");
    }

    /**
     * Invalidate role-related cache.
     */
    public function invalidateRoleCache(): void
    {
        Cache::forget('role_hierarchy');
        
        // Invalidate all user permissions since roles changed
        $this->invalidatePattern('user_permissions_*');
    }

    /**
     * Invalidate request-related cache.
     */
    public function invalidateRequestCache($requestId = null): void
    {
        if ($requestId) {
            Cache::forget("ai_result_{$requestId}");
        }
        
        // Invalidate all request caches
        Cache::forget('all_requests');
        $this->invalidatePattern('user_requests_*');
        $this->invalidatePattern('nearby_*');
    }

    /**
     * Invalidate cache by pattern.
     */
    public function invalidatePattern($pattern): void
    {
        $redis = Redis::connection();
        $keys = $redis->keys("*{$pattern}*");
        
        if (!empty($keys)) {
            $redis->del($keys);
        }
    }

    /**
     * Generate location-based cache key.
     */
    private function generateLocationKey($lat, $lng, $radius): string
    {
        // Round coordinates to create cache-friendly keys
        $latRounded = round($lat, 2);
        $lngRounded = round($lng, 2);
        $radiusRounded = round($radius);
        
        return "{$latRounded}_{$lngRounded}_{$radiusRounded}";
    }

    /**
     * Get cache statistics.
     */
    public function getCacheStats(): array
    {
        $redis = Redis::connection();
        $info = $redis->info('memory');
        
        return [
            'redis_memory_used' => $info['used_memory_human'] ?? 'unknown',
            'redis_memory_peak' => $info['used_memory_peak_human'] ?? 'unknown',
            'redis_keyspace_hits' => $redis->info('stats')['keyspace_hits'] ?? 0,
            'redis_keyspace_misses' => $redis->info('stats')['keyspace_misses'] ?? 0,
            'hit_rate' => $this->calculateHitRate($redis),
        ];
    }

    /**
     * Calculate cache hit rate.
     */
    private function calculateHitRate($redis): float
    {
        $stats = $redis->info('stats');
        $hits = $stats['keyspace_hits'] ?? 0;
        $misses = $stats['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Warm up cache with common data.
     */
    public function warmupCache(): void
    {
        try {
            // Cache role hierarchy
            $roleHierarchy = \App\Models\Role::with(['parentRoles', 'childRoles'])
                ->get()
                ->keyBy('id');
            $this->cacheRoleHierarchy($roleHierarchy);

            // Cache system metrics
            $metrics = [
                'total_users' => \App\Models\User::count(),
                'total_requests' => \App\Models\ServiceRequest::count(),
                'active_requests' => \App\Models\ServiceRequest::where('status', 'pending')->count(),
            ];
            $this->cacheSystemMetrics($metrics);

            Log::info('Cache warmup completed');

        } catch (\Exception $e) {
            Log::error('Cache warmup failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear all cache (for maintenance).
     */
    public function clearAllCache(): void
    {
        Cache::flush();
        Redis::flushdb();
        
        Log::info('All cache cleared');
    }
}
