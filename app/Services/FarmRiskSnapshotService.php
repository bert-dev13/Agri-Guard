<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdvisoryDisclaimer;

class FarmRiskSnapshotService
{
    public function __construct(
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
        $rainChance = is_numeric($weather['today_rain_probability'] ?? null)
            ? (int) $weather['today_rain_probability']
            : null;
        if ($rainChance === null && $forecast !== []) {
            $pops = array_filter(array_column($forecast, 'pop'), static fn ($v) => is_numeric($v));
            $rainChance = $pops !== [] ? (int) max($pops) : null;
        }
        $rainfallSeverity = $this->rainfallSeverity($rainChance);

        $eval = $this->cropImpactEngine->evaluate($user, $weather, $forecast, $rainfallSeverity);
        $level = (string) $eval['crop_impact_level'];

        return [
            'crop_impact_level' => $level,
            'crop_impact_label' => (string) $eval['crop_impact_label'],
            'possible_loss_range' => (string) $eval['possible_loss_range'],
            'crop_impact_tone' => $this->riskLabelMapper->cropImpactTone($level),

            'three_day_outlook' => (string) $eval['three_day_outlook'],
            'recommended_action' => (string) ($eval['recommended_action'] ?? ''),
            'rain_chance_display' => $rainChance !== null ? "{$rainChance}%" : '—',
            'rainfall_severity' => ucfirst($rainfallSeverity),

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

    private function rainfallSeverity(?int $rainProbability): string
    {
        if ($rainProbability === null) {
            return 'low';
        }

        return match (true) {
            $rainProbability >= 70 => 'high',
            $rainProbability >= 40 => 'moderate',
            default => 'low',
        };
    }
}
