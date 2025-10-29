<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register broadcast routes with auth:sanctum middleware
        Broadcast::routes(['middleware' => ['auth:sanctum']]);

        // Load the channels file manually (Laravel 12 minimal install doesn't include it)
        require base_path('routes/channels.php');
    }
}
