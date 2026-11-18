<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePasswordSession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $guard = Auth::guard('web'); $user = $guard->user();
        if (! $user || ! $request->hasSession()) return $next($request);
        $fingerprint = hash_hmac('sha256', $user->getAuthPassword(), (string) config('app.key'));
        $stored = $request->session()->get('auth_password_fingerprint');
        if (is_string($stored) && ! hash_equals($stored, $fingerprint)) {
            $guard->logoutCurrentDevice(); $request->session()->invalidate(); $request->session()->regenerateToken();
            throw new AuthenticationException('Phiên đã hết hiệu lực. Vui lòng đăng nhập lại.');
        }
        $request->session()->put('auth_password_fingerprint', $fingerprint);
        $response = $next($request);
        if ($guard->check()) $request->session()->put('auth_password_fingerprint', hash_hmac('sha256', $guard->user()->getAuthPassword(), (string) config('app.key')));
        return $response;
    }
}
