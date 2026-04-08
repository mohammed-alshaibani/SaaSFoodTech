<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

class RateLimitByPlan
{
    /**
     * The rate limiter instance.
     */
    protected RateLimiter $limiter;

    /**
     * Create a new middleware instance.
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            // Apply default rate limiting for unauthenticated requests
            return $this->applyRateLimit($request, 'anonymous', 60, 60, $next); // 60 requests per hour
        }

        // Get rate limits based on user plan (default to 'free' if plan is null)
        $rateLimits = $this->getRateLimitsByPlan($user->plan ?? 'free');
        $endpoint = $this->getEndpointKey($request);

        // Apply specific endpoint limits
        if (isset($rateLimits['endpoints'][$endpoint])) {
            $limit = $rateLimits['endpoints'][$endpoint];
            return $this->applyRateLimit($request, $user->id . ':' . $endpoint, $limit['requests'], $limit['minutes'], $next);
        }

        // Apply general plan limits
        return $this->applyRateLimit($request, (string) $user->id, $rateLimits['general']['requests'], $rateLimits['general']['minutes'], $next);
    }

    /**
     * Get rate limits based on user subscription plan.
     */
    protected function getRateLimitsByPlan(string $plan): array
    {
        $limits = [
            'free' => [
                'general' => ['requests' => 100, 'minutes' => 60], // 100 requests per hour
                'endpoints' => [
                    'ai_enhance' => ['requests' => 5, 'minutes' => 60], // 5 AI enhancements per hour
                    'requests_create' => ['requests' => 10, 'minutes' => 60], // 10 request creations per hour
                    'auth_login' => ['requests' => 20, 'minutes' => 60], // 20 login attempts per hour
                ],
            ],
            'paid' => [
                'general' => ['requests' => 1000, 'minutes' => 60], // 1000 requests per hour
                'endpoints' => [
                    'ai_enhance' => ['requests' => 50, 'minutes' => 60], // 50 AI enhancements per hour
                    'requests_create' => ['requests' => 100, 'minutes' => 60], // 100 request creations per hour
                    'auth_login' => ['requests' => 50, 'minutes' => 60], // 50 login attempts per hour
                ],
            ],
        ];

        return $limits[$plan] ?? $limits['free'];
    }

    /**
     * Get endpoint key for specific rate limiting.
     */
    protected function getEndpointKey(Request $request): string
    {
        $route = $request->route();
        if (!$route) {
            return 'unknown';
        }

        $routeName = $route->getName();
        $method = $request->method();
        $uri = $route->uri();

        // Map specific endpoints to keys
        $endpointMap = [
            'api.ai.enhance' => 'ai_enhance',
            'api.requests.store' => 'requests_create',
            'api.auth.login' => 'auth_login',
            'api.auth.register' => 'auth_register',
        ];

        if (isset($endpointMap[$routeName])) {
            return $endpointMap[$routeName];
        }

        // Fallback to URI-based identification
        if (str_contains($uri, 'ai/')) {
            return 'ai_enhance';
        }
        if (str_contains($uri, 'requests') && $method === 'POST') {
            return 'requests_create';
        }
        if (str_contains($uri, 'login')) {
            return 'auth_login';
        }
        if (str_contains($uri, 'register')) {
            return 'auth_register';
        }

        return 'general';
    }

    /**
     * Apply rate limiting to the request.
     */
    protected function applyRateLimit(Request $request, string $key, int $maxAttempts, int $decayMinutes, Closure $next): Response
    {
        // Add request ID for tracking
        $requestId = $request->header('X-Request-ID') ?? uniqid('req_', true);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            $seconds = $this->limiter->availableIn($key);

            // Log rate limit violation
            $this->logRateLimitViolation($request, $key, $maxAttempts, $decayMinutes);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please try again later.',
                    'details' => [
                        'limit' => $maxAttempts,
                        'reset_in' => $seconds,
                        'reset_at' => now()->addSeconds($seconds)->toISOString(),
                    ],
                    'request_id' => $requestId,
                    'timestamp' => now()->toISOString(),
                ]
            ], 429)->header('Retry-After', $seconds)
                ->header('X-RateLimit-Limit', $maxAttempts)
                ->header('X-RateLimit-Remaining', 0)
                ->header('X-RateLimit-Reset', now()->addSeconds($seconds)->timestamp);
        }

        // Add rate limit headers to successful responses
        $response = $next($request);
        $remaining = $maxAttempts - $this->limiter->retriesLeft($key, $maxAttempts);

        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining));
        $response->headers->set('X-RateLimit-Reset', now()->addMinutes($decayMinutes)->timestamp);

        return $response;
    }

    /**
     * Log rate limit violations for security monitoring.
     */
    protected function logRateLimitViolation(Request $request, string $key, int $maxAttempts, int $decayMinutes): void
    {
        $logData = [
            'key' => $key,
            'max_attempts' => $maxAttempts,
            'decay_minutes' => $decayMinutes,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()->toISOString(),
        ];

        // Check if this might be a DDoS attack
        $recentViolations = Cache::get('rate_limit_violations:' . $request->ip(), 0);
        Cache::put('rate_limit_violations:' . $request->ip(), $recentViolations + 1, 300); // 5 minutes

        if ($recentViolations > 10) {
            $logData['potential_ddos'] = true;
            logger()->critical('Potential DDoS Attack Detected', $logData);
        } else {
            logger()->warning('Rate Limit Violation', $logData);
        }
    }
}
