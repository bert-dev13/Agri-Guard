<?php

namespace App\Services;

/**
 * Builds a factual 3-day weather outlook for UI (no crop loss, yield, or damage estimates).
 *
 * @phpstan-type Outlook array{
 *   day_summary: string,
 *   rain_probability: string,
 *   temperature_trend: string,
 *   wind_condition: string,
 *   field_advisory_lines: list<string>,
 *   confidence: string,
 *   data_source: string,
 * }
 */
class ThreeDayWeatherOutlookService
{
    public const DATA_SOURCE = 'Weather forecast model + local interpolation';

    /**
     * @param  array<string, mixed>|null  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array<string, mixed>
     */
    public function build(?array $weather, array $forecast): array
    {
        $slice = array_slice($forecast, 0, 3);

        if ($slice === []) {
            return [
                'day_summary' => 'Forecast data is limited. Check back after the next weather update.',
                'rain_probability' => '—',
                'temperature_trend' => '—',
                'wind_condition' => '—',
                'field_advisory_lines' => [
                    'When a multi-day forecast is available, use it to plan field access and drainage checks.',
                    'Refer to official weather notices for any watches or warnings in your area.',
                ],
                'confidence' => 'Low',
                'data_source' => self::DATA_SOURCE,
            ];
        }

        $maxPop = $this->maxProbabilityOfPrecipitation($slice);
        $rainDisplay = $maxPop !== null ? ((int) round($maxPop)).'%' : '—';

        $tempTrend = $this->temperatureTrendLabel($slice);
        $avgWind = $this->averageWindKmh($slice, $weather);
        $windCondition = $this->windConditionLabel($avgWind);

        $daySummary = $this->daySummaryText($slice, $maxPop, $tempTrend);
        $fieldLines = $this->fieldAdvisoryLines($maxPop, $avgWind);

        $confidence = $this->confidenceLabel($slice, $maxPop, $avgWind);

        return [
            'day_summary' => $daySummary,
            'rain_probability' => $rainDisplay,
            'temperature_trend' => $tempTrend,
            'wind_condition' => $windCondition,
            'field_advisory_lines' => $fieldLines,
            'confidence' => $confidence,
            'data_source' => self::DATA_SOURCE,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slice
     */
    private function maxProbabilityOfPrecipitation(array $slice): ?float
    {
        $pops = array_filter(array_column($slice, 'pop'), static fn ($v) => is_numeric($v));
        if ($pops === []) {
            return null;
        }

        return (float) max(array_map(static fn ($v) => (float) $v, $pops));
    }

    /**
     * @param  array<int, array<string, mixed>>  $slice
     */
    private function temperatureTrendLabel(array $slice): string
    {
        $highs = [];
        foreach ($slice as $day) {
            if (isset($day['temp_max']) && is_numeric($day['temp_max'])) {
                $highs[] = (float) $day['temp_max'];
            }
        }
        if (count($highs) < 2) {
            return 'Stable';
        }

        $first = $highs[0];
        $last = $highs[count($highs) - 1];
        $delta = $last - $first;

        if (abs($delta) < 1.5) {
            return 'Stable';
        }

        return $delta > 0 ? 'Warming' : 'Cooling';
    }

    /**
     * @param  array<int, array<string, mixed>>  $slice
     * @param  array<string, mixed>|null  $weather
     */
    private function averageWindKmh(array $slice, ?array $weather): ?float
    {
        $speeds = [];
        foreach ($slice as $day) {
            if (isset($day['wind_speed']) && is_numeric($day['wind_speed'])) {
                $speeds[] = (float) $day['wind_speed'];
            }
        }
        if ($speeds !== []) {
            return array_sum($speeds) / count($speeds);
        }
        if (is_array($weather) && isset($weather['wind_speed']) && is_numeric($weather['wind_speed'])) {
            return (float) $weather['wind_speed'];
        }

        return null;
    }

    private function windConditionLabel(?float $avgKmh): string
    {
        if ($avgKmh === null) {
            return '—';
        }

        return match (true) {
            $avgKmh < 8.0 => 'Light',
            $avgKmh < 18.0 => 'Light to Moderate',
            $avgKmh < 30.0 => 'Moderate',
            default => 'Moderate to Strong',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $slice
     */
    private function hasThunderstormSignal(array $slice): bool
    {
        foreach ($slice as $day) {
            $id = (int) ($day['condition']['id'] ?? 800);
            if ($id >= 200 && $id < 300) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $slice
     */
    private function daySummaryText(array $slice, ?float $maxPop, string $tempTrend): string
    {
        if ($this->hasThunderstormSignal($slice)) {
            return 'Unsettled conditions are possible; monitor official weather updates for your area.';
        }

        $pop = $maxPop ?? 0.0;

        if ($pop >= 70.0) {
            return 'Wet pattern with elevated shower or thunderstorm chances over the outlook period.';
        }

        if ($pop >= 40.0) {
            return 'Variable skies with increased rain chances across the next few days.';
        }

        if ($pop < 40.0 && $tempTrend === 'Stable') {
            return 'Stable conditions with light atmospheric variation expected.';
        }

        return 'Conditions remain generally steady with modest day-to-day changes.';
    }

    /**
     * @return list<string>
     */
    private function fieldAdvisoryLines(?float $maxPop, ?float $avgWind): array
    {
        $pop = $maxPop ?? 0.0;

        if ($pop >= 50.0) {
            $line1 = 'Soil moisture may increase if rain occurs.';
        } elseif ($pop >= 25.0) {
            $line1 = 'Soil moisture may slightly increase if intermittent rain occurs.';
        } else {
            $line1 = 'Soil moisture may change slowly under prevailing conditions.';
        }

        if ($avgWind !== null && $avgWind >= 18.0) {
            $line2 = 'Breezier winds can affect spray timing and light materials; keep drainage paths clear.';
        } else {
            $line2 = 'Ensure drainage paths remain clear.';
        }

        return [$line1, $line2];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slice
     */
    private function confidenceLabel(array $slice, ?float $maxPop, ?float $avgWind): string
    {
        $dayCount = count($slice);
        if ($dayCount < 2) {
            return 'Low';
        }

        if ($maxPop === null && $avgWind === null) {
            return 'Low';
        }

        return 'Medium';
    }
}
