<?php

namespace App\Providers;

use App\Services\AiAdvisory\AiAdvisoryService;
use App\Services\BarangayFloodRiskOverviewService;
use App\Services\FarmWeatherService;
use App\Services\WeatherAdvisoryService;
use App\Services\WeatherPredictionService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Heavy data services are bound as singletons so:
     *   - Their per-request memoization actually shares state across all callers.
     *     A single dashboard render hits FarmWeatherService from advisory, AI
     *     recommendation, three-day outlook, etc.; without singleton binding
     *     each layer would resolve a fresh instance and re-do the cache lookup.
     *   - Heavy constructor wiring (e.g. injected child services) only runs once
     *     per request.
     */
    public function register(): void
    {
        $this->app->singleton(FarmWeatherService::class);
        $this->app->singleton(WeatherAdvisoryService::class);
        $this->app->singleton(WeatherPredictionService::class);
        $this->app->singleton(BarangayFloodRiskOverviewService::class);
        $this->app->singleton(AiAdvisoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        URL::forceScheme('https');

        $root = rtrim((string) config('app.url'), '/');
        if ($root !== '') {
            URL::forceRootUrl($root);
        }
    }
}
