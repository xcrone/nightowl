<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
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
        // This is an API-only app — there's no server-rendered login page to
        // redirect an unauthenticated browser request to. Without this,
        // Laravel's default Authenticate middleware tries to build a
        // redirect to a route named 'login' (which doesn't exist here) for
        // any request that doesn't send Accept: application/json, and
        // crashes with a 500 instead of a clean 401.
        Authenticate::redirectUsing(fn () => null);
    }
}
