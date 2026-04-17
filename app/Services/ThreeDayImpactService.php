<?php

namespace App\Services;

use App\Models\User;

class ThreeDayImpactService
{
    /**
     * @param  array<int, array<string, mixed>>  $forecast
     */
    public function predict(User $user, array $forecast, string $floodRiskLevel): ?string
    {
        return $this->buildImpact($user, $forecast, $floodRiskLevel)['effect_summary'];
    }

    /**
     * Shared impact payload used by both Dashboard summary and Weather page details.
     *
     * @param  array<int, array<string, mixed>>  $forecast
     * @return array{
     *     effect_summary: string,
     *     detailed_effects: array<int, string>,
     *     advice: array<int, string>,
     *     level: string
     * }
     */
    public function buildImpact(User $user, array $forecast, string $floodRiskLevel): array
    {
        $nextThreeDays = array_slice($forecast, 0, 3);
        if ($nextThreeDays === []) {
            return [
                'effect_summary' => 'Unable to determine impact',
                'detailed_effects' => ['No forecast data available'],
                'advice' => ['Monitor weather updates for the next few days'],
                'level' => 'unknown',
            ];
        }

        $maxPop = $this->maxNumeric($nextThreeDays, 'pop');
        $totalRainMm = $this->sumNumeric($nextThreeDays, 'rain_mm');
        $maxWind = $this->maxNumeric($nextThreeDays, 'wind_speed');
        $avgTemp = $this->avgNumeric($nextThreeDays, 'temp_max');
        $stage = strtolower(trim((string) ($user->farming_stage ?? '')));
        $floodLevel = strtolower(trim($floodRiskLevel));

        $effectSummary = 'Minor weather impact expected';
        $detailedEffects = [];
        $advice = [];
        $level = in_array($floodLevel, ['low', 'moderate', 'high', 'critical'], true) ? $floodLevel : 'low';

        if (($totalRainMm !== null && $totalRainMm >= 90) || (($maxPop ?? 0) >= 90 && ($totalRainMm ?? 0) >= 60)) {
            $level = 'critical';
        }

        if ($level === 'critical') {
            $effectSummary = 'Possible severe flooding';
            $detailedEffects[] = 'Continuous heavy rain may cause flooding in low areas';
            $detailedEffects[] = 'Possible water accumulation around crop roots';
            $advice[] = 'Prepare drainage now';
            $advice[] = 'Move farm materials to higher ground';
            $advice[] = 'Harvest mature crops early if safe';
        } elseif (($totalRainMm !== null && $totalRainMm >= 45) || $floodLevel === 'high') {
            $effectSummary = 'Possible waterlogging';
            $detailedEffects[] = 'Continuous rain for 3 days may soften soil';
            $detailedEffects[] = 'Possible water accumulation in low areas';
            $advice[] = 'Clear and open drainage channels';
            $advice[] = 'Delay fertilizer application';
            $advice[] = 'Monitor water level around the field';
            $level = $level === 'critical' ? 'critical' : 'high';
        } elseif ($totalRainMm !== null && $totalRainMm >= 22) {
            $effectSummary = 'Soil may become too wet';
            $detailedEffects[] = 'Rainfall may increase soil moisture over the next 3 days';
            $detailedEffects[] = 'Field access may become difficult in soft areas';
            $advice[] = 'Reduce irrigation for now';
            $advice[] = 'Check field drainage and runoff paths';
            $advice[] = 'Inspect crops for early stress signs';
            $level = in_array($level, ['high', 'critical'], true) ? $level : 'moderate';
        } elseif ($maxPop !== null && $maxPop >= 70) {
            $effectSummary = 'Low flood possibility in low-lying areas';
            $detailedEffects[] = 'Rain chance is high for at least one of the next 3 days';
            $detailedEffects[] = 'Moisture buildup is possible in low sections of the farm';
            $advice[] = 'Prepare backup drainage paths';
            $advice[] = 'Secure farm tools and loose materials';
            $advice[] = 'Review weather updates twice daily';
            $level = in_array($level, ['high', 'critical'], true) ? $level : 'moderate';
        }

        if ($maxWind !== null && $maxWind >= 30) {
            $detailedEffects[] = 'Strong winds may damage young crops';
            $advice[] = 'Secure crops and support weak stems';
            $advice[] = 'Postpone spraying during strong winds';
            if ($effectSummary === 'Minor weather impact expected') {
                $effectSummary = 'Increased crop stress likely';
            }
            if (! in_array($level, ['high', 'critical'], true)) {
                $level = 'moderate';
            }
        }

        if ($avgTemp !== null && $avgTemp >= 34) {
            $detailedEffects[] = 'Hot daytime conditions may increase crop stress';
            $advice[] = 'Water crops in early morning or late afternoon';
        }

        if (in_array($stage, ['early_growth', 'vegetative'], true) && $maxWind !== null && $maxWind >= 25) {
            $detailedEffects[] = 'Young crops are more sensitive to wind damage';
        }

        if ($detailedEffects === []) {
            $detailedEffects[] = 'Weather is mostly stable over the next 3 days';
            $advice[] = 'Continue normal farm activities';
            $advice[] = 'Monitor weather updates regularly';
        }

        return [
            'effect_summary' => $effectSummary,
            'detailed_effects' => array_values(array_unique($detailedEffects)),
            'advice' => array_values(array_unique($advice)),
            'level' => $level,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function maxNumeric(array $rows, string $key): ?float
    {
        $values = [];
        foreach ($rows as $row) {
            if (is_numeric($row[$key] ?? null)) {
                $values[] = (float) $row[$key];
            }
        }

        return $values === [] ? null : (float) max($values);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function sumNumeric(array $rows, string $key): ?float
    {
        $sum = 0.0;
        $hasValue = false;
        foreach ($rows as $row) {
            if (is_numeric($row[$key] ?? null)) {
                $sum += (float) $row[$key];
                $hasValue = true;
            }
        }

        return $hasValue ? $sum : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function avgNumeric(array $rows, string $key): ?float
    {
        $values = [];
        foreach ($rows as $row) {
            if (is_numeric($row[$key] ?? null)) {
                $values[] = (float) $row[$key];
            }
        }

        if ($values === []) {
            return null;
        }

        return array_sum($values) / count($values);
    }
}
