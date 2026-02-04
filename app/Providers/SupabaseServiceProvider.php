<?php

namespace App\Providers;

use App\Services\SupabaseStorageService;
use Illuminate\Support\ServiceProvider;

class SupabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(SupabaseStorageService::class, function ($app) {
            return new SupabaseStorageService();
        });

        $this->app->alias(SupabaseStorageService::class, 'supabase.storage');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}