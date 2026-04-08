<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class CacheService
{
    protected int $defaultTtl = 3600; // 1 hour
    protected string $prefix = 'saasfoodtech:';

    /**
     * Get cached data or execute callback and cache result.
     */
    public function remember(string $key, callable $callback, int $ttl = null): mixed
    {
        $fullKey = $this->prefix . $key;
        $ttl = $ttl ?? $this->defaultTtl;

        return Cache::remember($fullKey, $ttl, $callback);
    }

    /**
     * Get cached data or execute callback and cache result forever.
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        $fullKey = $this->prefix . $key;

        return Cache::rememberForever($fullKey, $callback);
    }

    /**
     * Get cached data.
     */
    public function get(string $key): mixed
    {
        $fullKey = $this->prefix . $key;

        return Cache::get($fullKey);
    }

    /**
     * Set cached data.
     */
    public function set(string $key, mixed $value, int $ttl = null): bool
    {
        $fullKey = $this->prefix . $key;
        $ttl = $ttl ?? $this->defaultTtl;

        return Cache::put($fullKey, $value, $ttl);
    }

    /**
     * Delete cached data.
     */
    public function forget(string $key): bool
    {
        $fullKey = $this->prefix . $key;

        return Cache::forget($fullKey);
    }

    /**
     * Check if key exists in cache.
     */
    public function has(string $key): bool
    {
        $fullKey = $this->prefix . $key;

        return Cache::has($fullKey);
    }

    /**
     * Clear all cache entries with prefix.
     */
    public function clear(): bool
    {
        if (config('cache.default') === 'redis') {
            $pattern = $this->prefix . '*';
            $keys = Redis::keys($pattern);

            if (!empty($keys)) {
                return Redis::del($keys) > 0;
            }
        }

        return Cache::flush();
    }

    /**
     * Cache user data.
     */
    public function cacheUser(int $userId, array $userData, int $ttl = null): void
    {
        $key = "user:{$userId}";
        $this->set($key, $userData, $ttl);
    }

    /**
     * Get cached user data.
     */
    public function getUser(int $userId): ?array
    {
        $key = "user:{$userId}";

        return $this->get($key);
    }

    /**
     * Forget user cache.
     */
    public function forgetUser(int $userId): void
    {
        $key = "user:{$userId}";
        $this->forget($key);
    }

    /**
     * Cache service request data.
     */
    public function cacheServiceRequest(int $requestId, array $requestData, int $ttl = null): void
    {
        $key = "service_request:{$requestId}";
        $this->set($key, $requestData, $ttl);
    }

    /**
     * Get cached service request data.
     */
    public function getServiceRequest(int $requestId): ?array
    {
        $key = "service_request:{$requestId}";

        return $this->get($key);
    }

    /**
     * Forget service request cache.
     */
    public function forgetServiceRequest(int $requestId): void
    {
        $key = "service_request:{$requestId}";
        $this->forget($key);
    }

    /**
     * Cache user permissions.
     */
    public function cacheUserPermissions(int $userId, array $permissions, int $ttl = null): void
    {
        $key = "user_permissions:{$userId}";
        $this->set($key, $permissions, $ttl);
    }

    /**
     * Get cached user permissions.
     */
    public function getUserPermissions(int $userId): ?array
    {
        $key = "user_permissions:{$userId}";

        return $this->get($key);
    }

    /**
     * Forget user permissions cache.
     */
    public function forgetUserPermissions(int $userId): void
    {
        $key = "user_permissions:{$userId}";
        $this->forget($key);
    }

    /**
     * Cache statistics data.
     */
    public function cacheStatistics(string $type, array $data, int $ttl = null): void
    {
        $key = "statistics:{$type}";
        $this->set($key, $data, $ttl);
    }

    /**
     * Get cached statistics data.
     */
    public function getStatistics(string $type): ?array
    {
        $key = "statistics:{$type}";

        return $this->get($key);
    }

    /**
     * Forget statistics cache.
     */
    public function forgetStatistics(string $type): void
    {
        $key = "statistics:{$type}";
        $this->forget($key);
    }

    /**
     * Cache search results.
     */
    public function cacheSearchResults(string $query, array $results, int $ttl = null): void
    {
        $key = "search:" . md5($query);
        $this->set($key, $results, $ttl);
    }

    /**
     * Get cached search results.
     */
    public function getSearchResults(string $query): ?array
    {
        $key = "search:" . md5($query);

        return $this->get($key);
    }

    /**
     * Forget search results cache.
     */
    public function forgetSearchResults(string $query): void
    {
        $key = "search:" . md5($query);
        $this->forget($key);
    }

    /**
     * Cache rate limiting data.
     */
    public function cacheRateLimit(string $key, int $count, int $ttl = null): void
    {
        $rateLimitKey = "rate_limit:{$key}";
        $this->set($rateLimitKey, $count, $ttl);
    }

    /**
     * Get cached rate limiting data.
     */
    public function getRateLimit(string $key): ?int
    {
        $rateLimitKey = "rate_limit:{$key}";

        return $this->get($rateLimitKey);
    }

    /**
     * Increment rate limit counter.
     */
    public function incrementRateLimit(string $key, int $ttl = null): int
    {
        $rateLimitKey = "rate_limit:{$key}";

        if (config('cache.default') === 'redis') {
            $fullKey = $this->prefix . $rateLimitKey;
            $ttl = $ttl ?? $this->defaultTtl;

            return Redis::incr($fullKey);
        }

        $current = $this->getRateLimit($key) ?? 0;
        $new = $current + 1;
        $this->cacheRateLimit($key, $new, $ttl);

        return $new;
    }

    /**
     * Cache nearby service requests.
     */
    public function cacheNearbyRequests(float $latitude, float $longitude, float $radius, array $requests, int $ttl = null): void
    {
        $key = "nearby:" . round($latitude, 2) . ":" . round($longitude, 2) . ":{$radius}";
        $this->set($key, $requests, $ttl);
    }

    /**
     * Get cached nearby service requests.
     */
    public function getNearbyRequests(float $latitude, float $longitude, float $radius): ?array
    {
        $key = "nearby:" . round($latitude, 2) . ":" . round($longitude, 2) . ":{$radius}";

        return $this->get($key);
    }

    /**
     * Forget nearby requests cache.
     */
    public function forgetNearbyRequests(float $latitude, float $longitude, float $radius): void
    {
        $key = "nearby:" . round($latitude, 2) . ":" . round($longitude, 2) . ":{$radius}";
        $this->forget($key);
    }

    /**
     * Cache API response.
     */
    public function cacheApiResponse(string $endpoint, array $params, array $response, int $ttl = null): void
    {
        $key = "api_response:" . md5($endpoint . serialize($params));
        $this->set($key, $response, $ttl);
    }

    /**
     * Get cached API response.
     */
    public function getApiResponse(string $endpoint, array $params): ?array
    {
        $key = "api_response:" . md5($endpoint . serialize($params));

        return $this->get($key);
    }

    /**
     * Forget API response cache.
     */
    public function forgetApiResponse(string $endpoint, array $params): void
    {
        $key = "api_response:" . md5($endpoint . serialize($params));
        $this->forget($key);
    }

    /**
     * Cache file metadata.
     */
    public function cacheFileMetadata(string $filename, array $metadata, int $ttl = null): void
    {
        $key = "file_metadata:" . md5($filename);
        $this->set($key, $metadata, $ttl);
    }

    /**
     * Get cached file metadata.
     */
    public function getFileMetadata(string $filename): ?array
    {
        $key = "file_metadata:" . md5($filename);

        return $this->get($key);
    }

    /**
     * Forget file metadata cache.
     */
    public function forgetFileMetadata(string $filename): void
    {
        $key = "file_metadata:" . md5($filename);
        $this->forget($key);
    }

    /**
     * Get cache statistics.
     */
    public function getCacheStats(): array
    {
        if (config('cache.default') === 'redis') {
            $info = Redis::info('memory');
            $keys = Redis::keys($this->prefix . '*');

            return [
                'driver' => 'redis',
                'total_keys' => count($keys),
                'memory_usage' => $info['used_memory_human'] ?? 'unknown',
                'memory_peak' => $info['used_memory_peak_human'] ?? 'unknown',
                'hit_rate' => $this->calculateHitRate(),
            ];
        }

        return [
            'driver' => config('cache.default'),
            'total_keys' => 'unknown',
            'memory_usage' => 'unknown',
            'hit_rate' => 'unknown',
        ];
    }

    /**
     * Calculate cache hit rate.
     */
    protected function calculateHitRate(): float
    {
        if (config('cache.default') === 'redis') {
            $stats = Redis::info('stats');
            $hits = $stats['keyspace_hits'] ?? 0;
            $misses = $stats['keyspace_misses'] ?? 0;
            $total = $hits + $misses;

            return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
        }

        return 0;
    }

    /**
     * Warm up cache with common data.
     */
    public function warmup(): void
    {
        // Cache common statistics
        $this->cacheStatistics('users', [
            'total' => \App\Models\User::count(),
            'customers' => \App\Models\User::role('customer')->count(),
            'providers' => \App\Models\User::role(['provider_admin', 'provider_employee'])->count(),
        ], 3600);

        $this->cacheStatistics('service_requests', [
            'total' => \App\Models\ServiceRequest::count(),
            'pending' => \App\Models\ServiceRequest::where('status', 'pending')->count(),
            'accepted' => \App\Models\ServiceRequest::where('status', 'accepted')->count(),
            'completed' => \App\Models\ServiceRequest::where('status', 'completed')->count(),
        ], 3600);

        // Cache active providers
        $activeProviders = \App\Models\User::role(['provider_admin', 'provider_employee'])
            ->whereHas('serviceRequests', function ($query) {
                $query->whereIn('status', ['accepted', 'completed']);
            })
            ->get()
            ->toArray();

        $this->set('active_providers', $activeProviders, 1800);
    }

    /**
     * Clear expired cache entries.
     */
    public function cleanup(): void
    {
        if (config('cache.default') === 'redis') {
            // Redis handles expiration automatically
            return;
        }

        // For other drivers, implement cleanup logic
        $this->clear();
    }
}
