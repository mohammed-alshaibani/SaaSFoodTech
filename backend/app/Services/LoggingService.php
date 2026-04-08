<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoggingService
{
    /**
     * Log API request with structured data.
     */
    public function logApiRequest(Request $request, $response = null): void
    {
        $logData = [
            'type' => 'api_request',
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => Auth::id(),
            'request_id' => $request->attributes->get('request_id'),
            'request_size' => strlen($request->getContent()),
        ];

        if ($response) {
            $logData['response_status'] = $response->getStatusCode();
            $logData['response_size'] = strlen($response->getContent());
            $logData['response_time'] = $this->getResponseTime($request);
        }

        // Add request parameters (excluding sensitive data)
        $logData['parameters'] = $this->sanitizeParameters($request->all());

        Log::info('API Request', $logData);
    }

    /**
     * Log business events.
     */
    public function logBusinessEvent(string $event, array $data = [], $userId = null): void
    {
        $logData = [
            'type' => 'business_event',
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'user_id' => $userId ?? Auth::id(),
            'data' => $data,
        ];

        Log::info("Business Event: {$event}", $logData);
    }

    /**
     * Log security events.
     */
    public function logSecurityEvent(string $event, array $data = [], $level = 'warning'): void
    {
        $logData = [
            'type' => 'security_event',
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'user_id' => Auth::id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data,
        ];

        Log::{$level}("Security Event: {$event}", $logData);
    }

    /**
     * Log performance metrics.
     */
    public function logPerformance(string $operation, array $metrics): void
    {
        $logData = [
            'type' => 'performance',
            'timestamp' => now()->toISOString(),
            'operation' => $operation,
            'user_id' => Auth::id(),
            'metrics' => $metrics,
        ];

        Log::info("Performance: {$operation}", $logData);
    }

    /**
     * Log errors with context.
     */
    public function logError(\Throwable $exception, array $context = []): void
    {
        $logData = [
            'type' => 'error',
            'timestamp' => now()->toISOString(),
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'user_id' => Auth::id(),
            'request_id' => request()->attributes->get('request_id'),
            'context' => $context,
        ];

        Log::error('Application Error', $logData);
    }

    /**
     * Log database queries in development.
     */
    public function logDatabaseQuery($query, $bindings, $time): void
    {
        if (config('app.debug') && config('logging.log_queries', false)) {
            $logData = [
                'type' => 'database_query',
                'timestamp' => now()->toISOString(),
                'query' => $query,
                'bindings' => $bindings,
                'execution_time' => $time,
                'user_id' => Auth::id(),
            ];

            Log::debug('Database Query', $logData);
        }
    }

    /**
     * Log cache operations.
     */
    public function logCacheOperation(string $operation, string $key, $hit = null): void
    {
        if (config('logging.log_cache', false)) {
            $logData = [
                'type' => 'cache_operation',
                'timestamp' => now()->toISOString(),
                'operation' => $operation,
                'key' => $key,
                'hit' => $hit,
                'user_id' => Auth::id(),
            ];

            Log::debug("Cache: {$operation}", $logData);
        }
    }

    /**
     * Log external API calls.
     */
    public function logExternalApiCall(string $service, string $endpoint, array $params = [], $response = null, $error = null): void
    {
        $logData = [
            'type' => 'external_api_call',
            'timestamp' => now()->toISOString(),
            'service' => $service,
            'endpoint' => $endpoint,
            'params' => $this->sanitizeParameters($params),
            'user_id' => Auth::id(),
        ];

        if ($response) {
            $logData['response_status'] = $response['status'] ?? null;
            $logData['response_time'] = $response['time'] ?? null;
        }

        if ($error) {
            $logData['error'] = $error;
        }

        Log::info("External API: {$service}", $logData);
    }

    /**
     * Log authentication events.
     */
    public function logAuthEvent(string $event, array $data = [], $userId = null): void
    {
        $logData = [
            'type' => 'auth_event',
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'user_id' => $userId,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $data,
        ];

        $level = in_array($event, ['login_failed', 'token_revoked']) ? 'warning' : 'info';
        Log::{$level}("Auth: {$event}", $logData);
    }

    /**
     * Log RBAC events.
     */
    public function logRbacEvent(string $event, array $data = []): void
    {
        $logData = [
            'type' => 'rbac_event',
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'user_id' => Auth::id(),
            'ip' => request()->ip(),
            'data' => $data,
        ];

        Log::info("RBAC: {$event}", $logData);
    }

    /**
     * Log subscription events.
     */
    public function logSubscriptionEvent(string $event, array $data = []): void
    {
        $logData = [
            'type' => 'subscription_event',
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'user_id' => Auth::id(),
            'data' => $data,
        ];

        Log::info("Subscription: {$event}", $logData);
    }

    /**
     * Get response time from request.
     */
    private function getResponseTime(Request $request): float
    {
        $startTime = $request->attributes->get('request_start_time');
        return $startTime ? round((microtime(true) - $startTime) * 1000, 2) : 0;
    }

    /**
     * Sanitize parameters to remove sensitive data.
     */
    private function sanitizeParameters(array $parameters): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization'];
        
        return collect($parameters)->map(function ($value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                return '[REDACTED]';
            }
            
            if (is_array($value)) {
                return $this->sanitizeParameters($value);
            }
            
            return $value;
        })->toArray();
    }

    /**
     * Create structured log entry.
     */
    public function createLogEntry(string $level, string $message, array $context = []): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'request_id' => request()->attributes->get('request_id'),
            'user_id' => Auth::id(),
            'app_version' => config('app.version', 'unknown'),
            'environment' => config('app.env'),
        ];
    }

    /**
     * Log system health check.
     */
    public function logHealthCheck(array $checks): void
    {
        $logData = [
            'type' => 'health_check',
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
            'overall_status' => collect($checks)->every('status'),
        ];

        Log::info('Health Check', $logData);
    }

    /**
     * Log background job events.
     */
    public function logJobEvent(string $event, string $jobClass, array $data = []): void
    {
        $logData = [
            'type' => 'job_event',
            'timestamp' => now()->toISOString(),
            'event' => $event,
            'job_class' => $jobClass,
            'data' => $data,
        ];

        Log::info("Job: {$event}", $logData);
    }

    /**
     * Get log statistics for monitoring.
     */
    public function getLogStats(): array
    {
        // This would typically query your log storage
        // For now, return placeholder data
        return [
            'total_requests_today' => 0,
            'error_count_today' => 0,
            'avg_response_time' => 0,
            'unique_users_today' => 0,
            'top_errors' => [],
        ];
    }
}
