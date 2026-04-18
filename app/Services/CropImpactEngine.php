<?php

namespace App\Services;

use App\Models\User;
use App\Support\CropImpactLevels;

/**
 * Canonical AGRIGUARD 3-day crop impact decision (rule-based, deterministic).
 *
 * Impact is chosen first; outlook is produced only after {@see AdvisoryConsistencyValidator::validateAdvisoryFinal}.
 */
final class CropImpactEngine
{
    public function __construct(
        private readonly ForecastInputNormalizer $inputs,
        private readonly WeatherSeverityClassifier $weatherTier,
        private readonly ActionSeverityResolver $actions,
        private readonly FloodRiskLimiter $floodLimiter,
        private readonly LowFloodEscalationGate $lowFloodGate,
        private readonly AdvisoryConsistencyValidator $validator,
        private readonly OutlookTextGenerator $outlook,
    ) {}

    /**
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array<string, mixed>
     */
    public function evaluate(User $user, array $weather, array $forecast, string $floodRiskNormalized): array
    {
        $normalized = $this->inputs->normalize($weather, $forecast);
        $weatherSeverity = $this->weatherTier->classify($normalized);
        $actionSeverity = $this->actions->resolve($weatherSeverity, $user, $normalized);

        $preliminary = $this->preliminaryImpactLevel($normalized, $weatherSeverity, $actionSeverity);
        $afterFlood = $this->floodLimiter->apply($user, $preliminary, $floodRiskNormalized, $normalized, $weatherSeverity);

        $finalLevel = $this->validator->finalize(
            $afterFlood,
            $floodRiskNormalized,
            $normalized,
            $weatherSeverity,
            $actionSeverity,
            $this->floodLimiter
        );

        $locked = $this->validator->validateAdvisoryFinal(
            $user,
            $finalLevel,
            $floodRiskNormalized,
            $normalized,
            $weatherSeverity,
            $this->lowFloodGate,
            $this->outlook,
        );

        $finalImpact = (string) $locked['crop_impact_level'];
        $sentence = (string) $locked['three_day_outlook'];

        $stress = $this->syntheticStressIndex($normalized, $weatherSeverity);

        return [
            'crop_impact_level' => $finalImpact,
            'crop_impact_label' => (string) $locked['crop_impact_label'],
            'possible_loss_range' => (string) $locked['possible_loss_range'],
            'three_day_outlook' => $sentence,
            'recommended_action' => $this->recommendedAction($finalImpact),
            'signals' => [
                'stress_score' => $stress,
                'normalized' => $normalized,
                'weather_severity' => $weatherSeverity,
                'action_severity' => $actionSeverity,
                'preliminary_impact_level' => $preliminary,
                'after_flood_impact_level' => $afterFlood,
                'after_finalize_impact_level' => $finalLevel,
                'hard_capped_low_flood' => (bool) ($locked['hard_capped_low_flood'] ?? false),
                'low_flood_escalation_allowed' => $this->lowFloodGate->allowsEscalationAboveLow($user, $normalized, $weatherSeverity),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function preliminaryImpactLevel(array $normalized, string $weatherSeverity, string $actionSeverity): string
    {
        $ceil = $this->actionCeilingRank($actionSeverity);
        $base = $this->baseRankFromWeather($weatherSeverity, $normalized);
        $rank = min($base, $ceil);

        return $this->rankToLevel($rank);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function baseRankFromWeather(string $weatherSeverity, array $normalized): int
    {
        $r = (float) ($normalized['three_day_total_rain_mm'] ?? 0);
        $p = (float) ($normalized['max_pop_percent'] ?? 0);
        $w = (float) ($normalized['max_wind_kmh'] ?? 0);

        if ($weatherSeverity === WeatherSeverityClassifier::MILD && $r < 6.0 && $p < 42.0 && $w < 26.0) {
            return 0;
        }

        return match ($weatherSeverity) {
            WeatherSeverityClassifier::MILD => 1,
            WeatherSeverityClassifier::ELEVATED => 2,
            WeatherSeverityClassifier::STRONG => 3,
            WeatherSeverityClassifier::SEVERE => 4,
            default => 1,
        };
    }

    private function actionCeilingRank(string $actionSeverity): int
    {
        return match (strtolower(trim($actionSeverity))) {
            ActionSeverityResolver::MILD => 1,
            ActionSeverityResolver::MEDIUM => 2,
            ActionSeverityResolver::STRONG => 3,
            ActionSeverityResolver::EMERGENCY => 4,
            default => 2,
        };
    }

    private function rankToLevel(int $rank): string
    {
        return match (max(0, min(4, $rank))) {
            0 => CropImpactLevels::MINIMAL,
            1 => CropImpactLevels::LOW,
            2 => CropImpactLevels::MODERATE,
            3 => CropImpactLevels::HIGH,
            default => CropImpactLevels::SEVERE,
        };
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function syntheticStressIndex(array $normalized, string $weatherSeverity): float
    {
        $rain = (float) ($normalized['three_day_total_rain_mm'] ?? 0);
        $w = (float) ($normalized['max_wind_kmh'] ?? 0);
        $p = (float) ($normalized['max_pop_percent'] ?? 0);

        $ws = match ($weatherSeverity) {
            WeatherSeverityClassifier::MILD => 12.0,
            WeatherSeverityClassifier::ELEVATED => 32.0,
            WeatherSeverityClassifier::STRONG => 56.0,
            WeatherSeverityClassifier::SEVERE => 78.0,
            default => 20.0,
        };

        $bonus = min(22.0, $rain * 0.25) + min(12.0, $w * 0.35) + min(10.0, max(0.0, $p - 35.0) * 0.12);

        return round(min(100.0, $ws + $bonus), 1);
    }

    private function recommendedAction(string $level): string
    {
        return match ($level) {
            CropImpactLevels::MINIMAL => 'Continue routine monitoring while conditions stay manageable.',
            CropImpactLevels::LOW => 'Watch short windows for spraying or tillage; keep drainage paths clear after light rain.',
            CropImpactLevels::MODERATE => 'Scout low spots after rain and improve drainage before the next wet stretch.',
            CropImpactLevels::HIGH => 'Defer non-essential field work until conditions ease; protect vulnerable crop sections.',
            CropImpactLevels::SEVERE => 'Prioritize safety and shelter inputs until severe weather passes.',
            default => 'Monitor conditions and adjust field work if weather worsens.',
        };
    }
}
