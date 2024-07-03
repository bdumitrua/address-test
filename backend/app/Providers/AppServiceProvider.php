<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\YandexGeoService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(YandexGeoService::class, function ($app) {
            return new YandexGeoService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
