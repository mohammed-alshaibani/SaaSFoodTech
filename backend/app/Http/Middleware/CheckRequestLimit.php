<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ServiceRequest;

class CheckRequestLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasExceededRequestLimit()) {
            return response()->json([
                'success' => false,
                'message' => 'Your monthly service request limit has been reached. Please upgrade your plan to create more requests.'
            ], 403);
        }

        return $next($request);
    }
}
