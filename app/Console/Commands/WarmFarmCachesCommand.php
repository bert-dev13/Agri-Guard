<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AiAdvisory\AiAdvisoryService;
use App\Services\AiRecommendationService;
use App\Services\BarangayFloodRiskOverviewService;
use App\Services\FarmRecommendationService;
use App\Services\FarmWeatherService;
use App\Services\WeatherAdvisoryService;
use App\Services\WeatherPredictionService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Pre-warm the heaviest caches in the background so authenticated page loads
 * are cache-hits during peak hours.
 *
 * Targets:
 *  - Per-farmer normalized weather (FarmWeatherService 15 min cache)
 *  - Shared ML weather prediction (WeatherPredictionService cache)
 *  - Historical aggregates / monthly trend (WeatherAdvisoryService caches)
 *  - Barangay flood risk overview (BarangayFloodRiskOverviewService cache)
 *  - AI Smart Advisory for the dashboard page (AiAdvisoryService fingerprint cache)
 *
 * Designed to be tolerant: every section is wrapped in try/catch so partial
 * failures never abort the whole warmup. Use `--limit` to cap how many farmers
 * are touched per run on shared hosting.
 */
class WarmFarmCachesCommand extends Command
{
    protected $signature = 'agriguard:warm-caches
                            {--limit=50 : Maximum number of farmer accounts to warm per run}
                            {--skip-ai : Skip AI advisory warmup (saves Together AI quota)}
                            {--skip-weather : Skip OpenWeatherMap warmup (saves API quota)}';

    protected $description = 'Pre-warm shared and per-user caches so page loads stay snappy.';

    public function handle(
        FarmWeatherService $farmWeather,
        WeatherAdvisoryService $weatherAdvisory,
        WeatherPredictionService $weatherPrediction,
        BarangayFloodRiskOverviewService $floodOverview,
        AiRecommendationService $aiRecommendation,
        FarmRecommendationService $farmRecommendation
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $skipAi = (bool) $this->option('skip-ai');
        $skipWeather = (bool) $this->option('skip-weather');

        // 1) Shared historical aggregates & flood overview (cheap, no external API).
        $this->section('Warming historical aggregates');
        $this->safely(static function () use ($weatherAdvisory): void {
            $weatherAdvisory->getMonthlyRainfallTrend();
            $weatherAdvisory->getTotalRainfallByYear();
            $weatherAdvisory->getHeavyRainfallStats();
        }, 'historical aggregates');

        $this->section('Warming barangay flood overview');
        $this->safely(static function () use ($floodOverview): void {
            $floodOverview->overviewForMunicipality('Amulung');
        }, 'flood overview');

        // 2) Shared ML prediction (one Python invocation; benefits every farmer).
        $this->section('Warming ML weather prediction');
        $this->safely(static function () use ($weatherPrediction): void {
            $weatherPrediction->predict();
        }, 'ML prediction');

        // 3) Per-user warmups — capped so we never make the warmer the slow path.
        $farmers = User::query()->farmers()
            ->whereNotNull('farm_municipality')
            ->select(['id', 'name', 'farm_municipality', 'farm_barangay', 'farm_barangay_code', 'crop_type', 'farming_stage', 'planting_date', 'crop_timeline_offset_days'])
            ->limit($limit)
            ->get();

        $this->section("Warming {$farmers->count()} farmer profile(s)");
        foreach ($farmers as $user) {
            if (! $skipWeather) {
                $this->safely(static function () use ($farmWeather, $user): void {
                    $farmWeather->getNormalizedWeatherForUser($user);
                }, "weather for user {$user->id}");
            }

            if (! $skipAi) {
                $this->safely(function () use ($aiRecommendation, $user, $weatherAdvisory): void {
                    $advisoryData = $weatherAdvisory->getAdvisoryData($user);
                    $weather = $advisoryData['weather'] ?? [];
                    $forecast = $advisoryData['forecast'] ?? [];
                    $aiRecommendation->generateSmartRecommendation($user, [
                        'barangay' => trim((string) ($user->farm_barangay_name ?? '')),
                        'weather' => [
                            'temperature' => $weather['temp'] ?? null,
                            'humidity' => $weather['humidity'] ?? null,
                            'wind_speed' => $weather['wind_speed'] ?? null,
                            'condition' => $weather['condition']['main'] ?? 'Unknown',
                            'rain_chance' => $weather['today_rain_probability'] ?? null,
                            'today_expected_rainfall_mm' => $weather['today_expected_rainfall'] ?? null,
                        ],
                        'forecast_next_days' => array_slice($forecast, 0, 5),
                    ], 'dashboard');
                }, "AI advisory for user {$user->id}");
            }
        }

        $this->info('Cache warmup complete.');

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->line("<fg=cyan>•</> {$title}");
    }

    private function safely(callable $fn, string $context): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->warn("  ! {$context} skipped: {$e->getMessage()}");
        }
    }
}
