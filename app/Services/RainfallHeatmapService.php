<?php

namespace App\Services;

/**
 * Lightweight rainfall visualization hints around a coordinate (forecast-based, not GIS polygons).
 */
class RainfallHeatmapService
{
    /**
     * Sample points around the farm for optional heat-style visualization.
     *
     * @param  array<string, mixed>  $weather  Normalized weather from FarmWeatherService
     * @return list<array{lat: float, lng: float, weight: float}>
     */
    public function buildPoints(float $lat, float $lng, array $weather): array
    {
        $step = 0.0025;
        $centerWeight = 1.0;
        $ringWeight = 0.45;

        return [
            ['lat' => $lat, 'lng' => $lng, 'weight' => $centerWeight],
            ['lat' => $lat + $step, 'lng' => $lng, 'weight' => $ringWeight],
            ['lat' => $lat - $step, 'lng' => $lng, 'weight' => $ringWeight],
            ['lat' => $lat, 'lng' => $lng + $step, 'weight' => $ringWeight],
            ['lat' => $lat, 'lng' => $lng - $step, 'weight' => $ringWeight],
        ];
    }

    /**
     * @param  array<string, mixed>  $weather
     */
    public function intensityLabel(array $weather): string
    {
        $pop = isset($weather['today_rain_probability']) ? (int) $weather['today_rain_probability'] : null;
        $rain = isset($weather['today_expected_rainfall']) ? (float) $weather['today_expected_rainfall'] : null;

        if ($pop !== null && $pop >= 70) {
            return 'High';
        }
        if ($pop !== null && $pop >= 40) {
            return 'Moderate';
        }
        if ($rain !== null && $rain > 15.0) {
            return 'Moderate';
        }
        if ($rain !== null && $rain > 0) {
            return 'Light';
        }

        return 'Low';
    }

    /**
     * @param  array<string, mixed>  $weather
     */
    public function intensityExplanation(array $weather): string
    {
        return 'Rainfall intensity is estimated from forecast data at your saved GPS coordinates, not from live radar.';
    }
}
