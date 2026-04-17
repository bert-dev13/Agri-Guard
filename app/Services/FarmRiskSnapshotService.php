<?php

namespace App\Services;

use App\Models\User;

class FarmRiskSnapshotService
{
    public function __construct(
        private readonly FloodRiskAssessmentService $floodRiskAssessment,
        private readonly CropLossEstimatorService $cropLossEstimator,
        private readonly ThreeDayImpactService $threeDayImpact
    ) {}

    /**
     * Canonical risk snapshot used by both Dashboard and Assistant.
     *
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array{
     *   estimated_crop_loss_value:int|null,
     *   estimated_crop_loss:string,
     *   three_day_effect:string,
     *   flood_risk_level:string,
     *   flood_risk_tone:string,
     *   flood_risk_label:string,
     *   flood_risk_message:string
     * }
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
        $impactPayload = $this->threeDayImpact->buildImpact($user, $forecast, $floodRiskLevel);
        $cropLoss = $this->cropLossEstimator->estimate($user, $weather, $forecast, $floodRiskLevel);
        $impactText = is_string($impactPayload['effect_summary'] ?? null)
            ? $impactPayload['effect_summary']
            : $this->threeDayImpact->predict($user, $forecast, $floodRiskLevel);

        return [
            'estimated_crop_loss_value' => $cropLoss,
            'estimated_crop_loss' => $cropLoss !== null ? $cropLoss.'%' : 'N/A',
            'three_day_effect' => $impactText ?? 'No forecast impact available',
            'flood_risk_level' => $floodRiskLevel !== '' ? ucfirst($floodRiskLevel) : 'Unknown',
            'flood_risk_tone' => $this->riskTone($floodRiskLevel),
            'flood_risk_label' => (string) ($risk['label'] ?? 'Low Risk'),
            'flood_risk_message' => (string) ($risk['message'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{
     *   estimated_crop_loss_value:int|null,
     *   estimated_crop_loss:string,
     *   three_day_effect:string,
     *   flood_risk_level:string,
     *   flood_risk_tone:string,
     *   flood_risk_label:string,
     *   flood_risk_message:string
     * }
     */
    public function buildFromNormalizedWeather(User $user, array $normalized): array
    {
        $weather = [
            'today_rain_probability' => $normalized['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $normalized['today_expected_rainfall'] ?? null,
            'wind_speed' => $normalized['wind_speed'] ?? null,
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

    private function riskTone(string $level): string
    {
        return match ($level) {
            'low' => 'low',
            'moderate' => 'moderate',
            'high' => 'high',
            'critical' => 'critical',
            default => 'unknown',
        };
    }

}
