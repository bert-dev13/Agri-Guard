<?php

namespace App\Services;

/**
 * Smart Advisory Engine – converts rule-based results into farmer-friendly text.
 *
 * AI layer acts only as advisory enhancement (natural language).
 * Uses template-based generation for explainability. Optional AI API can be
 * plugged in later to improve wording only, never to change logic.
 */
class SmartAdvisoryEngine
{
    public function __construct(
        private readonly RuleBasedAdvisoryService $ruleEngine
    ) {}

    /**
     * Generate smart advisory from rule engine output.
     *
     * @param array{
     *   risk_level: string,
     *   advisory_title: string,
     *   advisory_message: string,
     *   recommended_action: string,
     *   contributing_factors?: string[]
     * } $ruleOutput
     * @param array{
     *   crop_type: string|null,
     *   farming_stage: string|null,
     *   field_condition: string|null,
     *   rainfall_probability: int|null,
     *   forecast_summary: string|null
     * } $context
     * @return array{
     *   headline: string,
     *   short_recommendation: string,
     *   explanation: string,
     *   urgency: string,
     *   action_list: string[],
     *   farm_insight: string,
     *   weather_situation: string,
     *   farm_impact: string
     * }
     */
    public function enhance(array $ruleOutput, array $context = []): array
    {
        $riskLevel = $ruleOutput['risk_level'] ?? 'LOW';
        $crop = $context['crop_type'] ?? null;
        $stage = $context['farming_stage'] ?? null;
        $field = $context['field_condition'] ?? null;
        $rainProb = $context['rainfall_probability'] ?? null;
        $forecastSummary = $context['forecast_summary'] ?? null;

        $headline = $this->buildHeadline($riskLevel, $crop);
        $explanation = $this->buildExplanation($ruleOutput, $crop, $stage, $field);
        $actionList = $this->buildActionList($ruleOutput, $crop, $stage);
        $urgency = $this->urgencyLevel($riskLevel);
        $farmInsight = $this->buildFarmInsight($crop, $stage, $riskLevel, $rainProb);
        $weatherSituation = $this->buildWeatherSituation($riskLevel, $rainProb, $forecastSummary);
        $farmImpact = $this->buildFarmImpact($riskLevel, $crop, $field);

        return [
            'headline' => $headline,
            'short_recommendation' => $ruleOutput['recommended_action'] ?? 'Monitor weather and prepare drainage.',
            'explanation' => $explanation,
            'urgency' => $urgency,
            'action_list' => $actionList,
            'farm_insight' => $farmInsight,
            'weather_situation' => $weatherSituation,
            'farm_impact' => $farmImpact,
        ];
    }

    private function buildHeadline(string $riskLevel, ?string $crop): string
    {
        $cropName = $crop ? " for Your {$crop} Farm" : ' for Your Farm';

        return match ($riskLevel) {
            'HIGH' => "High Rainfall Alert{$cropName}",
            'MODERATE' => "Rain Advisory{$cropName}",
            default => "Weather Update{$cropName}",
        };
    }

    private function buildExplanation(array $ruleOutput, ?string $crop, ?string $stage, ?string $field): string
    {
        $parts = [$ruleOutput['advisory_message'] ?? ''];

        if ($crop && $stage) {
            $stageLabel = $this->ruleEngine->farmingStageLabel($stage);
            $parts[] = "Your crop is in the {$stageLabel} stage.";
        }
        if ($field && in_array($field, ['low_lying', 'flood_prone'], true)) {
            $parts[] = 'Your field may collect water easily.';
        }

        return implode(' ', array_filter($parts));
    }

    private function buildActionList(array $ruleOutput, ?string $crop, ?string $stage): array
    {
        $riskLevel = $ruleOutput['risk_level'] ?? 'LOW';
        $base = [
            'Clear drainage canals',
            'Inspect irrigation channels',
            'Secure tools and materials',
            'Monitor field water level',
        ];

        if ($crop) {
            $cropActions = $this->getCropActions($crop, $riskLevel);
            $base = array_merge($cropActions, array_diff($base, $cropActions));
        }

        $stageAction = $this->getFarmingStageAction($crop, $stage, $riskLevel);
        if ($stageAction !== null) {
            $base = array_merge([$stageAction], $base);
        }

        if (in_array($stage, ['harvest', 'harvesting'], true) && in_array($riskLevel, ['MODERATE', 'HIGH'], true)) {
            $base[] = 'Harvest mature crops early if heavy rain continues';
        }

        return array_values(array_unique(array_slice($base, 0, 8)));
    }

    /**
     * Crop-specific actions. Combines weather + crop logic.
     */
    private function getCropActions(string $crop, string $riskLevel): array
    {
        $lower = strtolower($crop);
        if (str_contains($lower, 'rice')) {
            $actions = [
                'Inspect paddy dikes',
                'Maintain proper water level',
                'Ensure drainage canals are clear',
                'Monitor irrigation channels',
            ];
            if (in_array($riskLevel, ['MODERATE', 'HIGH'], true)) {
                $actions[] = 'Prepare for possible flooding';
            }

            return $actions;
        }
        if (str_contains($lower, 'corn')) {
            $actions = [
                'Improve soil drainage',
                'Protect young plants from strong rainfall',
                'Check field runoff paths',
                'Monitor soil saturation',
            ];
            if (in_array($riskLevel, ['MODERATE', 'HIGH'], true)) {
                $actions[] = 'Avoid excess standing water around roots';
            }

            return $actions;
        }
        if (str_contains($lower, 'vegetable')) {
            $actions = [
                'Protect raised beds',
                'Cover sensitive crops',
                'Improve field drainage',
                'Harvest mature vegetables early if heavy rain continues',
            ];

            return $actions;
        }

        return [];
    }

    /**
     * Optional farming-stage-specific action (e.g. Rice + Planting → protect seedlings).
     */
    private function getFarmingStageAction(?string $crop, ?string $stage, string $riskLevel): ?string
    {
        if ($stage === null || ! in_array($riskLevel, ['MODERATE', 'HIGH'], true)) {
            return null;
        }
        $cropLower = $crop ? strtolower($crop) : '';
        $stageActions = [
            'planting' => str_contains($cropLower, 'rice')
                ? 'Protect seedlings from washout'
                : (str_contains($cropLower, 'vegetable') ? 'Protect seeds and seedlings from heavy rain' : 'Protect seeds and young plants'),
            'early_growth' => str_contains($cropLower, 'corn')
                ? 'Monitor root stability and avoid waterlogging'
                : 'Monitor water accumulation around young plants',
            'harvest' => str_contains($cropLower, 'vegetable')
                ? 'Harvest mature crops before heavy rain'
                : 'Prepare early harvest if rainfall is high',
            'harvesting' => str_contains($cropLower, 'vegetable')
                ? 'Harvest mature crops before heavy rain'
                : 'Prepare early harvest if rainfall is high',
        ];

        return $stageActions[$stage] ?? null;
    }

    /**
     * One or two sentences describing the weather that triggered the advisory.
     */
    private function buildWeatherSituation(string $riskLevel, ?int $rainProb, ?string $forecastSummary): string
    {
        if ($riskLevel === 'HIGH') {
            return $rainProb && $rainProb >= 70
                ? 'Heavy rainfall is expected within the next 24 hours. High chance of prolonged wet conditions.'
                : 'Heavy rainfall is expected. Storm conditions are possible.';
        }
        if ($riskLevel === 'MODERATE') {
            return $rainProb && $rainProb >= 50
                ? 'Moderate rainfall is likely within the next 24 hours.'
                : 'Moderate rainfall is expected. Prepare for possible rain.';
        }
        if ($rainProb && $rainProb >= 40) {
            return 'Light rain is possible. Rainfall may occur in the coming hours.';
        }
        if ($forecastSummary) {
            return $forecastSummary;
        }

        return 'No significant rainfall expected. Conditions are suitable for normal farm activities.';
    }

    /**
     * Short explanation of possible farm impact (crop-aware and field-aware).
     */
    private function buildFarmImpact(string $riskLevel, ?string $crop, ?string $field): string
    {
        $impact = [];
        if (in_array($field, ['low_lying', 'flood_prone'], true) && in_array($riskLevel, ['MODERATE', 'HIGH'], true)) {
            $impact[] = 'Low-lying and flood-prone areas may experience water accumulation.';
        }
        if ($riskLevel === 'HIGH') {
            $impact[] = 'Excess rainfall may cause waterlogging. Strong rain may damage young crops.';
        } elseif ($riskLevel === 'MODERATE') {
            $impact[] = 'Continuous rainfall may increase flood risk in low areas.';
        }
        $cropLower = $crop ? strtolower($crop) : '';
        if (str_contains($cropLower, 'rice')) {
            $impact[] = 'Rice farms are sensitive to flooding and water level control.';
        } elseif (str_contains($cropLower, 'corn')) {
            $impact[] = 'Corn crops can suffer from excessive soil moisture.';
        } elseif (str_contains($cropLower, 'vegetable')) {
            $impact[] = 'Vegetable crops are often sensitive to excess rainfall and waterlogging.';
        }
        if (empty($impact)) {
            return 'Monitor your field. Prepare drainage if rain develops.';
        }

        return implode(' ', array_slice($impact, 0, 2));
    }

    private function urgencyLevel(string $riskLevel): string
    {
        return match ($riskLevel) {
            'HIGH' => 'High',
            'MODERATE' => 'Moderate',
            default => 'Low',
        };
    }

    private function buildFarmInsight(?string $crop, ?string $stage, string $riskLevel, ?int $rainProb): string
    {
        if (! $crop && ! $stage) {
            return $rainProb && $rainProb > 50
                ? 'Rain is possible in your area. Prepare drainage and protect crops.'
                : 'Monitor weather and drainage regularly.';
        }

        $insights = [];

        if ($crop && $stage) {
            $stageLabel = $this->ruleEngine->farmingStageLabel($stage);
            $cropLower = strtolower($crop);
            if (str_contains($cropLower, 'vegetable') && in_array($stage, ['harvest', 'harvesting'], true)) {
                $insights[] = 'Vegetables in harvesting stage may be damaged by continuous rain. Consider early harvest if crops are ready.';
            } elseif (str_contains($cropLower, 'rice') && in_array($stage, ['planting', 'early_growth'], true)) {
                $insights[] = "Rice in {$stageLabel} stage is sensitive to heavy rain. Watch water levels and drainage.";
            } elseif (str_contains($cropLower, 'corn') && in_array($stage, ['flowering', 'flowering_fruiting', 'harvest', 'harvesting'], true)) {
                $insights[] = "Corn in {$stageLabel} stage may lose yield from strong rain. Prepare for early harvest if needed.";
            } else {
                $insights[] = ucfirst($crop)." in {$stageLabel} stage may need extra care during rainy weather.";
            }
        }

        if ($rainProb && $rainProb > 50 && empty($insights)) {
            $insights[] = 'Rain is likely in the next 24 hours. Check drainage and protect sensitive crops.';
        }

        return ! empty($insights) ? implode(' ', $insights) : 'Monitor weather and field conditions regularly.';
    }
}
