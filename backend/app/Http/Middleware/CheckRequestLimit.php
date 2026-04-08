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

        if ($user && $user->plan === 'free') {
            $count = ServiceRequest::where('customer_id', $user->id)->count();
            if ($count >= 3) {
                return response()->json([
                    'message' => 'Free plan limit reached. Upgrade to create more requests.'
                ], 403);
            }
        }

        return $next($request);
    }
}
