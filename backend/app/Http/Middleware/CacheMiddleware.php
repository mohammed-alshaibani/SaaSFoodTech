<?php

namespace App\Http\Middleware;

use App\Services\Cache\CacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CacheMiddleware
{
    protected CacheService $cacheService;
    protected array $cacheConfig;

    public function __construct(CacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        $this->cacheConfig = [
            // Routes to cache with TTL in seconds
            'GET:/api/requests' => 300, // 5 minutes
            'GET:/api/me' => 1800, // 30 minutes
            'GET:/api/users' => 600, // 10 minutes
            'GET:/api/statistics' => 900, // 15 minutes
            'GET:/api/analytics' => 1800, // 30 minutes
        ];
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        $cacheKey = $this->getCacheKey($request);
        $ttl = $this->getCacheTtl($request);

        // Check if response is cached
        if ($cachedResponse = $this->cacheService->get($cacheKey)) {
            return response()->json($cachedResponse)
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-TTL', $ttl);
        }

        // Process request
        $response = $next($request);

        // Cache successful responses
        if ($this->shouldCacheResponse($response) && $ttl > 0) {
            $responseData = json_decode($response->getContent(), true);
            $this->cacheService->set($cacheKey, $responseData, $ttl);
        }

        return $response
            ->header('X-Cache', 'MISS')
            ->header('X-Cache-TTL', $ttl);
    }

    /**
     * Generate cache key for request.
     */
    protected function getCacheKey(Request $request): string
    {
        $routeKey = $request->method() . ':' . $request->route()->uri();
        $params = $request->query();
        $user = $request->user();

        // Include user ID in cache key for personalized responses
        $userPart = $user ? ":user:{$user->id}" : '';

        // Sort query parameters to ensure consistent cache keys
        ksort($params);
        $queryPart = !empty($params) ? ':query:' . http_build_query($params) : '';

        return "response:{$routeKey}{$userPart}{$queryPart}";
    }

    /**
     * Get cache TTL for request.
     */
    protected function getCacheTtl(Request $request): int
    {
        $routeKey = $request->method() . ':' . $request->route()->uri();

        return $this->cacheConfig[$routeKey] ?? 0;
    }

    /**
     * Determine if response should be cached.
     */
    protected function shouldCacheResponse(Response $response): bool
    {
        if ($response instanceof JsonResponse) {
            $data = json_decode($response->getContent(), true);

            // Don't cache error responses
            if (isset($data['success']) && $data['success'] === false) {
                return false;
            }

            // Don't cache responses with sensitive data
            if (isset($data['data']['access_token']) || isset($data['data']['token'])) {
                return false;
            }
        }

        return $response->getStatusCode() === 200;
    }

    /**
     * Invalidate cache for specific routes.
     */
    public static function invalidate(array $patterns): void
    {
        $cacheService = app(CacheService::class);

        foreach ($patterns as $pattern) {
            // For Redis, we can use pattern matching
            if (config('cache.default') === 'redis') {
                $redisPattern = 'saasfoodtech:response:' . str_replace('*', '*', $pattern);
                $keys = \Illuminate\Support\Facades\Redis::keys($redisPattern);

                if (!empty($keys)) {
                    \Illuminate\Support\Facades\Redis::del($keys);
                }
            } else {
                // For other cache drivers, clear all cache
                $cacheService->clear();
            }
        }
    }
}
