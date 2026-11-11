<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRegistrationRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->is('register')) {
            $key = 'registration|'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, 10)) {
                return response()->noContent(429, [
                    'Retry-After' => (string) RateLimiter::availableIn($key),
                ]);
            }

            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
