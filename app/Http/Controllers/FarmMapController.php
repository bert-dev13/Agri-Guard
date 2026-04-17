<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FarmWeatherService;
use App\Services\FarmRiskSnapshotService;
use App\Services\FloodRiskAssessmentService;
use App\Services\MapSmartAdvisoryService;
use App\Services\RainfallHeatmapService;
use App\Services\RuleBasedAdvisoryService;
use App\Services\SmartAdvisoryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FarmMapController extends Controller
{
    private const LOCATION_DEVICE_GPS = 'device_gps';

    public function index(): View
    {
        $user = Auth::user();
        assert($user instanceof User);

        return view('user.map.index', [
            'initialHasDeviceGps' => $this->userHasDeviceGps($user),
        ]);
    }

    public function saveGpsLocation(Request $request, FarmWeatherService $farmWeather): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'min:-90', 'max:90'],
            'longitude' => ['required', 'numeric', 'min:-180', 'max:180'],
        ]);

        $lat = round((float) $validated['latitude'], 7);
        $lng = round((float) $validated['longitude'], 7);

        $user->forceFill([
            'farm_lat' => $lat,
            'farm_lng' => $lng,
            'gps_captured_at' => now(),
            'location_source' => self::LOCATION_DEVICE_GPS,
        ])->save();

        $farmWeather->invalidateCacheForUser($user);

        return response()->json([
            'success' => true,
            'message' => 'Farm location saved from your device GPS.',
            'latitude' => $lat,
            'longitude' => $lng,
            'gps_captured_at' => $user->gps_captured_at?->toIso8601String(),
            'location_source' => $user->location_source,
        ]);
    }

    public function farmContext(
        Request $request,
        FarmWeatherService $farmWeather,
        FloodRiskAssessmentService $floodRisk,
        RainfallHeatmapService $heatmap,
        RuleBasedAdvisoryService $ruleBasedAdvisory,
        SmartAdvisoryEngine $smartAdvisory,
        MapSmartAdvisoryService $mapSmartAdvisory,
        FarmRiskSnapshotService $riskSnapshotService
    ): JsonResponse {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $user->refresh();

        $mapLayer = strtolower(trim((string) $request->query('map_layer', 'farm')));
        if (! in_array($mapLayer, ['farm', 'weather', 'rainfall', 'flood'], true)) {
            $mapLayer = 'farm';
        }

        return response()->json($this->buildFarmContextPayload(
            $user,
            $farmWeather,
            $floodRisk,
            $heatmap,
            $ruleBasedAdvisory,
            $smartAdvisory,
            $mapSmartAdvisory,
            $riskSnapshotService,
            $mapLayer
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFarmContextPayload(
        User $user,
        FarmWeatherService $farmWeather,
        FloodRiskAssessmentService $floodRisk,
        RainfallHeatmapService $heatmap,
        RuleBasedAdvisoryService $ruleBasedAdvisory,
        SmartAdvisoryEngine $smartAdvisory,
        MapSmartAdvisoryService $mapSmartAdvisory,
        FarmRiskSnapshotService $riskSnapshotService,
        string $mapLayer = 'farm'
    ): array {
        $safeName = trim((string) ($user->name ?? ''));
        $farmName = $safeName !== '' ? $safeName.' Farm' : 'My Farm';
        $stageLabel = $user->farming_stage
            ? ucfirst(str_replace('_', ' ', (string) $user->farming_stage))
            : null;

        $base = [
            'farm_name' => $farmName,
            'crop_type' => $user->crop_type,
            'growth_stage' => $stageLabel,
            'barangay' => $user->farm_barangay_name,
            'municipality' => $user->farm_municipality,
            'location_display' => $user->farm_location_display,
            'latitude' => null,
            'longitude' => null,
            'gps_captured_at' => null,
            'location_source' => $user->location_source,
            'has_device_gps' => false,
            'map_ready' => false,
        ];

        if (! $this->userHasDeviceGps($user)) {
            return array_merge($base, [
                'message' => 'Exact map placement requires GPS from this device. Tap “Use My GPS Location” to save your farm position.',
                'weather' => null,
                'rainfall_context' => null,
                'flood_risk' => null,
                'advisory' => null,
                'smart_advisory' => null,
                'popup_summary' => null,
                'overlays' => null,
                'weekly_rainfall_summary' => null,
                'recommendation' => null,
                'map_insight_lines' => [
                    'Save your farm GPS to unlock map insights and layers tailored to your field.',
                ],
                'today_means_lines' => [
                    'After GPS is saved, you will see what today’s weather means for your decisions.',
                ],
                'map_smart_advisory' => null,
                'risk_snapshot' => [
                    'estimated_crop_loss' => 'N/A',
                    'three_day_effect' => 'No forecast impact available',
                    'flood_risk_level' => 'Unknown',
                ],
            ]);
        }

        $lat = (float) $user->farm_lat;
        $lng = (float) $user->farm_lng;

        $weather = $farmWeather->getNormalizedWeatherByCoordinates($lat, $lng);
        $riskSnapshot = $riskSnapshotService->buildFromNormalizedWeather($user, $weather);
        $userContext = [];
        $floodResult = $floodRisk->assess($weather, $userContext);
        $rainfallAccumulationLabel = $floodRisk->rainfallAccumulationLabel(
            isset($weather['today_expected_rainfall']) ? (float) $weather['today_expected_rainfall'] : null
        );

        $heatmapPoints = $heatmap->buildPoints($lat, $lng, $weather);
        $rainfallIntensityLabel = $heatmap->intensityLabel($weather);
        $rainfallIntensityExplanation = $heatmap->intensityExplanation($weather);

        $forecastMm = $this->maxDailyRainMm($weather['daily_forecast'] ?? []);
        $forecastProb = $weather['today_rain_probability'] ?? null;
        if ($forecastProb === null && ! empty($weather['daily_forecast'])) {
            $pops = array_filter(array_column($weather['daily_forecast'], 'pop'), fn ($v) => $v !== null);
            $forecastProb = ! empty($pops) ? (int) max($pops) : null;
        }

        $weatherBlock = [
            'temp' => $weather['current_temperature'] ?? null,
            'condition' => $weather['condition'],
            'wind_speed' => $weather['wind_speed'] ?? null,
            'today_rain_probability' => $weather['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $weather['today_expected_rainfall'] ?? null,
        ];

        $advisoryPayload = [
            'weather' => $weatherBlock,
            'forecast_rainfall_mm' => $forecastMm,
            'forecast_rain_probability' => $forecastProb,
        ];

        $advisory = $ruleBasedAdvisory->generateForUser($user, $advisoryPayload);
        $forecastSummary = $this->forecastSummarySnippet($weather['daily_forecast'] ?? []);

        $smart = $smartAdvisory->enhance($advisory, [
            'crop_type' => $user->crop_type,
            'farming_stage' => $user->farming_stage,
            'field_condition' => null,
            'rainfall_probability' => $forecastProb,
            'forecast_summary' => $forecastSummary,
        ]);

        $mapSmartAdvisoryPayload = $mapSmartAdvisory->build(
            $user,
            $weather,
            $floodResult,
            [
                'intensity_label' => $rainfallIntensityLabel,
                'accumulation_label' => $rainfallAccumulationLabel,
            ],
            $advisory,
            $smart,
            $forecastMm,
            ['selected_layer' => $mapLayer]
        );

        $weeklySummary = $this->weeklyRainfallSummary($weather['daily_forecast'] ?? []);

        $popupSummary = $this->buildPopupSummary(
            $farmName,
            $weather,
            $floodResult['label'] ?? '—',
            $rainfallIntensityLabel
        );

        $rainColor = $this->rainfallOverlayColor($rainfallIntensityLabel);
        $floodColor = $this->floodOverlayColor($floodResult['level'] ?? 'LOW');

        $mapInsightLines = array_values(
            array_filter(
                $mapSmartAdvisoryPayload['map_insight'] ?? [],
                fn ($line) => is_string($line) && trim($line) !== ''
            )
        );
        $todayMeansLines = $this->buildTodayMeansLines($smart, $advisory);

        return array_merge($base, [
            'latitude' => $lat,
            'longitude' => $lng,
            'gps_captured_at' => $user->gps_captured_at?->toIso8601String(),
            'has_device_gps' => true,
            'map_ready' => true,
            'message' => null,
            'map_insight_lines' => $mapInsightLines,
            'today_means_lines' => $todayMeansLines,
            'weather' => [
                'location_name' => $weather['location_name'] ?? null,
                'current_temperature' => $weather['current_temperature'] ?? null,
                'condition' => $weather['condition'] ?? null,
                'condition_id' => $weather['condition_id'] ?? null,
                'feels_like' => $weather['feels_like'] ?? null,
                'humidity' => $weather['humidity'] ?? null,
                'wind_speed' => $weather['wind_speed'] ?? null,
                'wind_direction_deg' => $weather['wind_direction'] ?? null,
                'wind_direction_label' => \App\Services\WeatherAdvisoryService::windDirectionLabel(
                    isset($weather['wind_direction']) ? (float) $weather['wind_direction'] : null
                ),
                'today_rain_probability' => $weather['today_rain_probability'] ?? null,
                'today_expected_rainfall' => $weather['today_expected_rainfall'] ?? null,
                'last_updated' => $weather['last_updated'] ?? null,
                'daily_forecast' => $weather['daily_forecast'] ?? [],
            ],
            'rainfall_context' => [
                'intensity_label' => $rainfallIntensityLabel,
                'intensity_explanation' => $rainfallIntensityExplanation,
                'accumulation_label' => $rainfallAccumulationLabel,
                'forecast_max_daily_mm' => $forecastMm,
                'heatmap_points' => $heatmapPoints,
            ],
            'flood_risk' => [
                'level' => $floodResult['level'] ?? 'LOW',
                'label' => $floodResult['label'] ?? '—',
                'color' => $floodResult['color'] ?? '#64748b',
                'message' => $floodResult['message'] ?? '',
            ],
            'advisory' => $advisory,
            'smart_advisory' => $smart,
            'map_smart_advisory' => $mapSmartAdvisoryPayload,
            'risk_snapshot' => $riskSnapshot,
            'popup_summary' => $popupSummary,
            'weekly_rainfall_summary' => $weeklySummary,
            'recommendation' => $smart['short_recommendation'] ?? ($advisory['recommended_action'] ?? null),
            'overlays' => [
                'rainfall' => [
                    'label' => 'Rainfall context (forecast-based)',
                    'fill_color' => $rainColor,
                    'radius_m' => $this->rainfallRadiusM($rainfallIntensityLabel),
                ],
                'flood' => [
                    'label' => 'Flood risk context (advisory estimate, not exact flood GIS)',
                    'fill_color' => $floodColor,
                    'radius_m' => $this->floodRadiusM($floodResult['level'] ?? 'LOW'),
                ],
            ],
        ]);
    }

    private function userHasDeviceGps(User $user): bool
    {
        return $user->location_source === self::LOCATION_DEVICE_GPS
            && $user->farm_lat !== null
            && $user->farm_lng !== null;
    }

    /**
     * @param  list<array<string, mixed>>  $daily
     */
    private function maxDailyRainMm(array $daily): ?float
    {
        if ($daily === []) {
            return null;
        }
        $max = 0.0;
        foreach ($daily as $day) {
            $mm = (float) ($day['rain_mm'] ?? 0);
            if ($mm > $max) {
                $max = $mm;
            }
        }

        return $max > 0 ? round($max, 1) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $daily
     */
    private function weeklyRainfallSummary(array $daily): ?string
    {
        if ($daily === []) {
            return null;
        }
        $total = 0.0;
        foreach ($daily as $day) {
            $total += (float) ($day['rain_mm'] ?? 0);
        }
        $total = round($total, 1);
        $days = count($daily);

        return "Next {$days}-day forecast: about {$total} mm total rain (model estimate).";
    }

    /**
     * @param  list<array<string, mixed>>  $forecast
     */
    private function forecastSummarySnippet(array $forecast): ?string
    {
        if ($forecast === []) {
            return null;
        }
        $pops = array_filter(array_column($forecast, 'pop'), fn ($v) => $v !== null);
        $maxRain = ! empty($pops) ? max($pops) : 0;
        $rainDays = count(array_filter($forecast, fn ($d) => (($d['pop'] ?? 0) >= 50)));
        if ($maxRain >= 70) {
            return 'High chance of rain in the next few days.';
        }
        if ($rainDays >= 2) {
            return 'Rain possible on several days.';
        }
        if ($maxRain >= 50) {
            return 'Rain possible within 24–48 hours.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $weather
     */
    private function buildPopupSummary(string $farmName, array $weather, string $floodLabel, string $rainIntensity): string
    {
        $temp = isset($weather['current_temperature']) ? (string) $weather['current_temperature'].'°C' : '—';
        $cond = $weather['condition'] ?? '—';

        return "{$farmName} · {$temp}, {$cond}. Rain context: {$rainIntensity}. Flood: {$floodLabel}.";
    }

    private function rainfallOverlayColor(string $intensityLabel): string
    {
        return match (strtolower($intensityLabel)) {
            'high' => 'rgba(37, 99, 235, 0.35)',
            'moderate' => 'rgba(59, 130, 246, 0.28)',
            'light' => 'rgba(96, 165, 250, 0.22)',
            default => 'rgba(148, 163, 184, 0.15)',
        };
    }

    private function rainfallRadiusM(string $intensityLabel): int
    {
        return match (strtolower($intensityLabel)) {
            'high' => 520,
            'moderate' => 420,
            'light' => 340,
            default => 280,
        };
    }

    private function floodOverlayColor(string $level): string
    {
        return match ($level) {
            'CRITICAL' => 'rgba(127, 29, 29, 0.3)',
            'HIGH' => 'rgba(220, 38, 38, 0.22)',
            'MODERATE' => 'rgba(245, 158, 11, 0.22)',
            default => 'rgba(34, 197, 94, 0.18)',
        };
    }

    private function floodRadiusM(string $level): int
    {
        return match ($level) {
            'CRITICAL' => 560,
            'HIGH' => 480,
            'MODERATE' => 400,
            default => 320,
        };
    }

    /**
     * @param  array<string, mixed>  $smart
     * @param  array<string, mixed>  $advisory
     * @return list<string>
     */
    private function buildTodayMeansLines(array $smart, array $advisory): array
    {
        $actions = $smart['action_list'] ?? [];
        $out = [];
        if (is_array($actions) && count($actions) > 0) {
            foreach (array_slice($actions, 0, 4) as $a) {
                $t = trim((string) $a);
                if ($t !== '') {
                    $out[] = $t;
                }
            }
        }
        if (count($out) >= 2) {
            return $out;
        }

        $short = trim((string) ($smart['short_recommendation'] ?? ($advisory['recommended_action'] ?? '')));
        if ($short !== '') {
            $out[] = $short;
        }
        $ws = trim((string) ($smart['weather_situation'] ?? ''));
        if ($ws !== '') {
            $out[] = $ws;
        }
        $fi = trim((string) ($smart['farm_impact'] ?? ''));
        if ($fi !== '') {
            $out[] = $fi;
        }

        if ($out === []) {
            return [
                'Safe to continue normal farm activities where conditions allow.',
                'Watch rainfall and flood layers if the forecast changes.',
            ];
        }

        return array_slice(array_values(array_unique($out)), 0, 5);
    }
}
