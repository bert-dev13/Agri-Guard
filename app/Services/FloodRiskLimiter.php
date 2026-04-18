<?php

namespace App\Services;

use App\Models\User;
use App\Support\CropImpactLevels;

/**
 * Flood tier is a hard limiter on crop impact (decision-support, conservative).
 *
 * Low flood uses {@see LowFloodEscalationGate}: without measurable extreme hazard,
 * impact stays at Low before downstream validation — final lock is {@see AdvisoryConsistencyValidator::validateAdvisoryFinal}.
 */
final class FloodRiskLimiter
{
    public function __construct(
        private readonly LowFloodEscalationGate $lowFloodGate,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function apply(
        User $user,
        string $preliminaryLevel,
        string $floodRiskNormalized,
        array $normalized,
        string $weatherSeverity
    ): string {
        $flood = strtolower(trim($floodRiskNormalized));
        if ($flood === 'unknown') {
            $flood = 'low';
        }

        $rank = $this->rankFromLevel($preliminaryLevel);

        return match ($flood) {
            'low' => $this->capForLowFlood($user, $rank, $normalized, $weatherSeverity),
            'moderate' => $this->capForModerateFlood($rank, $normalized, $weatherSeverity),
            'high', 'critical' => $this->levelFromRank(min(4, $rank)),
            default => $this->capForLowFlood($user, $rank, $normalized, $weatherSeverity),
        };
    }

    /**
     * Explicit extreme hazard signal while flood runoff risk stays low (e.g. wind/heat dominated).
     *
     * @param  array<string, mixed>  $normalized
     */
    public function extremeNonFloodThreat(array $normalized): bool
    {
        $rain = (float) ($normalized['three_day_total_rain_mm'] ?? 0);
        $wind = (float) ($normalized['max_wind_kmh'] ?? 0);
        $heat = $normalized['avg_temp_max_c'];
        $storm = ! empty($normalized['storm_present']);

        if ($storm && $rain >= 38.0) {
            return true;
        }
        if ($rain >= 82.0) {
            return true;
        }
        if ($wind >= 52.0) {
            return true;
        }
        if (is_numeric($heat) && (float) $heat >= 39.5) {
            return true;
        }
        if ($rain >= 52.0 && $wind >= 38.0) {
            return true;
        }

        return false;
    }

    /**
     * Repeated rainfall with measurable totals (secondary signal — gate still required for Low flood escalation).
     *
     * @param  array<string, mixed>  $normalized
     */
    public function clearRepeatedRainfallStress(array $normalized): bool
    {
        $rain = (float) ($normalized['three_day_total_rain_mm'] ?? 0);
        $wet = (int) ($normalized['wet_day_rain_pattern_count'] ?? 0);
        $pop = (float) ($normalized['max_pop_percent'] ?? 0);

        if ($rain >= 36.0 && $wet >= 2) {
            return true;
        }
        if ($rain >= 28.0 && $wet >= 3) {
            return true;
        }
        if ($rain >= 30.0 && $pop >= 72.0) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function capForLowFlood(User $user, int $rank, array $normalized, string $weatherSeverity): string
    {
        if (! $this->lowFloodGate->allowsEscalationAboveLow($user, $normalized, $weatherSeverity)) {
            return CropImpactLevels::LOW;
        }

        $extreme = $this->extremeNonFloodThreat($normalized);
        $wetStress = $this->clearRepeatedRainfallStress($normalized);

        if ($weatherSeverity === WeatherSeverityClassifier::SEVERE) {
            return $this->levelFromRank(min(4, $rank));
        }

        if ($extreme) {
            return $this->levelFromRank(min(3, $rank));
        }

        if ($weatherSeverity === WeatherSeverityClassifier::STRONG) {
            return $this->levelFromRank(min(3, $rank));
        }

        if ($weatherSeverity === WeatherSeverityClassifier::ELEVATED || $wetStress) {
            return $this->levelFromRank(min(2, $rank));
        }

        return $this->levelFromRank(min(1, $rank));
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function capForModerateFlood(int $rank, array $normalized, string $weatherSeverity): string
    {
        unset($normalized, $weatherSeverity);

        return $this->levelFromRank(min(3, $rank));
    }

    private function rankFromLevel(string $level): int
    {
        return match (strtolower(trim($level))) {
            CropImpactLevels::MINIMAL => 0,
            CropImpactLevels::LOW => 1,
            CropImpactLevels::MODERATE => 2,
            CropImpactLevels::HIGH => 3,
            CropImpactLevels::SEVERE => 4,
            default => 1,
        };
    }

    private function levelFromRank(int $rank): string
    {
        return match (max(0, min(4, $rank))) {
            0 => CropImpactLevels::MINIMAL,
            1 => CropImpactLevels::LOW,
            2 => CropImpactLevels::MODERATE,
            3 => CropImpactLevels::HIGH,
            default => CropImpactLevels::SEVERE,
        };
    }
}
