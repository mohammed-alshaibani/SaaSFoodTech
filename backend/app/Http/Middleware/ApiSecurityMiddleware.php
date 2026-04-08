<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Add request ID for tracking
        $requestId = $request->header('X-Request-ID') ?? uniqid('req_', true);
        $request->headers->set('X-Request-ID', $requestId);

        // Validate API version
        $apiVersion = $request->header('X-API-Version', '1.0');
        if (!in_array($apiVersion, ['1.0', '1.1'])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNSUPPORTED_API_VERSION',
                    'message' => 'API version not supported. Use v1.0 or v1.1.',
                    'request_id' => $requestId,
                    'timestamp' => now()->toISOString(),
                ]
            ], 400);
        }

        // Validate Content-Type for POST/PUT/PATCH requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->header('Content-Type', '');
            if (!str_contains($contentType, 'application/json')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_CONTENT_TYPE',
                        'message' => 'Content-Type must be application/json.',
                        'request_id' => $requestId,
                        'timestamp' => now()->toISOString(),
                    ]
                ], 415);
            }
        }

        // Check for required security headers in production
        if (app()->environment('production')) {
            $requiredHeaders = [
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/json',
            ];

            foreach ($requiredHeaders as $header => $expectedValue) {
                if (!$request->hasHeader($header)) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'MISSING_SECURITY_HEADER',
                            'message' => "Missing required security header: {$header}",
                            'request_id' => $requestId,
                            'timestamp' => now()->toISOString(),
                        ]
                    ], 400);
                }
            }
        }

        // Input sanitization and validation
        $this->sanitizeInput($request);

        // Log API request for monitoring
        $this->logApiRequest($request, $requestId);

        // Add security headers to response
        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-API-Version', $apiVersion);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Sanitize input data to prevent XSS attacks.
     */
    protected function sanitizeInput(Request $request): void
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret', 'key'];

        $input = $request->all();

        array_walk_recursive($input, function (&$value, $key) use ($sensitiveFields) {
            // Skip sanitization for sensitive fields and non-string values
            if (in_array($key, $sensitiveFields) || !is_string($value)) {
                return;
            }

            // Remove potential XSS content
            $value = strip_tags($value);
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false);

            // Remove suspicious patterns
            $suspiciousPatterns = [
                '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
                '/javascript:/i',
                '/on\w+\s*=/i',
            ];

            foreach ($suspiciousPatterns as $pattern) {
                $value = preg_replace($pattern, '', $value);
            }
        });

        $request->merge($input);
    }

    /**
     * Log API requests for monitoring and security analysis.
     */
    protected function logApiRequest(Request $request, string $requestId): void
    {
        $logData = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'content_length' => $request->header('Content-Length'),
            'api_version' => $request->header('X-API-Version'),
            'timestamp' => now()->toISOString(),
        ];

        // Don't log sensitive data
        $excludeKeys = ['password', 'password_confirmation', 'token', 'secret', 'key'];
        $input = $request->all();
        foreach ($excludeKeys as $key) {
            unset($input[$key]);
        }

        if (!empty($input)) {
            $logData['input_keys'] = array_keys($input);
        }

        logger()->info('API Request', $logData);
    }
}
