<?php

namespace App\Support;

/**
 * Rain likelihood display: API precipitation probability when present,
 * otherwise a bucketed estimate from predicted / observed rainfall (mm).
 */
final class RainChance
{
    /**
     * Maps predicted rainfall (mm) to a rain-chance % for display.
     * Above-bucket thresholds match the original stepped table; within the
     * trace band (0–0.10 mm) we use sqrt scaling so tiny ML outputs (e.g.
     * 0.009 vs 0.03 mm) are not all identical at 5%.
     */
    public static function calculateRainChance(float $rainfall): int
    {
        if ($rainfall <= 0.0) {
            return 5;
        }
        if ($rainfall <= 0.10) {
            $scaled = 5 + sqrt($rainfall / 0.10) * 9;

            return max(5, min(14, (int) round($scaled)));
        }
        if ($rainfall <= 0.50) {
            return 15;
        }
        if ($rainfall <= 2.00) {
            return 40;
        }
        if ($rainfall <= 10.00) {
            return 70;
        }

        return 90;
    }

    /**
     * @param  int|null  $apiProbabilityPercent  0–100 from API (pop / chance_of_rain / etc.), or null if absent
     */
    public static function resolve(?int $apiProbabilityPercent, ?float $predictedRainfallMm): int
    {
        if ($apiProbabilityPercent !== null) {
            return max(0, min(100, $apiProbabilityPercent));
        }

        $mm = $predictedRainfallMm ?? 0.0;

        return self::calculateRainChance(max(0.0, $mm));
    }
}
