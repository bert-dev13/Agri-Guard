<?php

namespace App\Services;

use App\Models\User;

/**
 * Reusable rule-based flood risk assessment for AGRIGUARD.
 * Uses weather data (rain probability, expected rainfall, condition) and optional
 * area/field context. Easy to tune or extend with elevation/hazard APIs later.
 */
class FloodRiskAssessmentService
{
    /** Risk levels used across the app */
    public const RISK_LOW = 'LOW';
    public const RISK_MODERATE = 'MODERATE';
    public const RISK_HIGH = 'HIGH';

    /** Rain probability threshold: below this = low risk (when no other factors) */
    private const RAIN_PROB_LOW_MAX = 39;

    /** Rain probability threshold: 40–69% = moderate; 70%+ = high */
    private const RAIN_PROB_MODERATE_MAX = 69;

    /** Expected rainfall (mm) thresholds for accumulation */
    private const RAIN_MM_LIGHT_MAX = 5.0;
    private const RAIN_MM_MODERATE_MAX = 20.0;

    /** OpenWeatherMap condition id ranges */
    private const CONDITION_THUNDER = [200, 299];
    private const CONDITION_RAIN = [300, 599];

    /**
     * Assess flood risk from normalized weather and optional user context.
     *
     * @param  array{today_rain_probability?: int|null, today_expected_rainfall?: float|null, condition_id?: int|null, condition?: string|null}  $weather  Normalized weather (e.g. from FarmWeatherService)
     * @param  array{field_condition?: string|null}  $userContext  Optional user field_condition for low-lying factor
     * @return array{level: string, label: string, color: string, message: string}
     */
    public function assess(array $weather, array $userContext = []): array
    {
        $rainProb = isset($weather['today_rain_probability']) ? (int) $weather['today_rain_probability'] : null;
        $rainMm = isset($weather['today_expected_rainfall']) ? (float) $weather['today_expected_rainfall'] : null;
        $conditionId = isset($weather['condition_id']) ? (int) $weather['condition_id'] : null;
        $isLowLying = $this->isLowLyingArea($userContext);

        $level = $this->computeLevel($rainProb, $rainMm, $conditionId, $isLowLying);
        $label = $this->riskLabel($level);
        $color = $this->riskColor($level);
        $message = $this->farmerMessage($level);

        return [
            'level' => $level,
            'label' => $label,
            'color' => $color,
            'message' => $message,
        ];
    }

    /**
     * Get rainfall accumulation label from expected rainfall (mm).
     *
     * @return string e.g. "Low accumulation", "Moderate accumulation", "Heavy accumulation"
     */
    public function rainfallAccumulationLabel(?float $expectedRainMm): string
    {
        if ($expectedRainMm === null || $expectedRainMm <= 0) {
            return 'Low accumulation';
        }
        if ($expectedRainMm <= self::RAIN_MM_LIGHT_MAX) {
            return 'Low accumulation';
        }
        if ($expectedRainMm <= self::RAIN_MM_MODERATE_MAX) {
            return 'Moderate accumulation';
        }
        return 'Heavy accumulation';
    }

    /**
     * Get area condition label for display (rule-based placeholder; upgrade with elevation API later).
     *
     * @param  array{field_condition?: string|null}  $userContext  User's field_condition
     */
    public function areaConditionLabel(array $userContext = []): string
    {
        $field = $userContext['field_condition'] ?? null;
        if (empty($field)) {
            return 'Moderately elevated area';
        }
        if (in_array($field, ['low_lying', 'flood_prone'], true)) {
            return 'Low-lying area';
        }
        if (in_array($field, ['elevated', 'well_drained'], true)) {
            return 'Elevated area';
        }
        if ($field === 'sloped') {
            return 'Moderately elevated area';
        }
        return 'Moderately elevated area';
    }

    /**
     * Compute risk level from inputs.
     */
    private function computeLevel(?int $rainProb, ?float $rainMm, ?int $conditionId, bool $isLowLying): string
    {
        $isHeavyRainCondition = $this->isHeavyRainCondition($conditionId);
        $isModerateOrHeavyRainfall = $rainMm !== null && $rainMm > self::RAIN_MM_LIGHT_MAX;

        // HIGH: rain probability >= 70% OR heavy rainfall OR thunder/heavy rain condition (worse if low-lying)
        if ($rainProb !== null && $rainProb >= 70) {
            return self::RISK_HIGH;
        }
        if ($rainMm !== null && $rainMm > self::RAIN_MM_MODERATE_MAX) {
            return self::RISK_HIGH;
        }
        if ($isHeavyRainCondition && $isLowLying) {
            return self::RISK_HIGH;
        }
        if ($isHeavyRainCondition) {
            return self::RISK_HIGH;
        }

        // MODERATE: 40–69% rain probability OR moderate rainfall OR rainy/cloudy + low-lying
        if ($rainProb !== null && $rainProb >= 40 && $rainProb <= self::RAIN_PROB_MODERATE_MAX) {
            return self::RISK_MODERATE;
        }
        if ($isModerateOrHeavyRainfall) {
            return self::RISK_MODERATE;
        }
        if ($this->isRainyOrCloudyCondition($conditionId) && $isLowLying) {
            return self::RISK_MODERATE;
        }

        // LOW: default
        return self::RISK_LOW;
    }

    private function isHeavyRainCondition(?int $conditionId): bool
    {
        if ($conditionId === null) {
            return false;
        }
        // Thunderstorm
        if ($conditionId >= self::CONDITION_THUNDER[0] && $conditionId <= self::CONDITION_THUNDER[1]) {
            return true;
        }
        // Heavy rain (5xx)
        if ($conditionId >= 500 && $conditionId < 600) {
            return true;
        }
        return false;
    }

    private function isRainyOrCloudyCondition(?int $conditionId): bool
    {
        if ($conditionId === null) {
            return false;
        }
        if ($conditionId >= 300 && $conditionId < 600) {
            return true;
        }
        if ($conditionId >= 801 && $conditionId <= 804) {
            return true;
        }
        return false;
    }

    private function isLowLyingArea(array $userContext): bool
    {
        $field = $userContext['field_condition'] ?? null;
        return in_array($field, ['low_lying', 'flood_prone'], true);
    }

    private function riskLabel(string $level): string
    {
        return match ($level) {
            self::RISK_HIGH => 'High Risk',
            self::RISK_MODERATE => 'Moderate Risk',
            default => 'Low Risk',
        };
    }

    private function riskColor(string $level): string
    {
        return match ($level) {
            self::RISK_HIGH => '#dc2626',
            self::RISK_MODERATE => '#f59e0b',
            default => '#2E7D32',
        };
    }

    /**
     * Farmer-friendly explanation for "What this means for your farm".
     */
    private function farmerMessage(string $level): string
    {
        return match ($level) {
            self::RISK_HIGH => 'Rainfall patterns suggest higher runoff risk — move inputs or tools to safer ground and keep drainage open.',
            self::RISK_MODERATE => 'Prepare drainage channels and watch low-lying sections if rain increases.',
            default => 'Keep drainage clear and monitor low spots.',
        };
    }
}
