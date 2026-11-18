<?php

namespace App\Http\Middleware;

use App\Models\Club;
use Closure;
use Illuminate\Http\Request;

class EnsureClubAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        $club = Club::whereKey($request->route('club'))->whereHas('memberships', fn ($query) => $query
            ->where('user_id', $request->user()->id)->where('status', 'active'))->firstOrFail();
        $request->attributes->set('club', $club);

        return $next($request);
    }
}
