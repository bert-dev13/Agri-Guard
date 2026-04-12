<?php

namespace App\Services;

use App\Models\User;

/**
 * Rule-Based Advisory Engine for AGRIGUARD.
 *
 * Primary decision logic remains rule-based and explainable for thesis.
 * No AI in this layer – only deterministic rules.
 */
class RuleBasedAdvisoryService
{
    public const THRESHOLD_HIGH_MM = 60;

    public const THRESHOLD_MODERATE_MM = 30;

    public const RAIN_PROBABILITY_STRENGTHEN_PERCENT = 70;

    public const HISTORICAL_HIGH_RAIN_MM = 50;

    public const RISK_LOW = 'LOW';

    public const RISK_MODERATE = 'MODERATE';

    public const RISK_HIGH = 'HIGH';

    /**
     * Generate advisory from inputs. Returns risk level, messages, and contributing factors.
     *
     * @param array{
     *   forecast_rainfall_mm: float|null,
     *   rain_probability: int|null,
     *   wind_speed_kmh: float|null,
     *   current_month: int,
     *   historical_avg_rainfall_mm: float|null,
     *   crop_type: string|null,
     *   farming_stage: string|null,
     *   field_condition: string|null,
     *   farm_location: string|null
     * } $inputs
     * @return array{risk_level: string, advisory_title: string, advisory_message: string, recommended_action: string, contributing_factors: string[]}
     */
    public function generate(array $inputs): array
    {
        $rainMm = $inputs['forecast_rainfall_mm'] ?? null;
        $rainMm = $rainMm !== null ? (float) $rainMm : 0.0;
        $rainProb = isset($inputs['rain_probability']) && $inputs['rain_probability'] !== null
            ? (int) $inputs['rain_probability'] : null;
        $currentMonth = (int) ($inputs['current_month'] ?? (int) now()->format('n'));
        $historicalAvg = isset($inputs['historical_avg_rainfall_mm']) ? (float) $inputs['historical_avg_rainfall_mm'] : null;
        $cropType = $inputs['crop_type'] ?? null;
        $farmingStage = $inputs['farming_stage'] ?? null;
        $fieldCondition = $inputs['field_condition'] ?? null;

        $contributingFactors = [];

        // Base risk from forecast rainfall
        $riskLevel = $this->resolveBaseRiskLevel($rainMm);
        if ($rainMm >= self::THRESHOLD_HIGH_MM) {
            $contributingFactors[] = 'Heavy rainfall forecast ('.round($rainMm).' mm or more)';
        } elseif ($rainMm >= self::THRESHOLD_MODERATE_MM) {
            $contributingFactors[] = 'Moderate rainfall expected';
        }

        $messages = $this->getBaseMessages($riskLevel, $rainMm);
        $advisoryTitle = $messages['title'];
        $advisoryMessage = $messages['message'];
        $recommendedAction = $messages['action'];

        if ($rainProb !== null && $rainProb >= self::RAIN_PROBABILITY_STRENGTHEN_PERCENT) {
            $contributingFactors[] = 'High rain probability ('.$rainProb.'%)';
            $strengthened = $this->strengthenForHighRainProbability(
                $riskLevel, $advisoryTitle, $advisoryMessage, $recommendedAction
            );
            $riskLevel = $strengthened['risk_level'];
            $advisoryTitle = $strengthened['advisory_title'];
            $advisoryMessage = $strengthened['advisory_message'];
            $recommendedAction = $strengthened['recommended_action'];
        }

        if ($historicalAvg !== null && $historicalAvg >= self::HISTORICAL_HIGH_RAIN_MM) {
            $contributingFactors[] = 'Historically wet month (avg '.round($historicalAvg).' mm)';
            $strengthened = $this->strengthenForHistoricalWetMonth(
                $riskLevel, $advisoryTitle, $advisoryMessage, $recommendedAction, $historicalAvg
            );
            $riskLevel = $strengthened['risk_level'];
            $advisoryTitle = $strengthened['advisory_title'];
            $advisoryMessage = $strengthened['advisory_message'];
            $recommendedAction = $strengthened['recommended_action'];
        }

        if ($fieldCondition) {
            $fcLabel = $this->fieldConditionLabel($fieldCondition);
            if (in_array($fieldCondition, ['low_lying', 'flood_prone'], true)) {
                $contributingFactors[] = 'Low-lying or flood-prone field';
            }
            $tailored = $this->tailorForFieldCondition(
                $fieldCondition, $riskLevel, $advisoryTitle, $advisoryMessage, $recommendedAction
            );
            $advisoryTitle = $tailored['advisory_title'];
            $advisoryMessage = $tailored['advisory_message'];
            $recommendedAction = $tailored['recommended_action'];
        }

        if ($farmingStage) {
            $contributingFactors[] = 'Crop in '.$this->farmingStageLabel($farmingStage).' stage';
            $tailored = $this->tailorForFarmingStage(
                $farmingStage, $riskLevel, $advisoryTitle, $advisoryMessage, $recommendedAction
            );
            $advisoryTitle = $tailored['advisory_title'];
            $advisoryMessage = $tailored['advisory_message'];
            $recommendedAction = $tailored['recommended_action'];
        }

        if ($cropType !== null && $cropType !== '') {
            $tailored = $this->tailorForCropType(
                $cropType, $riskLevel, $advisoryTitle, $advisoryMessage, $recommendedAction
            );
            $advisoryTitle = $tailored['advisory_title'];
            $advisoryMessage = $tailored['advisory_message'];
            $recommendedAction = $tailored['recommended_action'];
        }

        if (empty($contributingFactors)) {
            $contributingFactors[] = $riskLevel === self::RISK_LOW ? 'Normal weather conditions' : 'Weather and field factors combined';
        }

        return [
            'risk_level' => $riskLevel,
            'advisory_title' => $advisoryTitle,
            'advisory_message' => $advisoryMessage,
            'recommended_action' => $recommendedAction,
            'contributing_factors' => array_unique($contributingFactors),
        ];
    }

    public function generateForUser(User $user, array $weatherPayload): array
    {
        $currentMonth = (int) now()->format('n');
        $historicalAvg = \App\Models\HistoricalWeather::averageRainfallForMonth($currentMonth);

        $locationParts = array_filter([
            $user->farm_barangay_name,
            $user->farm_municipality,
            'Cagayan',
            'Philippines',
        ]);
        $farmLocation = implode(', ', $locationParts) ?: null;

        $inputs = [
            'forecast_rainfall_mm' => $weatherPayload['forecast_rainfall_mm'] ?? null,
            'rain_probability' => $weatherPayload['forecast_rain_probability'] ?? null,
            'wind_speed_kmh' => isset($weatherPayload['weather']['wind_speed'])
                ? (float) $weatherPayload['weather']['wind_speed'] : null,
            'current_month' => $currentMonth,
            'historical_avg_rainfall_mm' => $historicalAvg,
            'crop_type' => $user->crop_type,
            'farming_stage' => $user->farming_stage,
            'field_condition' => null,
            'farm_location' => $farmLocation,
        ];

        return $this->generate($inputs);
    }

    protected function resolveBaseRiskLevel(float $rainMm): string
    {
        if ($rainMm >= self::THRESHOLD_HIGH_MM) {
            return self::RISK_HIGH;
        }
        if ($rainMm >= self::THRESHOLD_MODERATE_MM && $rainMm < self::THRESHOLD_HIGH_MM) {
            return self::RISK_MODERATE;
        }

        return self::RISK_LOW;
    }

    protected function getBaseMessages(string $riskLevel, float $rainMm): array
    {
        switch ($riskLevel) {
            case self::RISK_HIGH:
                return [
                    'title' => 'Heavy rain expected',
                    'message' => 'Heavy rain (60 mm or more) is possible. High chance of water buildup in your field.',
                    'action' => 'Check drainage, secure tools, and protect stored crops. Limit field work.',
                ];
            case self::RISK_MODERATE:
                return [
                    'title' => 'Moderate rain expected',
                    'message' => 'Moderate rain (30–60 mm) is possible. Prepare drainage and watch for updates.',
                    'action' => 'Prepare drainage and watch for updates. You may delay non-essential field work.',
                ];
            default:
                return [
                    'title' => 'Conditions are manageable',
                    'message' => 'No strong rain expected. You can do normal farm activities with usual care.',
                    'action' => 'Continue to monitor the weather.',
                ];
        }
    }

    protected function strengthenForHighRainProbability(
        string $riskLevel,
        string $title,
        string $message,
        string $action
    ): array {
        if ($riskLevel === self::RISK_HIGH) {
            $message .= ' Rain chance is high — expect wet conditions for some time.';
            $action = 'Check drainage, secure tools, and protect stored crops. Stay updated on forecasts.';

            return ['risk_level' => $riskLevel, 'advisory_title' => $title, 'advisory_message' => $message, 'recommended_action' => $action];
        }
        if ($riskLevel === self::RISK_MODERATE) {
            return [
                'risk_level' => self::RISK_HIGH,
                'advisory_title' => 'Moderate to heavy rain likely',
                'advisory_message' => 'Moderate rain and high rain chance. Prepare for wet conditions.',
                'recommended_action' => 'Prepare drainage, secure tools, and watch updates. Be ready to limit field work if it gets worse.',
            ];
        }

        return [
            'risk_level' => self::RISK_MODERATE,
            'advisory_title' => 'Higher chance of rain',
            'advisory_message' => 'Rain chance is high. Prepare for possible rain even if amounts are low.',
            'recommended_action' => 'Prepare drainage and watch weather updates.',
        ];
    }

    protected function strengthenForHistoricalWetMonth(
        string $riskLevel,
        string $title,
        string $message,
        string $action,
        float $historicalAvg
    ): array {
        $message .= ' This month often has strong rain (avg '.round($historicalAvg).' mm).';

        if ($riskLevel === self::RISK_LOW) {
            return [
                'risk_level' => self::RISK_MODERATE,
                'advisory_title' => 'Wet month — stay prepared',
                'advisory_message' => $message,
                'recommended_action' => 'Prepare drainage and watch updates; heavy rain is more likely this month.',
            ];
        }
        if ($riskLevel === self::RISK_MODERATE) {
            return [
                'risk_level' => self::RISK_HIGH,
                'advisory_title' => 'Higher risk — wet month',
                'advisory_message' => $message,
                'recommended_action' => 'Prepare drainage, secure tools, and prepare for possible flooding. This month often has heavy rain.',
            ];
        }

        return ['risk_level' => $riskLevel, 'advisory_title' => $title, 'advisory_message' => $message, 'recommended_action' => $action];
    }

    protected function tailorForFieldCondition(
        string $fieldCondition,
        string $riskLevel,
        string $title,
        string $message,
        string $action
    ): array {
        if (in_array($fieldCondition, ['low_lying', 'flood_prone'], true) && in_array($riskLevel, [self::RISK_MODERATE, self::RISK_HIGH], true)) {
            $message .= ' Your field may collect water easily.';
            $action = 'Clear drainage paths and watch water levels. Protect seeds or crops near low areas.';
        }

        return ['advisory_title' => $title, 'advisory_message' => $message, 'recommended_action' => $action];
    }

    protected function tailorForFarmingStage(
        string $farmingStage,
        string $riskLevel,
        string $title,
        string $message,
        string $action
    ): array {
        $stageActions = [
            'land_preparation' => 'Check soil moisture and prepare drainage before planting.',
            'planting' => 'Protect seeds and seedlings. Heavy rain may wash them away.',
            'early_growth' => 'Watch root growth. Avoid waterlogging in the field.',
            'vegetative' => 'Keep drainage clear. Too much rain can wash away nutrients.',
            'growing' => 'Keep drainage clear. Too much rain can wash away nutrients.',
            'flowering' => 'Lessen crop stress. Check for disease after rain.',
            'flowering_fruiting' => 'Lessen crop stress. Check for disease after rain.',
            'harvest' => 'Harvest ready crops first. Avoid spoilage and flooding.',
            'harvesting' => 'Harvest ready crops first. Avoid spoilage and flooding.',
        ];
        $stageAction = $stageActions[$farmingStage] ?? null;
        if ($stageAction && in_array($riskLevel, [self::RISK_MODERATE, self::RISK_HIGH], true)) {
            $action = $stageAction.' '.$action;
        }

        return ['advisory_title' => $title, 'advisory_message' => $message, 'recommended_action' => trim($action)];
    }

    protected function tailorForCropType(
        string $cropType,
        string $riskLevel,
        string $title,
        string $message,
        string $action
    ): array {
        $lower = strtolower($cropType);

        if ($this->isRiceCrop($lower)) {
            $message .= ' Rice farms: watch water level and drainage.';
            if (in_array($riskLevel, [self::RISK_MODERATE, self::RISK_HIGH], true)) {
                $action = 'Check field drainage and water level. Clear canals. Protect stored grain.';
            }

            return ['advisory_title' => $title, 'advisory_message' => $message, 'recommended_action' => $action];
        }

        if ($this->isRainSensitiveCrop($lower) && in_array($riskLevel, [self::RISK_MODERATE, self::RISK_HIGH], true)) {
            $message .= ' Your crop (e.g. vegetables or corn) is sensitive to heavy rain — take care.';
            $action = 'Prepare drainage, protect vulnerable crops. Harvest ready produce early if possible.';
        }

        return ['advisory_title' => $title, 'advisory_message' => $message, 'recommended_action' => $action];
    }

    protected function isRiceCrop(string $cropTypeLower): bool
    {
        return str_contains($cropTypeLower, 'rice');
    }

    protected function isRainSensitiveCrop(string $cropTypeLower): bool
    {
        $sensitive = ['vegetables', 'vegetable', 'corn', 'tomato', 'eggplant', 'pepper', 'leafy'];
        foreach ($sensitive as $term) {
            if (str_contains($cropTypeLower, $term)) {
                return true;
            }
        }

        return false;
    }

    public function farmingStageLabel(string $stage): string
    {
        return match ($stage) {
            'land_preparation' => 'land preparation',
            'planting' => 'planting',
            'early_growth' => 'early growth',
            'vegetative' => 'vegetative',
            'growing' => 'vegetative',
            'flowering' => 'flowering',
            'flowering_fruiting' => 'flowering',
            'harvest' => 'harvest',
            'harvesting' => 'harvest',
            default => $stage,
        };
    }

    public function fieldConditionLabel(string $condition): string
    {
        return match ($condition) {
            'low_lying' => 'Low-lying',
            'elevated' => 'Elevated',
            'sloped' => 'Sloped',
            'flood_prone' => 'Flood-prone',
            'well_drained' => 'Well-drained',
            default => $condition,
        };
    }
}
