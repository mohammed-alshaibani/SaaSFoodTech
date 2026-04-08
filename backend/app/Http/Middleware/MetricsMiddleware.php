<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\MetricsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MetricsMiddleware
{
    protected MetricsService $metricsService;

    public function __construct(MetricsService $metricsService)
    {
        $this->metricsService = $metricsService;
    }

    /**
     * Handle an incoming request and record metrics.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $startTime = microtime(true);
        
        // Process the request
        $response = $next($request);
        
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        // Record API request metrics
        $this->recordRequestMetrics($request, $response, $duration);
        
        // Add metrics headers
        $response->headers->set('X-Response-Time', round($duration, 2) . 'ms');
        $response->headers->set('X-Metrics-Recorded', 'true');
        
        return $response;
    }

    /**
     * Record request metrics.
     */
    protected function recordRequestMetrics(Request $request, Response $response, float $duration): void
    {
        $endpoint = $this->getEndpointName($request);
        $method = $request->method();
        $statusCode = $response->getStatusCode();
        
        // Build tags for better categorization
        $tags = [
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => (string)$statusCode,
            'status_class' => $this->getStatusCodeClass($statusCode),
            'user_authenticated' => $request->user() ? 'true' : 'false',
        ];

        // Add user role if authenticated
        if ($request->user()) {
            $userRoles = $request->user()->getRoleNames()->toArray();
            $tags['user_role'] = !empty($userRoles) ? $userRoles[0] : 'unknown';
        }

        // Record the metrics
        $this->metricsService->recordApiRequest($endpoint, $method, $statusCode, $duration, $tags);

        // Record slow requests
        if ($duration > 1000) { // > 1 second
            $this->metricsService->increment('slow_requests_total', $tags);
        }

        // Record large responses
        $contentLength = strlen($response->getContent());
        if ($contentLength > 1048576) { // > 1MB
            $this->metricsService->increment('large_responses_total', array_merge($tags, [
                'size_category' => $this->getSizeCategory($contentLength)
            ]));
        }
    }

    /**
     * Get endpoint name for metrics.
     */
    protected function getEndpointName(Request $request): string
    {
        $route = $request->route();
        
        if ($route && $route->getName()) {
            return $route->getName();
        }
        
        // Fallback to URI pattern
        $uri = $request->route()->uri();
        
        // Replace IDs with placeholders for better grouping
        $uri = preg_replace('/\{[^}]+\}/', '{id}', $uri);
        
        return $uri;
    }

    /**
     * Get status code class.
     */
    protected function getStatusCodeClass(int $statusCode): string
    {
        return floor($statusCode / 100) . 'xx';
    }

    /**
     * Get size category for responses.
     */
    protected function getSizeCategory(int $size): string
    {
        if ($size < 1024) {
            return 'small';
        } elseif ($size < 1048576) {
            return 'medium';
        } elseif ($size < 10485760) {
            return 'large';
        } else {
            return 'xlarge';
        }
    }
}
