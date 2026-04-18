<?php

namespace App\Services;

/**
 * Normalizes raw weather + 3-day forecast into measurable fields only.
 * No severity scoring here — classification happens downstream.
 */
final class ForecastInputNormalizer
{
    /**
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array<string, mixed>
     */
    public function normalize(array $weather, array $forecast): array
    {
        $nextThree = array_slice($forecast, 0, 3);

        $todayRain = $this->floatOrZero($weather['today_expected_rainfall'] ?? null);
        $todayPop = $this->floatOrNull($weather['today_rain_probability'] ?? null);
        $todayWind = $this->floatOrNull($weather['wind_speed'] ?? null);
        $todayHumidity = $this->floatOrNull($weather['humidity'] ?? null);

        $totalRain = $todayRain;
        $maxPop = $todayPop ?? 0.0;
        $maxWind = $todayWind ?? 0.0;
        $humiditySamples = [];
        if ($todayHumidity !== null) {
            $humiditySamples[] = $todayHumidity;
        }

        $heatMaxVals = [];
        $wetDayPopCount = 0;
        $wetDayRainCount = 0;
        $heavyRainDays = 0;

        foreach ($nextThree as $day) {
            $mm = $this->floatOrZero($day['rain_mm'] ?? null);
            $totalRain += $mm;

            $pop = isset($day['pop']) && is_numeric($day['pop']) ? (float) $day['pop'] : null;
            if ($pop !== null) {
                $maxPop = max($maxPop, $pop);
                if ($pop >= 55.0) {
                    $wetDayPopCount++;
                }
                if ($pop >= 45.0 && $mm >= 4.0) {
                    $wetDayRainCount++;
                }
            } elseif ($mm >= 6.0) {
                $wetDayRainCount++;
                $wetDayPopCount++;
            }

            if ($mm >= 22.0 || ($mm >= 14.0 && $pop !== null && $pop >= 68.0)) {
                $heavyRainDays++;
            }

            $ws = $this->floatOrNull($day['wind_speed'] ?? null);
            if ($ws !== null) {
                $maxWind = max($maxWind, $ws);
            }

            $hum = $this->floatOrNull($day['humidity'] ?? null);
            if ($hum !== null) {
                $humiditySamples[] = $hum;
            }

            $tm = $this->floatOrNull($day['temp_max'] ?? null);
            if ($tm !== null) {
                $heatMaxVals[] = $tm;
            }
        }

        $missing = 0;
        foreach ($nextThree as $day) {
            $hasRain = isset($day['rain_mm']) && is_numeric($day['rain_mm']);
            $hasPop = isset($day['pop']) && is_numeric($day['pop']);
            if (! $hasRain && ! $hasPop) {
                $missing++;
            }
        }
        $forecastSparse = $nextThree === [] || $missing >= 2;

        $avgHumidity = $humiditySamples === [] ? null : array_sum($humiditySamples) / count($humiditySamples);
        $avgHeatMax = $heatMaxVals === [] ? null : array_sum($heatMaxVals) / count($heatMaxVals);

        $storm = $this->stormPresent($weather, $nextThree);

        return [
            'three_day_total_rain_mm' => round($totalRain, 1),
            'today_rain_mm' => round($todayRain, 1),
            'max_pop_percent' => round($maxPop, 1),
            'max_wind_kmh' => round($maxWind, 1),
            'avg_temp_max_c' => $avgHeatMax !== null ? round($avgHeatMax, 1) : null,
            'avg_humidity_percent' => $avgHumidity !== null ? round($avgHumidity, 1) : null,
            'wet_day_pop_count' => $wetDayPopCount,
            'wet_day_rain_pattern_count' => $wetDayRainCount,
            'heavy_rain_day_count' => $heavyRainDays,
            'storm_present' => $storm,
            'forecast_sparse' => $forecastSparse,
            'forecast_days_used' => count($nextThree),
        ];
    }

    private function stormPresent(array $weather, array $nextThree): bool
    {
        $todayId = (int) ($weather['condition']['id'] ?? 800);
        if ($todayId >= 200 && $todayId < 300) {
            return true;
        }
        foreach ($nextThree as $day) {
            $id = (int) ($day['condition']['id'] ?? 800);
            if ($id >= 200 && $id < 300) {
                return true;
            }
        }

        return false;
    }

    private function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    private function floatOrZero(mixed $v): float
    {
        return $this->floatOrNull($v) ?? 0.0;
    }
}
