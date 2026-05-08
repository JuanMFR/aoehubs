<?php

namespace App\Providers;

use App\Models\GameMatch;
use App\Observers\MatchObserver;
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
    }
}
