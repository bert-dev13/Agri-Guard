<?php

namespace App\Services;

use App\Models\User;

/**
 * Forecast impact payload for Blade/API — uses {@see CropImpactEngine}.
 */
class CropImpactService
{
    public function __construct(
        private readonly CropImpactEngine $cropImpactEngine,
        private readonly RiskLabelMapper $riskLabelMapper,
    ) {}

    /**
     * Panel / API shape for impact_advisory blocks.
     *
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array<string, mixed>
     */
    public function buildForecastImpactPayload(User $user, array $weather, array $forecast, string $floodRiskNormalized): array
    {
        $eval = $this->cropImpactEngine->evaluate($user, $weather, $forecast, $floodRiskNormalized);

        return [
            'effect_summary' => $eval['three_day_outlook'],
            'detailed_effects' => array_filter([$eval['three_day_outlook']]),
            'advice' => [],
            'level' => $eval['crop_impact_level'],
            'three_day_outlook' => $eval['three_day_outlook'],
            'recommended_action' => (string) ($eval['recommended_action'] ?? ''),
            'crop_impact_label' => $eval['crop_impact_label'],
            'possible_loss_range' => $eval['possible_loss_range'],
        ];
    }

    /**
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array<string, mixed>
     */
    public function assess(User $user, array $weather, array $forecast, string $floodRiskNormalized): array
    {
        $eval = $this->cropImpactEngine->evaluate($user, $weather, $forecast, $floodRiskNormalized);

        return [
            'level' => $eval['crop_impact_level'],
            'label' => $eval['crop_impact_label'],
            'possible_loss_range' => $eval['possible_loss_range'],
            'tone' => $this->riskLabelMapper->cropImpactTone($eval['crop_impact_level']),
            'stress_index' => (float) ($eval['signals']['stress_score'] ?? 0),
        ];
    }
}
