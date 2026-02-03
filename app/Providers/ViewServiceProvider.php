<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share current user data with the sidebar view
        view()->composer('dashboard.partials.sidebar', function ($view) {
            $firebase = app(\App\Services\FirebaseService::class);
            $view->with('currentUser', $firebase->getCurrentUser());
        });
    }
}
