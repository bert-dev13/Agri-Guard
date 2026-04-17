<?php

namespace App\Services;

use App\Models\User;

class CropLossEstimatorService
{
    /**
     * Estimate crop loss percentage using weather + crop context.
     *
     * @param  array<string, mixed>  $weather
     * @param  array<int, array<string, mixed>>  $forecast
     */
    public function estimate(User $user, array $weather, array $forecast, string $floodRiskLevel): ?int
    {
        $rainChance = is_numeric($weather['today_rain_probability'] ?? null) ? (float) $weather['today_rain_probability'] : null;
        $windSpeed = is_numeric($weather['wind_speed'] ?? null) ? (float) $weather['wind_speed'] : null;

        $nextThreeDays = array_slice($forecast, 0, 3);
        $maxRainChance = $this->maxNumeric($nextThreeDays, 'pop');
        $totalRainMm = $this->sumNumeric($nextThreeDays, 'rain_mm');

        // If all key weather signals are missing, surface fallback value.
        if ($rainChance === null && $windSpeed === null && $maxRainChance === null && $totalRainMm === null) {
            return null;
        }

        $score = 0.0;
        $score += $this->rainfallScore($rainChance, $maxRainChance, $totalRainMm);
        $score += $this->windScore($windSpeed);
        $score += $this->floodRiskScore($floodRiskLevel);
        $score += $this->cropStageScore((string) ($user->farming_stage ?? ''));
        $score += $this->cropTypeScore((string) ($user->crop_type ?? ''));

        $score = max(0.0, min(100.0, $score));

        return (int) round($score);
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

    private function rainfallScore(?float $todayRainChance, ?float $maxThreeDayRainChance, ?float $threeDayRainMm): float
    {
        $score = 0.0;
        $effectiveChance = $maxThreeDayRainChance ?? $todayRainChance;

        if ($effectiveChance !== null) {
            if ($effectiveChance >= 90) {
                $score += 22;
            } elseif ($effectiveChance >= 75) {
                $score += 16;
            } elseif ($effectiveChance >= 55) {
                $score += 10;
            } elseif ($effectiveChance >= 35) {
                $score += 5;
            }
        }

        if ($threeDayRainMm !== null) {
            if ($threeDayRainMm >= 80) {
                $score += 20;
            } elseif ($threeDayRainMm >= 50) {
                $score += 14;
            } elseif ($threeDayRainMm >= 25) {
                $score += 8;
            } elseif ($threeDayRainMm >= 10) {
                $score += 4;
            }
        }

        return $score;
    }

    private function windScore(?float $windSpeedKmh): float
    {
        if ($windSpeedKmh === null) {
            return 0.0;
        }
        if ($windSpeedKmh >= 45) {
            return 10.0;
        }
        if ($windSpeedKmh >= 30) {
            return 7.0;
        }
        if ($windSpeedKmh >= 20) {
            return 4.0;
        }

        return 0.0;
    }

    private function floodRiskScore(string $floodRiskLevel): float
    {
        return match (strtolower($floodRiskLevel)) {
            'critical' => 24.0,
            'high' => 16.0,
            'moderate' => 8.0,
            'low' => 2.0,
            default => 0.0,
        };
    }

    private function cropStageScore(string $stage): float
    {
        return match (strtolower(trim($stage))) {
            'flowering' => 6.0,
            'early_growth', 'vegetative' => 5.0,
            'planting' => 4.0,
            'harvest' => 3.0,
            default => 4.0,
        };
    }

    private function cropTypeScore(string $cropType): float
    {
        return match (strtolower(trim($cropType))) {
            'corn' => 4.0,
            'rice' => 5.0,
            default => 4.0,
        };
    }
}
