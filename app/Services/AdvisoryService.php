<?php

namespace App\Services;

use App\Support\CropImpactLevels;

/**
 * Single-place advisory lines aligned with impact level and flood tier (decision-support tone).
 */
class AdvisoryService
{
    public function __construct(
        private readonly RiskLabelMapper $riskLabels
    ) {}

    /**
     * One short recommended action combining crop impact and flood context.
     *
     * @param  array{level: string}  $cropImpact  From CropImpactService::assess
     */
    public function primaryRecommendedAction(array $cropImpact, string $floodRiskNormalized): string
    {
        $level = strtolower((string) ($cropImpact['level'] ?? ''));
        $flood = strtolower(trim($floodRiskNormalized));

        $floodLine = $this->riskLabels->floodRecommendedAction($flood);

        $impactLine = match ($level) {
            CropImpactLevels::MINIMAL => 'Continue routine monitoring and normal activities where safe.',
            CropImpactLevels::LOW => 'Delay sensitive activities if rain increases, and keep drainage clear.',
            CropImpactLevels::MODERATE => 'Improve drainage and monitor field moisture closely.',
            CropImpactLevels::HIGH => 'Prepare mitigation steps for wet fields and vulnerable crop sections.',
            CropImpactLevels::SEVERE => 'Prioritize safety, drainage, and protecting people, inputs, and crops.',
            default => 'Monitor conditions and adjust field work if weather worsens.',
        };

        if (in_array($flood, ['high', 'critical'], true)) {
            return $floodLine;
        }

        if ($flood === 'moderate' && in_array($level, [CropImpactLevels::MINIMAL, CropImpactLevels::LOW], true)) {
            return 'Prepare drainage channels and watch low-lying sections; '.$impactLine;
        }

        return $impactLine;
    }

    public function impactContextLine(string $impactLevel): string
    {
        return match (strtolower(trim($impactLevel))) {
            CropImpactLevels::MINIMAL => 'Conditions are generally manageable.',
            CropImpactLevels::LOW => 'Minor field disruption is possible.',
            CropImpactLevels::MODERATE => 'Crop stress may develop if wet conditions continue.',
            CropImpactLevels::HIGH => 'Significant field stress is possible.',
            CropImpactLevels::SEVERE => 'Serious crop damage may occur under sustained severe weather.',
            default => 'Conditions should be monitored.',
        };
    }
}
