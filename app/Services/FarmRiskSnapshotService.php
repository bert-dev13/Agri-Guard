<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdvisoryDisclaimer;

class FarmRiskSnapshotService
{
    public function __construct(
        private readonly FloodRiskAssessmentService $floodRiskAssessment,
        private readonly CropImpactEngine $cropImpactEngine,
        private readonly RiskLabelMapper $riskLabelMapper,
    ) {}

    /**
     * Canonical risk snapshot + 3-day outlook card data for Dashboard, Weather, Map JSON, Assistant.
     *
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array<string, mixed>
     */
    public function buildFromWeather(User $user, array $weather, array $forecast): array
    {
        $risk = $this->floodRiskAssessment->assess([
            'today_rain_probability' => $weather['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $weather['today_expected_rainfall'] ?? null,
            'condition_id' => $weather['condition']['id'] ?? null,
            'condition' => $weather['condition']['main'] ?? null,
        ], [
            'field_condition' => $user->field_condition,
        ]);

        $floodRiskLevel = $this->normalizeFloodLevel((string) ($risk['level'] ?? ''));
        $floodForEval = $floodRiskLevel === 'unknown' ? 'low' : $floodRiskLevel;

        $eval = $this->cropImpactEngine->evaluate($user, $weather, $forecast, $floodForEval);
        $level = (string) $eval['crop_impact_level'];

        $floodDisplay = $this->floodDisplayFromLevel($floodRiskLevel);

        return [
            'crop_impact_level' => $level,
            'crop_impact_label' => (string) $eval['crop_impact_label'],
            'possible_loss_range' => (string) $eval['possible_loss_range'],
            'crop_impact_tone' => $this->riskLabelMapper->cropImpactTone($level),

            'three_day_outlook' => (string) $eval['three_day_outlook'],
            'recommended_action' => (string) ($eval['recommended_action'] ?? ''),

            'flood_risk_normalized' => $floodForEval,
            'flood_risk_display' => $floodDisplay,
            'flood_risk_level' => $floodDisplay,
            'flood_risk_tone' => $this->riskToneFromFloodDisplay($floodDisplay),
            'flood_risk_label' => (string) ($risk['label'] ?? 'Low Risk'),
            'flood_risk_message' => (string) ($risk['message'] ?? ''),

            'advisory_disclaimer' => AdvisoryDisclaimer::TEXT,

            'estimated_crop_loss' => (string) $eval['possible_loss_range'],
            'three_day_effect' => (string) $eval['three_day_outlook'],
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function buildFromNormalizedWeather(User $user, array $normalized): array
    {
        $weather = [
            'today_rain_probability' => $normalized['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $normalized['today_expected_rainfall'] ?? null,
            'wind_speed' => $normalized['wind_speed'] ?? null,
            'humidity' => $normalized['humidity'] ?? null,
            'temp' => $normalized['current_temperature'] ?? null,
            'condition' => [
                'id' => $normalized['condition_id'] ?? null,
                'main' => $normalized['condition'] ?? null,
            ],
        ];
        $forecast = is_array($normalized['daily_forecast'] ?? null) ? $normalized['daily_forecast'] : [];

        return $this->buildFromWeather($user, $weather, $forecast);
    }

    private function normalizeFloodLevel(string $raw): string
    {
        $value = strtolower(trim($raw));

        return match ($value) {
            'low' => 'low',
            'moderate' => 'moderate',
            'high' => 'high',
            'critical' => 'critical',
            default => 'unknown',
        };
    }

    private function floodDisplayFromLevel(string $level): string
    {
        return match ($level) {
            'high', 'critical' => 'High',
            'moderate' => 'Moderate',
            'low' => 'Low',
            default => 'Unknown',
        };
    }

    private function riskToneFromFloodDisplay(string $display): string
    {
        return match ($display) {
            'High' => 'high',
            'Moderate' => 'moderate',
            'Low' => 'low',
            default => 'unknown',
        };
    }
}
