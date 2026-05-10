<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

/**
 * Smart Advisory Service – combines weather, flood risk, and crop growth stage
 * to generate clear, personalized, farmer-friendly recommendations.
 *
 * Rule-based and explainable. Structured for future AI refinement, SMS, or history.
 */
class SmartAdvisoryService
{
    public const RISK_HIGH = 'HIGH';

    public const RISK_MODERATE = 'MODERATE';

    public const RISK_LOW = 'LOW';

    /** Rain probability thresholds (%). */
    private const RAIN_PROB_HIGH = 70;

    private const RAIN_PROB_MODERATE_MIN = 40;

    private const RAIN_PROB_MODERATE_MAX = 69;

    /** Crop stages considered sensitive to heavy rain (seedling/vegetative). */
    private const SENSITIVE_STAGES = ['seedling', 'vegetative', 'planting', 'early_growth', 'land_preparation'];

    /** Reproductive-type stages. */
    private const REPRODUCTIVE_STAGES = ['flowering', 'flowering_fruiting', 'reproductive'];

    /** Maturity-type stages. */
    private const MATURITY_STAGES = ['harvest', 'harvesting', 'maturity'];

    public function __construct(
        private readonly FarmWeatherService $farmWeather,
        private readonly FloodRiskAssessmentService $floodRisk
    ) {}

    /**
     * Build full advisory payload for the authenticated user.
     *
     * @return array{
     *   has_advisory: bool,
     *   missing_crop: bool,
     *   missing_weather: bool,
     *   flood_risk_unavailable: bool,
     *   risk_level: string,
     *   risk_label: string,
     *   risk_color: string,
     *   advisory_title: string,
     *   advisory_message: string,
     *   recommended_actions: string[],
     *   explanation: string,
     *   advisory_categories: string[],
     *   factors: array,
     *   weather: array|null,
     *   flood_risk: array|null,
     *   crop_type: string|null,
     *   crop_stage: string|null,
     *   crop_stage_display: string|null,
     *   days_after_planting: int|null,
     *   planting_date: string|null,
     *   area_condition: string|null,
     *   last_updated: string|null
     * }
     */
    public function buildForUser(User $user): array
    {
        $weather = $this->farmWeather->getNormalizedWeatherForUser($user);
        $hasWeather = ! empty($weather['last_updated']) || (
            $weather['today_rain_probability'] !== null || $weather['condition'] !== null
        );

        $userContext = [];
        $floodResult = $this->floodRisk->assess($weather, $userContext);
        $areaCondition = $this->floodRisk->areaConditionLabel($userContext);

        $cropType = $user->crop_type;
        $plantingDate = $user->planting_date;
        $daysAfterPlanting = $plantingDate ? (int) Carbon::now()->startOfDay()->diffInDays($plantingDate, false) : null;
        if ($daysAfterPlanting !== null && $daysAfterPlanting < 0) {
            $daysAfterPlanting = 0;
        }
        $cropStage = $this->resolveCropStage($user, $daysAfterPlanting);
        $cropStageDisplay = $this->cropStageDisplayLabel($cropStage);

        $missingCrop = empty($cropType) || empty($plantingDate);
        $missingWeather = ! $hasWeather;
        $floodRiskUnavailable = false; // We always have flood risk from assessment

        if ($missingCrop) {
            return $this->emptyAdvisoryResponse([
                'missing_crop' => true,
                'weather' => $this->weatherFactorsForView($weather),
                'flood_risk' => [
                    'label' => $floodResult['label'],
                    'color' => $floodResult['color'],
                    'level' => $floodResult['level'],
                ],
                'area_condition' => $areaCondition,
                'last_updated' => $weather['last_updated'] ?? null,
            ]);
        }

        if ($missingWeather) {
            return $this->emptyAdvisoryResponse([
                'missing_weather' => true,
                'crop_type' => $cropType,
                'crop_stage' => $cropStage,
                'crop_stage_display' => $cropStageDisplay,
                'days_after_planting' => $daysAfterPlanting,
                'planting_date' => $plantingDate?->format('M j, Y'),
                'area_condition' => $areaCondition,
                'flood_risk' => [
                    'label' => $floodResult['label'],
                    'color' => $floodResult['color'],
                    'level' => $floodResult['level'],
                ],
            ]);
        }

        $result = $this->generateAdvisory(
            $weather,
            $floodResult,
            $cropType,
            $cropStage,
            $cropStageDisplay,
            $areaCondition
        );

        $factors = [
            'weather_condition' => $weather['condition'] ?? '—',
            'rain_probability' => $weather['today_rain_probability'],
            'flood_risk_label' => $floodResult['label'],
            'flood_risk_color' => $floodResult['color'],
            'crop_type' => $cropType,
            'crop_stage' => $cropStageDisplay,
            'area_condition' => $areaCondition,
        ];

        return [
            'has_advisory' => true,
            'missing_crop' => false,
            'missing_weather' => false,
            'flood_risk_unavailable' => $floodRiskUnavailable,
            'risk_level' => $result['risk_level'],
            'risk_label' => $result['risk_label'],
            'risk_color' => $result['risk_color'],
            'advisory_title' => $result['advisory_title'],
            'advisory_message' => $result['advisory_message'],
            'recommended_actions' => $result['recommended_actions'],
            'explanation' => $result['explanation'],
            'advisory_categories' => $result['advisory_categories'],
            'factors' => $factors,
            'weather' => $this->weatherFactorsForView($weather),
            'flood_risk' => [
                'label' => $floodResult['label'],
                'color' => $floodResult['color'],
                'level' => $floodResult['level'],
            ],
            'crop_type' => $cropType,
            'crop_stage' => $cropStage,
            'crop_stage_display' => $cropStageDisplay,
            'days_after_planting' => $daysAfterPlanting,
            'planting_date' => $plantingDate?->format('M j, Y'),
            'area_condition' => $areaCondition,
            'last_updated' => $weather['last_updated'] ?? null,
        ];
    }

    /**
     * Generate advisory from weather, flood risk, and crop context.
     */
    private function generateAdvisory(
        array $weather,
        array $floodResult,
        string $cropType,
        string $cropStage,
        string $cropStageDisplay,
        string $areaCondition
    ): array {
        $rainProb = $weather['today_rain_probability'] !== null ? (int) $weather['today_rain_probability'] : null;
        $floodLevel = $floodResult['level'] ?? FloodRiskAssessmentService::RISK_LOW;
        $condition = $weather['condition'] ?? null;

        $rainProbCategory = $this->rainProbabilityCategory($rainProb);

        // CASE 1: High risk – rain prob >= 70%, high flood, seedling/vegetative
        if ($rainProbCategory === 'high' && $floodLevel === FloodRiskAssessmentService::RISK_HIGH
            && $this->isSensitiveStage($cropStage)) {
            return $this->buildHighRiskAdvisory($cropType, $cropStageDisplay, $areaCondition, $rainProb, $condition);
        }
        if ($rainProbCategory === 'high' && $this->isSensitiveStage($cropStage)) {
            return $this->buildHighRiskAdvisory($cropType, $cropStageDisplay, $areaCondition, $rainProb, $condition);
        }
        if ($floodLevel === FloodRiskAssessmentService::RISK_HIGH && $this->isSensitiveStage($cropStage)) {
            return $this->buildHighRiskAdvisory($cropType, $cropStageDisplay, $areaCondition, $rainProb, $condition);
        }

        // CASE 2: Moderate – 40–69% rain, moderate flood, reproductive
        if ($rainProbCategory === 'moderate' && in_array($floodLevel, [FloodRiskAssessmentService::RISK_MODERATE, FloodRiskAssessmentService::RISK_HIGH], true)) {
            return $this->buildModerateRiskAdvisory($cropType, $cropStageDisplay, $areaCondition, $rainProb);
        }
        if ($rainProbCategory === 'moderate') {
            return $this->buildModerateRiskAdvisory($cropType, $cropStageDisplay, $areaCondition, $rainProb);
        }

        // CASE 3: Low – < 40%, low flood, or maturity
        return $this->buildLowRiskAdvisory($cropType, $cropStageDisplay, $rainProb);
    }

    private function rainProbabilityCategory(?int $rainProb): string
    {
        if ($rainProb === null) {
            return 'low';
        }
        if ($rainProb >= self::RAIN_PROB_HIGH) {
            return 'high';
        }
        if ($rainProb >= self::RAIN_PROB_MODERATE_MIN && $rainProb <= self::RAIN_PROB_MODERATE_MAX) {
            return 'moderate';
        }

        return 'low';
    }

    private function isSensitiveStage(string $stage): bool
    {
        return in_array($stage, self::SENSITIVE_STAGES, true);
    }

    private function buildHighRiskAdvisory(
        string $cropType,
        string $cropStageDisplay,
        string $areaCondition,
        ?int $rainProb,
        ?string $condition
    ): array {
        $cropLower = strtolower($cropType);
        $message = 'Heavy rainfall may affect your crop. Clear drainage canals and protect young plants immediately.';
        $actions = [
            'Clear drainage canals',
            'Avoid fertilizer application today',
            'Inspect low-lying areas for water buildup',
            'Secure sensitive crops and young plants',
        ];

        if (str_contains($cropLower, 'rice')) {
            $message = sprintf(
                'Heavy rainfall is expected within the next 24 hours. Your rice crop is currently in the %s stage. Check drainage canals and avoid fertilizer application today.',
                strtolower($cropStageDisplay)
            );
            $actions = [
                'Inspect drainage canals',
                'Monitor water accumulation',
                'Avoid fertilizer application today',
                'Check young plants for possible water damage',
            ];
        } elseif (str_contains($cropLower, 'vegetable')) {
            $message = 'Heavy rain is likely. Your vegetables are in a sensitive growth stage. Protect crops and ensure raised beds drain well.';
            $actions = [
                'Check raised beds and drainage',
                'Avoid fertilizer application today',
                'Protect young plants from water damage',
                'Monitor for waterlogging',
            ];
        } elseif (str_contains($cropLower, 'corn')) {
            $message = sprintf(
                'Heavy rainfall is expected. Your corn is in the %s stage. Clear drainage and protect roots from waterlogging.',
                strtolower($cropStageDisplay)
            );
            $actions = [
                'Clear field drainage paths',
                'Avoid fertilizer application today',
                'Monitor soil saturation',
                'Protect young plants from runoff',
            ];
        }

        $explanation = 'This recommendation is based on high rain probability, current flood risk, and the sensitive growth stage of your crop.';

        return [
            'risk_level' => self::RISK_HIGH,
            'risk_label' => 'High Risk',
            'risk_color' => '#dc2626',
            'advisory_title' => 'Heavy rain expected – take action',
            'advisory_message' => $message,
            'recommended_actions' => $actions,
            'explanation' => $explanation,
            'advisory_categories' => ['Rain Advisory', 'Flood Preparedness', 'Crop Stage Advisory', 'Field Management Tip'],
        ];
    }

    private function buildModerateRiskAdvisory(
        string $cropType,
        string $cropStageDisplay,
        string $areaCondition,
        ?int $rainProb
    ): array {
        $message = sprintf(
            'Moderate rainfall is expected. Monitor field water levels and protect crops in sensitive growth stages. Your crop is in the %s stage.',
            strtolower($cropStageDisplay)
        );
        $actions = [
            'Observe water level in the field',
            'Inspect drainage paths',
            'Monitor for pests and diseases after rain',
            'Stay updated on weather changes',
        ];

        if (str_contains(strtolower($cropType), 'rice')) {
            $actions = [
                'Maintain proper water level in paddies',
                'Observe for pest and disease risk',
                'Check bunds and drainage paths',
                'Stay updated on weather changes',
            ];
        }

        return [
            'risk_level' => self::RISK_MODERATE,
            'risk_label' => 'Moderate Risk',
            'risk_color' => '#f59e0b',
            'advisory_title' => 'Moderate rain expected',
            'advisory_message' => $message,
            'recommended_actions' => $actions,
            'explanation' => 'This recommendation is based on moderate rain probability, current flood risk, and your crop growth stage.',
            'advisory_categories' => ['Rain Advisory', 'Crop Stage Advisory', 'Field Management Tip'],
        ];
    }

    private function buildLowRiskAdvisory(string $cropType, string $cropStageDisplay, ?int $rainProb): array
    {
        $message = 'Current weather risk is low. Continue regular field monitoring and prepare for harvest if needed.';
        if (str_contains(strtolower($cropType), 'rice')) {
            $message = sprintf(
                'Weather risk is low. Your rice is in the %s stage. Continue normal field monitoring and prepare for harvest if needed.',
                strtolower($cropStageDisplay)
            );
        }

        $actions = [
            'Continue normal field monitoring',
            'Stay updated on weather changes',
            'Prepare for possible weather shifts',
        ];

        return [
            'risk_level' => self::RISK_LOW,
            'risk_label' => 'Low Risk',
            'risk_color' => '#2E7D32',
            'advisory_title' => 'Conditions are manageable',
            'advisory_message' => $message,
            'recommended_actions' => $actions,
            'explanation' => 'This recommendation is based on low rain probability, current flood risk, and your crop stage. Risk may change; keep monitoring.',
            'advisory_categories' => ['Field Management Tip', 'Weather Update'],
        ];
    }

    private function resolveCropStage(User $user, ?int $daysAfterPlanting): string
    {
        if ($user->farming_stage) {
            $stage = $user->farming_stage;

            return $this->mapToAdvisoryStage($stage);
        }
        if ($daysAfterPlanting === null) {
            return 'unknown';
        }

        return $this->deriveStageFromDays($user->crop_type, $daysAfterPlanting);
    }

    private function mapToAdvisoryStage(string $farmingStage): string
    {
        $map = [
            'land_preparation' => 'land_preparation',
            'planting' => 'seedling',
            'early_growth' => 'vegetative',
            'vegetative' => 'vegetative',
            'growing' => 'vegetative',
            'flowering' => 'reproductive',
            'flowering_fruiting' => 'reproductive',
            'harvest' => 'maturity',
            'harvesting' => 'maturity',
        ];

        return $map[$farmingStage] ?? $farmingStage;
    }

    private function deriveStageFromDays(?string $cropType, int $days): string
    {
        $cropLower = $cropType ? strtolower($cropType) : '';
        if (str_contains($cropLower, 'rice')) {
            if ($days <= 21) {
                return 'vegetative';
            }
            if ($days <= 55) {
                return 'reproductive';
            }

            return 'maturity';
        }
        if (str_contains($cropLower, 'corn')) {
            if ($days <= 30) {
                return 'vegetative';
            }
            if ($days <= 70) {
                return 'reproductive';
            }

            return 'maturity';
        }
        if ($days <= 14) {
            return 'seedling';
        }
        if ($days <= 40) {
            return 'vegetative';
        }
        if ($days <= 70) {
            return 'reproductive';
        }

        return 'maturity';
    }

    private function cropStageDisplayLabel(string $stage): string
    {
        return match ($stage) {
            'seedling' => 'Seedling Stage',
            'vegetative' => 'Vegetative Stage',
            'reproductive' => 'Reproductive Stage',
            'maturity' => 'Maturity Stage',
            'land_preparation' => 'Land Preparation',
            'planting' => 'Planting',
            'early_growth' => 'Early Growth',
            'growing' => 'Vegetative',
            'flowering' => 'Flowering',
            'flowering_fruiting' => 'Flowering',
            'harvest' => 'Harvest',
            'harvesting' => 'Harvest',
            default => ucfirst(str_replace('_', ' ', $stage)),
        };
    }

    private function weatherFactorsForView(array $weather): array
    {
        return [
            'condition' => $weather['condition'] ?? null,
            'today_rain_probability' => $weather['today_rain_probability'] ?? null,
            'today_expected_rainfall' => $weather['today_expected_rainfall'] ?? null,
            'daily_forecast' => $weather['daily_forecast'] ?? [],
            'last_updated' => $weather['last_updated'] ?? null,
        ];
    }

    private function emptyAdvisoryResponse(array $merge): array
    {
        return array_merge([
            'has_advisory' => false,
            'missing_crop' => false,
            'missing_weather' => false,
            'flood_risk_unavailable' => false,
            'risk_level' => self::RISK_LOW,
            'risk_label' => '—',
            'risk_color' => '#2E7D32',
            'advisory_title' => '',
            'advisory_message' => '',
            'recommended_actions' => [],
            'explanation' => '',
            'advisory_categories' => [],
            'factors' => [],
            'weather' => null,
            'flood_risk' => null,
            'crop_type' => null,
            'crop_stage' => null,
            'crop_stage_display' => null,
            'days_after_planting' => null,
            'planting_date' => null,
            'area_condition' => null,
            'last_updated' => null,
        ], $merge);
    }
}
