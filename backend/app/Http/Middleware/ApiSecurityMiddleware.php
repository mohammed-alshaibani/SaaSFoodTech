<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. IP Blacklist check (Dynamic only, no hardcoded ranges)
        if (Cache::has("blacklisted_ip_{$request->ip()}")) {
            Log::warning('Blocked request from blacklisted IP', ['ip' => $request->ip()]);
            abort(403, 'Access denied');
        }

        // 2. Add request ID for tracking
        $requestId = $request->header('X-Request-ID') ?? uniqid('req_', true);
        $request->headers->set('X-Request-ID', $requestId);

        // 3. Validate Content-Type for write requests
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->header('Content-Type', '');
            if (!str_contains($contentType, 'application/json')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Content-Type must be application/json.',
                    'request_id' => $requestId,
                ], 415);
            }
        }

        $response = $next($request);

        // 4. Standard Security Headers
        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self';");
        
        return $response;
    }
}
