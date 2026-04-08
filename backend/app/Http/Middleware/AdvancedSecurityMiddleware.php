<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AdvancedSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Add security headers
        $response = $next($request);
        $this->addSecurityHeaders($response, $request);
        
        // Log security events
        $this->logSecurityEvent($request, $response);
        
        // Check for suspicious patterns
        $this->checkSuspiciousActivity($request);
        
        return $response;
    }

    /**
     * Add security headers to response.
     */
    private function addSecurityHeaders(Response $response, Request $request): void
    {
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Enable XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Strict transport security
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        
        // Content security policy
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';";
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions policy
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // Remove server information
        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');
    }

    /**
     * Log security-related events.
     */
    private function logSecurityEvent(Request $request, Response $response): void
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Log suspicious user agents
        if ($this->isSuspiciousUserAgent($userAgent)) {
            Log::warning('Suspicious user agent detected', [
                'ip' => $ip,
                'user_agent' => $userAgent,
                'endpoint' => $request->path(),
            ]);
        }
        
        // Log failed authentication attempts
        if ($response->getStatusCode() === 401 && $request->is('api/*')) {
            $this->incrementFailedAttempts($ip);
            
            Log::warning('Authentication failure', [
                'ip' => $ip,
                'endpoint' => $request->path(),
                'attempts' => $this->getFailedAttempts($ip),
            ]);
        }
        
        // Log authorization failures
        if ($response->getStatusCode() === 403) {
            Log::warning('Authorization failure', [
                'ip' => $ip,
                'endpoint' => $request->path(),
                'user_id' => auth()->id(),
                'method' => $request->method(),
            ]);
        }
        
        // Log potential SQL injection attempts
        if ($this->containsSqlInjection($request)) {
            Log::alert('Potential SQL injection attempt', [
                'ip' => $ip,
                'endpoint' => $request->path(),
                'parameters' => $this->sanitizeParameters($request->all()),
                'user_agent' => $userAgent,
            ]);
        }
        
        // Log XSS attempts
        if ($this->containsXss($request)) {
            Log::alert('Potential XSS attempt', [
                'ip' => $ip,
                'endpoint' => $request->path(),
                'parameters' => $this->sanitizeParameters($request->all()),
                'user_agent' => $userAgent,
            ]);
        }
    }

    /**
     * Check for suspicious activity patterns.
     */
    private function checkSuspiciousActivity(Request $request): void
    {
        $ip = $request->ip();
        
        // Check for rapid requests from same IP
        $key = "rapid_requests_{$ip}";
        $count = Cache::increment($key, 1, 60); // Count requests in last minute
        
        if ($count > 100) { // More than 100 requests per minute
            Log::warning('Rapid request pattern detected', [
                'ip' => $ip,
                'count' => $count,
                'endpoint' => $request->path(),
            ]);
            
            // Apply stricter rate limiting
            abort(429, 'Too many requests');
        }
        
        // Check for requests from suspicious IPs
        if ($this->isSuspiciousIp($ip)) {
            Log::warning('Request from suspicious IP', [
                'ip' => $ip,
                'endpoint' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);
            
            abort(403, 'Access denied');
        }
        
        // Check for unusual request patterns
        if ($this->hasUnusualPattern($request)) {
            Log::info('Unusual request pattern', [
                'ip' => $ip,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'headers' => $this->getRelevantHeaders($request),
            ]);
        }
    }

    /**
     * Check if user agent is suspicious.
     */
    private function isSuspiciousUserAgent($userAgent): bool
    {
        $suspiciousPatterns = [
            '/bot/i',
            '/crawler/i',
            '/scanner/i',
            '/nmap/i',
            '/sqlmap/i',
            '/nikto/i',
            '/dirbuster/i',
            '/gobuster/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is suspicious.
     */
    private function isSuspiciousIp($ip): bool
    {
        // Check against known malicious IP ranges
        $suspiciousRanges = [
            '10.0.0.0/8',     // Private network
            '172.16.0.0/12',   // Private network
            '192.168.0.0/16',  // Private network
            '127.0.0.0/8',     // Loopback
        ];
        
        foreach ($suspiciousRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        
        // Check against cached blacklist
        return Cache::has("blacklisted_ip_{$ip}");
    }

    /**
     * Check for SQL injection patterns.
     */
    private function containsSqlInjection(Request $request): bool
    {
        $sqlPatterns = [
            '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
            '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
            '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))w*((\%6F)|o|(\%4F))/i',
            '/((\%27)|(\'))union/ix',
            '/exec(\s|\+)+(s|x)p\w+/ix',
            '/UNION[^a-zA-Z]/i',
            '/SELECT.*FROM/i',
            '/INSERT.*INTO/i',
            '/UPDATE.*SET/i',
            '/DELETE.*FROM/i',
        ];
        
        $parameters = $request->all();
        
        foreach ($parameters as $key => $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Check for XSS patterns.
     */
    private function containsXss(Request $request): bool
    {
        $xssPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<object\b[^<]*<[^<]*<\/object>/i',
            '/<embed\b[^<]*<[^<]*<\/embed>/i',
            '/<applet\b[^<]*<[^<]*<\/applet>/i',
            '/<meta\b[^<]*<[^<]*<\/meta>/i',
        ];
        
        $parameters = $request->all();
        
        foreach ($parameters as $key => $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Check for unusual request patterns.
     */
    private function hasUnusualPattern(Request $request): bool
    {
        // Check for common attack patterns
        $path = $request->path();
        
        $attackPatterns = [
            '/\.\./',           // Directory traversal
            '/\/etc\/passwd/',    // System file access
            '/\/proc\//',        // System file access
            '/\/windows\//',     // System file access
            '/admin/i',           // Admin path access
            '/wp-admin/i',        // WordPress admin
            '/phpmyadmin/i',      // Database admin
        ];
        
        foreach ($attackPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        
        // Check for unusual header combinations
        $headers = $request->headers;
        if ($headers->has('X-Forwarded-For') && 
            $headers->has('X-Real-IP') && 
            $headers->has('X-Originating-IP')) {
            return true; // Multiple IP forwarding headers
        }
        
        return false;
    }

    /**
     * Check if IP is in given range.
     */
    private function ipInRange($ip, $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($range_ip, $netmask) = explode('/', $range);
        $range_decimal = ip2long($range_ip);
        $ip_decimal = ip2long($ip);
        $netmask_decimal = -1 << (32 - $netmask);
        
        return ($ip_decimal & $netmask_decimal) === ($range_decimal & $netmask_decimal);
    }

    /**
     * Increment failed authentication attempts.
     */
    private function incrementFailedAttempts($ip): void
    {
        $key = "failed_auth_{$ip}";
        Cache::increment($key, 1, 300); // Keep for 5 minutes
    }

    /**
     * Get failed authentication attempts.
     */
    private function getFailedAttempts($ip): int
    {
        $key = "failed_auth_{$ip}";
        return (int) Cache::get($key, 0);
    }

    /**
     * Sanitize parameters for logging.
     */
    private function sanitizeParameters(array $parameters): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'api_key'];
        
        return collect($parameters)->map(function ($value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                return '[REDACTED]';
            }
            
            if (is_string($value) && strlen($value) > 200) {
                return substr($value, 0, 200) . '...';
            }
            
            return $value;
        })->toArray();
    }

    /**
     * Get relevant headers for logging.
     */
    private function getRelevantHeaders(Request $request): array
    {
        $relevantHeaders = [
            'Authorization',
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Originating-IP',
            'User-Agent',
            'Referer',
            'Content-Type',
        ];
        
        $headers = [];
        foreach ($relevantHeaders as $header) {
            if ($request->headers->has($header)) {
                $headers[$header] = $request->headers->get($header);
            }
        }
        
        return $headers;
    }

    /**
     * Add IP to blacklist.
     */
    public static function blacklistIp($ip, $duration = 3600): void
    {
        Cache::put("blacklisted_ip_{$ip}", true, $duration);
        
        Log::warning('IP blacklisted', [
            'ip' => $ip,
            'duration' => $duration,
        ]);
    }

    /**
     * Remove IP from blacklist.
     */
    public static function unblacklistIp($ip): void
    {
        Cache::forget("blacklisted_ip_{$ip}");
        
        Log::info('IP removed from blacklist', [
            'ip' => $ip,
        ]);
    }

    /**
     * Get security statistics.
     */
    public static function getSecurityStats(): array
    {
        return [
            'blacklisted_ips' => Cache::keys('blacklisted_ip_*'),
            'failed_attempts_today' => self::getFailedAttemptsCount(),
            'suspicious_activities' => self::getSuspiciousActivityCount(),
            'blocked_requests' => self::getBlockedRequestCount(),
        ];
    }

    /**
     * Get failed attempts count for today.
     */
    private static function getFailedAttemptsCount(): int
    {
        // This would typically query your logs
        return 0;
    }

    /**
     * Get suspicious activity count for today.
     */
    private static function getSuspiciousActivityCount(): int
    {
        // This would typically query your logs
        return 0;
    }

    /**
     * Get blocked request count for today.
     */
    private static function getBlockedRequestCount(): int
    {
        // This would typically query your logs
        return 0;
    }
}
