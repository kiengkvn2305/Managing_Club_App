<?php

namespace App\Providers;

use App\Models\Club;
use App\Policies\ClubPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Club::class, ClubPolicy::class);
        // This release supports web sessions only; native bearer auth has a separate release gate.
        \Laravel\Sanctum\Sanctum::getAccessTokenFromRequestUsing(fn (Request $request) => null);
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(hash('sha256', mb_strtolower(trim(is_string($request->input('email')) ? $request->input('email') : '')).'|'.$request->ip())),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
    }
}
