<?php

namespace App\Services;

/**
 * Deterministic 3-day weather severity: Mild | Elevated | Strong | Severe.
 * Uses only normalized numeric inputs — no keyword escalation.
 */
final class WeatherSeverityClassifier
{
    public const MILD = 'mild';

    public const ELEVATED = 'elevated';

    public const STRONG = 'strong';

    public const SEVERE = 'severe';

    /**
     * @param  array<string, mixed>  $normalized  From {@see ForecastInputNormalizer::normalize()}
     */
    public function classify(array $normalized): string
    {
        if (! empty($normalized['forecast_sparse'])) {
            return self::MILD;
        }

        $r = (float) ($normalized['three_day_total_rain_mm'] ?? 0);
        $p = (float) ($normalized['max_pop_percent'] ?? 0);
        $w = (float) ($normalized['max_wind_kmh'] ?? 0);
        $heat = $normalized['avg_temp_max_c'];
        $storm = ! empty($normalized['storm_present']);
        $wetPop = (int) ($normalized['wet_day_pop_count'] ?? 0);
        $wetPat = (int) ($normalized['wet_day_rain_pattern_count'] ?? 0);
        $heavyDays = (int) ($normalized['heavy_rain_day_count'] ?? 0);

        // Severe: hazardous synoptic-scale signals (conservative thresholds).
        if ($storm && $r >= 38.0) {
            return self::SEVERE;
        }
        if ($r >= 88.0 || $w >= 56.0) {
            return self::SEVERE;
        }
        if (is_numeric($heat) && (float) $heat >= 39.5) {
            return self::SEVERE;
        }
        if ($r >= 58.0 && $w >= 40.0) {
            return self::SEVERE;
        }

        // Strong: clearly stressful but sub-disaster.
        if ($r >= 48.0 || $p >= 82.0 || $w >= 44.0) {
            return self::STRONG;
        }
        if (is_numeric($heat) && (float) $heat >= 36.5) {
            return self::STRONG;
        }
        if ($heavyDays >= 2 && $r >= 36.0) {
            return self::STRONG;
        }
        if ($r >= 38.0 && ($wetPat >= 2 || $wetPop >= 3)) {
            return self::STRONG;
        }

        // Elevated: repeated chances / moderate totals — still manageable with planning.
        if ($r >= 22.0 || $p >= 62.0 || $w >= 30.0) {
            return self::ELEVATED;
        }
        if (is_numeric($heat) && (float) $heat >= 34.5) {
            return self::ELEVATED;
        }
        if ($wetPat >= 2 && $r >= 14.0) {
            return self::ELEVATED;
        }
        if ($wetPop >= 3 && $r >= 12.0) {
            return self::ELEVATED;
        }

        return self::MILD;
    }
}
