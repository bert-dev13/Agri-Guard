<?php

namespace App\Http\Controllers;

use App\Services\CropImpactService;
use App\Services\FarmWeatherService;
use App\Services\FarmRiskSnapshotService;
use App\Services\RainfallHeatmapService;
use App\Services\ThreeDayWeatherOutlookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Weather API for frontend (e.g. weather widget).
 * Returns the same normalized weather data as Dashboard/Weather Details so values stay in sync.
 */
class WeatherController extends Controller
{
    public function index(
        Request $request,
        FarmWeatherService $farmWeather,
        CropImpactService $cropImpactService,
        FarmRiskSnapshotService $riskSnapshotService,
        ThreeDayWeatherOutlookService $threeDayOutlook
    ): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $data = $farmWeather->getNormalizedWeatherForUser($user);

        $current = $data['raw_current'];
        $forecast = $data['daily_forecast'];

        $response = [
            'updated_at' => $data['last_updated'] ? (new \DateTimeImmutable($data['last_updated']))->format(\DateTimeInterface::ATOM) : now()->toIso8601String(),
            'location' => [
                'display' => $data['location_name'],
                'query' => implode(', ', array_filter([$user->farm_municipality, 'Cagayan', 'Philippines'])),
            ],
            'current' => [
                'temp' => $data['current_temperature'],
                'feels_like' => $data['feels_like'],
                'humidity' => $data['humidity'],
                'pressure' => $data['pressure'],
                'wind_speed' => $data['wind_speed'],
                'sunrise' => $current['sunrise_ts'] ?? null,
                'sunset' => $current['sunset_ts'] ?? null,
                'condition' => $current['condition'] ?? ['id' => 800, 'main' => 'Clear', 'description' => '', 'icon' => '01d'],
            ],
            'forecast' => array_map(function ($day) {
                return [
                    'date' => $day['date'],
                    'day_name' => $day['day_name'],
                    'date_display' => $day['date_display'] ?? $day['date'],
                    'temp_min' => $day['temp_min'],
                    'temp_max' => $day['temp_max'],
                    'pop' => $day['pop'],
                    'condition' => $day['condition'],
                ];
            }, $forecast),
            'today_rain_probability' => $data['today_rain_probability'],
        ];

        $riskSnapshot = $riskSnapshotService->buildFromNormalizedWeather($user, $data);
        $weatherBlock = [
            'today_rain_probability' => $data['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $data['today_expected_rainfall'] ?? null,
            'wind_speed' => $data['wind_speed'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'temp' => $data['current_temperature'] ?? null,
            'condition' => [
                'id' => $data['condition_id'] ?? null,
                'main' => $data['condition'] ?? null,
            ],
        ];
        $rainfallSeverity = $this->rainfallSeverityFromWeather($weatherBlock, $forecast);

        $impactAdvisory = $cropImpactService->buildForecastImpactPayload(
            $user,
            $weatherBlock,
            $forecast,
            $rainfallSeverity
        );
        $response['impact_advisory'] = $impactAdvisory;
        $response['risk_snapshot'] = $riskSnapshot;
        $response['weather_outlook'] = $threeDayOutlook->build($weatherBlock, $forecast);

        return response()->json($response);
    }

    /**
     * Weather + rainfall context for given coordinates (e.g. device GPS).
     * Uses current GPS; no saved farm location.
     */
    public function byCoordinates(Request $request, FarmWeatherService $farmWeather, RainfallHeatmapService $heatmap): JsonResponse
    {
        if (! $request->user()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $lat = $request->query('lat');
        $lng = $request->query('lng');
        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['error' => 'Missing or invalid lat/lng.'], 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return response()->json(['error' => 'Coordinates out of range.'], 400);
        }

        $weather = $farmWeather->getNormalizedWeatherByCoordinates($lat, $lng);
        $rainfallAccumulationLabel = $this->rainfallAccumulationLabel(
            isset($weather['today_expected_rainfall']) ? (float) $weather['today_expected_rainfall'] : null
        );

        $heatmapPoints = $heatmap->buildPoints($lat, $lng, $weather);
        $rainfallIntensityLabel = $heatmap->intensityLabel($weather);
        $rainfallIntensityExplanation = $heatmap->intensityExplanation($weather);

        return response()->json([
            'weather' => [
                'location_name' => $weather['location_name'],
                'today_rain_probability' => $weather['today_rain_probability'],
                'today_expected_rainfall' => $weather['today_expected_rainfall'],
                'condition' => $weather['condition'],
                'condition_id' => $weather['condition_id'] ?? null,
                'last_updated' => $weather['last_updated'],
                'daily_forecast' => $weather['daily_forecast'] ?? [],
            ],
            'rainfall_accumulation_label' => $rainfallAccumulationLabel,
            'rainfall_intensity' => [
                'label' => $rainfallIntensityLabel,
                'explanation' => $rainfallIntensityExplanation,
            ],
            'heatmap_points' => $heatmapPoints,
            'coordinates' => ['lat' => $lat, 'lng' => $lng],
        ]);
    }

    private function rainfallAccumulationLabel(?float $todayExpectedRainfallMm): string
    {
        if ($todayExpectedRainfallMm === null) {
            return 'Unknown';
        }

        return match (true) {
            $todayExpectedRainfallMm >= 30.0 => 'High',
            $todayExpectedRainfallMm >= 10.0 => 'Moderate',
            $todayExpectedRainfallMm > 0 => 'Light',
            default => 'Minimal',
        };
    }

    /**
     * Legacy crop impact service expects a low/moderate/high severity string.
     */
    private function rainfallSeverityFromWeather(array $weatherBlock, array $forecast): string
    {
        $pop = is_numeric($weatherBlock['today_rain_probability'] ?? null)
            ? (int) $weatherBlock['today_rain_probability']
            : null;
        if ($pop === null && $forecast !== []) {
            $pops = array_filter(array_column($forecast, 'pop'), static fn ($v) => is_numeric($v));
            $pop = $pops !== [] ? (int) max($pops) : null;
        }

        if ($pop === null) {
            return 'low';
        }

        return match (true) {
            $pop >= 70 => 'high',
            $pop >= 40 => 'moderate',
            default => 'low',
        };
    }
}
