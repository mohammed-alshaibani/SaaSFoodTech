<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Authentication Middleware
 * 
 * Ensures API routes return JSON 401 responses instead of web redirects
 * when authentication fails.
 */
class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        // Set guard to sanctum for API routes
        $guards = empty($guards) ? ['sanctum'] : $guards;
        
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::shouldUse($guard);
                return $next($request);
            }
        }

        // Return JSON 401 for API requests instead of redirecting
        if ($request->expectsJson() || $request->is('api/*') || $request->wantsJson()) {
            return response()->json([
                'message' => 'Unauthenticated. Please provide a valid authentication token.',
                'error' => 'Unauthorized',
            ], 401);
        }

        // For non-API requests, let Laravel handle the redirect
        return redirect()->guest(route('login'));
    }
}
