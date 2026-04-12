<?php

namespace App\Services;

use App\Models\User;
use App\Services\AiAdvisory\AiAdvisoryService;

/**
 * Farm Map page — AI advisory via centralized Together AI service only (no rule-based copy).
 */
class MapSmartAdvisoryService
{
    public function __construct(
        private readonly AiAdvisoryService $aiAdvisory,
        private readonly CropTimelineService $cropTimeline,
    ) {}

    /**
     * @param  array<string, mixed>  $weather  Normalized weather block from FarmWeatherService
     * @param  array<string, mixed>  $floodResult  From FloodRiskAssessmentService::assess
     * @param  array<string, mixed>  $rainfallContext
     * @param  array<string, mixed>  $advisory  RuleBasedAdvisoryService output (signals for AI context only)
     * @param  array<string, mixed>  $smartAdvisory  Reserved for future context expansion
     * @param  array<string, mixed>  $mapUiState  e.g. selected_layer: farm|weather|rainfall|flood
     * @return array<string, mixed>
     */
    public function build(
        User $user,
        array $weather,
        array $floodResult,
        array $rainfallContext,
        array $advisory,
        array $smartAdvisory,
        ?float $forecastMaxDailyMm,
        array $mapUiState = []
    ): array {
        $generatedAt = now()->toIso8601String();
        $payload = $this->compactContextForAi(
            $user,
            $weather,
            $floodResult,
            $rainfallContext,
            $forecastMaxDailyMm,
            $advisory,
            $mapUiState
        );

        $result = $this->aiAdvisory->run(AiAdvisoryService::PAGE_MAP, $user, $payload);

        return $this->aiAdvisory->formatMapAdvisory($result, $generatedAt);
    }

    /**
     * @param  array<string, mixed>  $weather
     * @param  array<string, mixed>  $floodResult
     * @param  array<string, mixed>  $rainfallContext
     * @param  array<string, mixed>  $advisory
     * @param  array<string, mixed>  $mapUiState
     * @return array<string, mixed>
     */
    private function compactContextForAi(
        User $user,
        array $weather,
        array $floodResult,
        array $rainfallContext,
        ?float $forecastMaxDailyMm,
        array $advisory,
        array $mapUiState
    ): array {
        $stageKey = $this->cropTimeline->normalizeStageKey((string) ($user->farming_stage ?? ''));
        $stageLabel = CropTimelineService::STAGE_LABELS[$stageKey] ?? 'Not set';
        $layer = $this->normalizeMapLayer((string) ($mapUiState['selected_layer'] ?? 'farm'));
        $lat = $user->farm_lat !== null ? (float) $user->farm_lat : 0.0;
        $lng = $user->farm_lng !== null ? (float) $user->farm_lng : 0.0;

        return [
            'task' => 'map_smart_advisory',
            'location' => [
                'gps_decimal' => sprintf('%.5f,%.5f', $lat, $lng),
                'line' => (string) ($user->farm_location_display ?? ''),
                'barangay' => (string) ($user->farm_barangay_name ?? ''),
                'municipality' => trim((string) ($user->farm_municipality ?? '')) !== ''
                    ? trim((string) $user->farm_municipality)
                    : 'Amulung',
                'province' => 'Cagayan',
            ],
            'map' => [
                'selected_layer' => $layer,
                'layers_available' => ['farm', 'weather', 'rainfall', 'flood'],
            ],
            'weather_at_pin' => [
                'condition' => $weather['condition'] ?? null,
                'temp_c' => $weather['current_temperature'] ?? null,
                'wind_kmh' => $weather['wind_speed'] ?? null,
                'today_rain_probability_pct' => $weather['today_rain_probability'] ?? null,
                'today_expected_rainfall_mm' => $weather['today_expected_rainfall'] ?? null,
            ],
            'rainfall_overlay' => [
                'intensity_label' => $rainfallContext['intensity_label'] ?? null,
                'accumulation_label' => $rainfallContext['accumulation_label'] ?? null,
                'forecast_peak_daily_mm' => $forecastMaxDailyMm,
            ],
            'flood_overlay' => [
                'level' => $floodResult['level'] ?? 'LOW',
                'label' => $floodResult['label'] ?? '',
                'message' => $floodResult['message'] ?? '',
            ],
            'advisory_engine_signals' => [
                'rule_based_risk_level' => $advisory['risk_level'] ?? null,
                'recommended_action_code' => $advisory['recommended_action'] ?? null,
            ],
            'optional_farm_context' => [
                'crop_type' => $user->crop_type,
                'growth_stage_key' => $stageKey,
                'growth_stage_label' => $stageLabel,
                'field_condition' => $user->field_condition ?? null,
            ],
        ];
    }

    private function normalizeMapLayer(string $raw): string
    {
        $r = strtolower(trim($raw));

        return in_array($r, ['farm', 'weather', 'rainfall', 'flood'], true) ? $r : 'farm';
    }
}
