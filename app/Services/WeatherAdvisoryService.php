<?php

namespace App\Services;

use App\Models\HistoricalWeather;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Advisory and chart data built on top of FarmWeatherService (single source of truth).
 * All weather values for advisory/risk come from the same normalized weather data.
 */
class WeatherAdvisoryService
{
    public function __construct(
        private readonly FarmWeatherService $farmWeather,
        private readonly RuleBasedAdvisoryService $ruleBasedAdvisory,
        private readonly SmartAdvisoryEngine $smartAdvisory
    ) {}

    /**
     * Convert wind direction degrees to compass label (N, NE, E, SE, S, SW, W, NW).
     */
    public static function windDirectionLabel(?float $deg): string
    {
        if ($deg === null) {
            return '—';
        }
        $labels = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
        $idx = (int) round($deg / 45) % 8;
        return $labels[$idx < 0 ? $idx + 8 : $idx];
    }

    /**
     * Get full advisory data: weather, forecast, risk, charts.
     * All values come from FarmWeatherService so Dashboard, Weather Details, and Advisory stay in sync.
     */
    public function getAdvisoryData(User $user): array
    {
        $normalized = $this->farmWeather->getNormalizedWeatherForUser($user);

        $weather = $this->mapNormalizedToWeatherBlock($normalized);
        $forecast = $normalized['daily_forecast'] ?? [];
        $hourlyForecast = $normalized['hourly_forecast'] ?? [];
        $locationDisplay = $this->locationDisplay($user);

        $todayRainProbability = $normalized['today_rain_probability'] ?? null;
        $forecastRainfallMm = $this->farmWeather->getMaxForecastRainfallMm($user);
        $forecastRainProbability = $todayRainProbability;
        if ($forecastRainProbability === null && ! empty($forecast)) {
            $pops = array_filter(array_column($forecast, 'pop'), fn ($v) => $v !== null);
            $forecastRainProbability = ! empty($pops) ? (int) max($pops) : null;
        }
        $rainProbabilityDisplay = $todayRainProbability ?? $forecastRainProbability;

        $advisoryPayload = [
            'weather' => $weather,
            'forecast' => $forecast,
            'forecast_rainfall_mm' => $forecastRainfallMm,
            'forecast_rain_probability' => $forecastRainProbability,
            'location_display' => $locationDisplay,
        ];

        $currentMonth = (int) now()->format('n');
        $currentMonthHistoricalAvg = HistoricalWeather::averageRainfallForMonth($currentMonth);
        $monthlyTrend = $this->getMonthlyRainfallTrend();
        $yearlyTotals = $this->getTotalRainfallByYear();
        $heavyRainfallStats = $this->getHeavyRainfallStats();

        $advisory = $this->ruleBasedAdvisory->generateForUser($user, $advisoryPayload);
        $smartAdvisory = $this->smartAdvisory->enhance($advisory, [
            'crop_type' => $user->crop_type,
            'farming_stage' => $user->farming_stage,
            'field_condition' => null,
            'rainfall_probability' => $forecastRainProbability,
            'forecast_summary' => $this->forecastSummary($forecast),
        ]);

        $rainfallInsight = $this->buildRainfallInsight($monthlyTrend, $user->crop_type, $user->farm_municipality);

        return [
            'weather' => $weather,
            'forecast' => $forecast,
            'hourly_forecast' => $hourlyForecast,
            'forecast_rainfall_mm' => $forecastRainfallMm,
            'forecast_rain_probability' => $forecastRainProbability,
            'today_rain_probability' => $todayRainProbability,
            'rain_probability_display' => $rainProbabilityDisplay,
            'current_month_historical_avg_rain' => $currentMonthHistoricalAvg,
            'advisory' => $advisory,
            'smart_advisory' => $smartAdvisory,
            'rainfall_insight' => $rainfallInsight,
            'charts' => [
                'monthly_trend' => $monthlyTrend,
                'yearly_totals' => $yearlyTotals,
                'heavy_rainfall' => $heavyRainfallStats,
            ],
            'location_display' => $locationDisplay,
            'weather_data' => $this->toViewWeatherData($normalized),
            'last_updated' => $normalized['last_updated'] ?? null,
        ];
    }

    /**
     * Normalized structure for views: same keys across Dashboard, Weather Details, Advisory.
     */
    private function toViewWeatherData(array $normalized): array
    {
        return [
            'location_name' => $normalized['location_name'] ?? null,
            'current_temperature' => $normalized['current_temperature'] ?? null,
            'condition' => $normalized['condition'] ?? null,
            'feels_like' => $normalized['feels_like'] ?? null,
            'humidity' => $normalized['humidity'] ?? null,
            'wind_speed' => $normalized['wind_speed'] ?? null,
            'pressure' => $normalized['pressure'] ?? null,
            'visibility_km' => $normalized['visibility_km'] ?? null,
            'uv_index' => $normalized['uv_index'] ?? null,
            'today_rain_probability' => $normalized['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $normalized['today_expected_rainfall'] ?? null,
            'sunrise' => $normalized['sunrise'] ?? null,
            'sunset' => $normalized['sunset'] ?? null,
            'hourly_forecast' => $normalized['hourly_forecast'] ?? [],
            'daily_forecast' => $normalized['daily_forecast'] ?? [],
            'last_updated' => $normalized['last_updated'] ?? null,
        ];
    }

    private function mapNormalizedToWeatherBlock(array $normalized): ?array
    {
        $raw = $normalized['raw_current'] ?? null;
        if ($raw === null) {
            return null;
        }
        return [
            'temp' => $normalized['current_temperature'],
            'feels_like' => $normalized['feels_like'],
            'humidity' => $normalized['humidity'],
            'pressure' => $normalized['pressure'],
            'wind_speed' => $normalized['wind_speed'],
            'wind_direction' => $normalized['wind_direction'] ?? null,
            'visibility_km' => $normalized['visibility_km'] ?? null,
            'uv_index' => $normalized['uv_index'] ?? null,
            'today_rain_probability' => $normalized['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $normalized['today_expected_rainfall'] ?? null,
            'sunrise' => $raw['sunrise_ts'] ?? null,
            'sunset' => $raw['sunset_ts'] ?? null,
            'condition' => $raw['condition'] ?? ['id' => 800, 'main' => 'Clear', 'description' => '', 'icon' => '01d'],
        ];
    }

    /**
     * Monthly rainfall trend for chart: month => average rainfall.
     */
    public function getMonthlyRainfallTrend(): array
    {
        $rows = HistoricalWeather::monthlyRainfallTrend();
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $data = [];
        foreach ($rows as $row) {
            $m = (int) $row->month;
            $data[] = [
                'month' => $months[$m - 1] ?? (string) $m,
                'avg_rain' => round((float) $row->avg_rain, 2),
            ];
        }
        return $data;
    }

    /**
     * Total rainfall by year for chart.
     */
    public function getTotalRainfallByYear(): array
    {
        $rows = HistoricalWeather::totalRainfallByYear();
        return $rows->map(fn ($row) => [
            'year' => (string) $row->year,
            'total_rainfall' => round((float) $row->total_rainfall, 2),
        ])->all();
    }

    /**
     * Heavy rainfall frequency: total count and by year for chart.
     */
    public function getHeavyRainfallStats(): array
    {
        $total = HistoricalWeather::heavyRainfallCount();
        $byYear = HistoricalWeather::heavyRainfallByYear();
        return [
            'total_count' => $total,
            'by_year' => $byYear->map(fn ($row) => [
                'year' => (string) $row->year,
                'count' => (int) $row->count,
            ])->values()->all(),
        ];
    }

    /**
     * Build a short rainfall insight from historical monthly data. Crop-aware where possible.
     */
    private function buildRainfallInsight(array $monthlyTrend, ?string $cropType, ?string $municipality): string
    {
        $place = $municipality ?: 'Amulung';
        if (empty($monthlyTrend)) {
            return "Prepare drainage and monitor flood-prone fields during the rainy season.";
        }

        $byMonth = [];
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        foreach ($monthlyTrend as $row) {
            $idx = array_search($row['month'] ?? '', $monthNames);
            if ($idx !== false) {
                $byMonth[$idx + 1] = (float) ($row['avg_rain'] ?? 0);
            }
        }
        if (empty($byMonth)) {
            return "Prepare drainage and monitor flood-prone fields during the rainy season.";
        }

        $avg = array_sum($byMonth) / count($byMonth);
        $wetMonths = [];
        $fullNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        foreach ($byMonth as $m => $rain) {
            if ($rain >= $avg * 1.2) {
                $wetMonths[] = $fullNames[$m - 1] ?? (string) $m;
            }
        }

        $cropTip = '';
        $cropLower = $cropType ? strtolower($cropType) : '';
        if (str_contains($cropLower, 'rice')) {
            $cropTip = ' Rice farmers should prepare irrigation and drainage systems.';
        } elseif (str_contains($cropLower, 'corn')) {
            $cropTip = ' Corn farmers should watch runoff and protect roots during heavy rain.';
        } elseif (str_contains($cropLower, 'vegetable')) {
            $cropTip = ' Vegetable farmers should use raised beds and cover sensitive crops.';
        }

        if (empty($wetMonths)) {
            return "Based on historical data, rainfall in {$place} varies. Prepare drainage and monitor your field regularly.{$cropTip}";
        }

        $range = count($wetMonths) >= 3
            ? $wetMonths[0] . ' to ' . end($wetMonths)
            : implode(', ', $wetMonths);

        return "Based on historical data, rainfall in {$place} usually increases from {$range}. Farmers should prepare drainage during these months.{$cropTip}";
    }

    private function locationDisplay(User $user): string
    {
        return $user->farm_location_display;
    }

    private function forecastSummary(array $forecast): ?string
    {
        if (empty($forecast)) {
            return null;
        }
        $pops = array_filter(array_column($forecast, 'pop'), fn ($v) => $v !== null);
        $maxRain = ! empty($pops) ? max($pops) : 0;
        $rainDays = count(array_filter($forecast, fn ($d) => (($d['pop'] ?? 0) >= 50)));
        if ($maxRain >= 70) {
            return 'High chance of rain in the next 5 days.';
        }
        if ($rainDays >= 2) {
            return 'Rain possible on several days.';
        }
        return $maxRain >= 50 ? 'Rain possible within 24 hours.' : null;
    }
}
