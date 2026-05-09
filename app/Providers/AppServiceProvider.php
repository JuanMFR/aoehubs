<?php

namespace App\Providers;

use App\Models\GameMatch;
use App\Observers\MatchObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Observa cuando un match pasa a 'completed' para otorgar awards
        // instant (Centurion, streak, climber, etc.).
        GameMatch::observe(MatchObserver::class);

        // Rate limiters para companion API endpoints. Por user (sanctum
        // token), no por IP — varios users detras del mismo NAT no se
        // bloquean entre si.
        RateLimiter::for('companion-poll', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('companion-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('companion-replay', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Auth web: login + callback + logout. Por IP — auth previene fuerza
        // bruta de OpenID callbacks. 30/min es generoso pero corta scans.
        RateLimiter::for('auth-web', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
        // Public profile / leaderboard scrape protection.
        RateLimiter::for('public-read', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
