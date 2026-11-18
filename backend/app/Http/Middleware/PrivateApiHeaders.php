<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PrivateApiHeaders
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        if ($request->is('api/*', 'sanctum/*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Frame-Options', 'DENY');

        return $response;
    }
}
