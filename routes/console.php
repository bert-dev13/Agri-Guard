<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\TogetherAiService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// Scheduled jobs — run by `php artisan schedule:work` (local) or a single
// `* * * * * php artisan schedule:run` cron entry (production).
// Goal: pre-compute the heaviest data so user-facing requests hit warm caches.
// ---------------------------------------------------------------------------

// Pre-warm shared aggregates + per-user weather/AI caches every 20 minutes.
// `withoutOverlapping` prevents pile-ups; `runInBackground` lets the scheduler
// keep ticking while the warmer is still finishing.
// `cron('*/20 * * * *')` is used instead of `everyTwentyMinutes()` for
// compatibility across Laravel 11/12 minor versions.
Schedule::command('agriguard:warm-caches', ['--limit=50'])
    ->cron('*/20 * * * *')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->onOneServer()
    ->name('agriguard.warm-caches');

// Lightweight version that only warms the shared/non-user caches every 5
// minutes — keeps OWM + ML prediction fresh without touching Together AI.
Schedule::command('agriguard:warm-caches', ['--limit=0', '--skip-ai', '--skip-weather'])
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->onOneServer()
    ->name('agriguard.warm-caches.light');

Artisan::command('together:test', function (TogetherAiService $togetherAiService) {
    $apiKey = (string) (config('togetherai.api_key') ?? config('services.togetherai.api_key'));
    $model = (string) (config('togetherai.model') ?? config('services.togetherai.model'));
    $baseUrl = (string) (config('togetherai.base_url') ?? config('services.togetherai.base_url'));

    if (trim($apiKey) === '') {
        $this->error('Together API key is not configured.');
        return self::FAILURE;
    }

    $this->info('Testing Together AI connectivity...');
    $this->line('Model: ' . $model);
    $this->line('Base URL: ' . $baseUrl);

    try {
        $result = $togetherAiService->generateRecommendation(
            [
                'farm_name' => 'Test Farm',
                'location' => 'Amulung, Cagayan',
                'crop_type' => 'Rice',
                'growth_stage' => 'growing',
                'weather' => [
                    'temperature' => 29,
                    'humidity' => 82,
                    'wind_speed' => 8,
                    'condition' => 'Cloudy',
                    'rain_chance' => 65,
                    'hourly_summary' => [
                        'morning_rain_chance' => 60,
                        'afternoon_rain_chance' => 70,
                        'evening_rain_chance' => 55,
                    ],
                ],
                'rainfall' => [
                    'today_mm' => 12,
                    'week_mm' => 56,
                    'month_mm' => 180,
                    'trend' => 'increasing',
                ],
                'system_flags' => [
                    'flood_risk' => false,
                    'soil_saturation' => false,
                    'irrigation_needed' => false,
                    'good_for_spraying' => false,
                ],
            ],
            'Return valid JSON only with a short dashboard recommendation.'
        );

        $this->info('Together AI request successful.');
        $this->line('Model used: ' . (string) ($result['model_used'] ?? $model));
        $preview = mb_substr(trim((string) ($result['raw_content'] ?? '')), 0, 300);
        $this->line('Response preview: ' . $preview);
        return self::SUCCESS;
    } catch (\Throwable $e) {
        $this->error('Together AI request failed: ' . $e->getMessage());
        return self::FAILURE;
    }
})->purpose('Test Together AI API key/model connectivity');
