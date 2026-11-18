<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless($request->user()?->is_active, 403, 'Tài khoản đã bị vô hiệu hóa.');

        return $next($request);
    }
}
