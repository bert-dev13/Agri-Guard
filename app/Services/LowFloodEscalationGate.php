<?php

namespace App\Services;

use App\Models\User;

/**
 * When flood risk is Low, crop impact may exceed "Low" ONLY if clearly defined
 * measurable hazards exist. PoP streaks, "strong" labels, or vague patterns are NOT enough.
 */
final class LowFloodEscalationGate
{
    /**
     * True ONLY if at least one explicit hazard is present — never true on probability alone.
     *
     * @param  array<string, mixed>  $normalized  {@see ForecastInputNormalizer::normalize()}
     */
    public function allowsEscalationAboveLow(User $user, array $normalized, string $weatherSeverity): bool
    {
        $rain = (float) ($normalized['three_day_total_rain_mm'] ?? 0);
        $wind = (float) ($normalized['max_wind_kmh'] ?? 0);
        $heat = $normalized['avg_temp_max_c'];
        $hum = $normalized['avg_humidity_percent'];
        $storm = ! empty($normalized['storm_present']);
        $heavyDays = (int) ($normalized['heavy_rain_day_count'] ?? 0);
        $wetPat = (int) ($normalized['wet_day_rain_pattern_count'] ?? 0);

        // Storm / thunderstorm with material rainfall (not empty sky noise).
        if ($storm && $rain >= 28.0) {
            return true;
        }

        // Heavy accumulation — not "rain expected".
        if ($rain >= 52.0) {
            return true;
        }

        // Multi-day intense rain: at least two heavy days and meaningful total volume.
        if ($heavyDays >= 2 && $rain >= 44.0) {
            return true;
        }

        // Damaging wind (km/h scale as used elsewhere in AgriGuard).
        if ($wind >= 46.0) {
            return true;
        }

        // Extreme heat stress for the 3-day window.
        if (is_numeric($heat) && (float) $heat >= 38.5) {
            return true;
        }

        // Compound hazard: wind + substantial rain together.
        if ($rain >= 40.0 && $wind >= 36.0) {
            return true;
        }

        // Overlapping moisture + heat + rain (field stress stack), all measurable.
        if (is_numeric($hum) && is_numeric($heat)
            && (float) $hum >= 82.0
            && (float) $heat >= 33.5
            && $rain >= 28.0) {
            return true;
        }

        // Prolonged strong rain (volume + pattern), not light repeated drizzle.
        if ($rain >= 46.0 && $wetPat >= 2 && $heavyDays >= 1) {
            return true;
        }

        // Synoptic Severe tier already requires numeric thresholds in {@see WeatherSeverityClassifier}.
        if ($weatherSeverity === WeatherSeverityClassifier::SEVERE) {
            return true;
        }

        // Strong tier only counts if backed by heavy rain OR damaging wind (not PoP alone).
        if ($weatherSeverity === WeatherSeverityClassifier::STRONG
            && ($rain >= 48.0 || $wind >= 44.0)) {
            return true;
        }

        // Highly sensitive crop stage + clearly elevated totals (not probability-only).
        if ($this->highlySensitiveStage($user)
            && in_array($weatherSeverity, [
                WeatherSeverityClassifier::ELEVATED,
                WeatherSeverityClassifier::STRONG,
                WeatherSeverityClassifier::SEVERE,
            ], true)
            && $rain >= 40.0) {
            return true;
        }

        // Severe field vulnerability (physical exposure) + heavy rain totals.
        if ($this->explicitSevereFieldVulnerability($user) && $rain >= 45.0) {
            return true;
        }

        return false;
    }

    private function explicitSevereFieldVulnerability(User $user): bool
    {
        $f = strtolower(trim((string) ($user->field_condition ?? '')));
        if ($f === '') {
            return false;
        }

        foreach (['low_lying', 'flood_prone', 'flood', 'waterlog', 'waterlogged'] as $marker) {
            if (str_contains($f, $marker)) {
                return true;
            }
        }

        return in_array($f, ['low_lying', 'flood_prone'], true);
    }

    private function highlySensitiveStage(User $user): bool
    {
        $stage = strtolower(trim((string) ($user->farming_stage ?? '')));

        return in_array($stage, ['flowering', 'flowering_fruiting', 'harvest', 'harvesting'], true);
    }
}
