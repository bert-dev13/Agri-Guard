<?php

namespace App\Services;

use App\Support\CropImpactLevels;

/**
 * Outlook lines generated only from final crop-impact tier — text never drives severity.
 */
final class OutlookTextGenerator
{
    /**
     * Approved Low-impact language only (flood Low hard-cap and normal Low tier).
     *
     * @return list<string>
     */
    public static function lowSeverityApprovedLines(): array
    {
        return [
            'Light to moderate rain may delay spraying and increase surface moisture.',
            'Minor rain interruptions may affect timing of field applications.',
            'Mostly manageable conditions with minor disruption to field scheduling.',
            'Light rain may increase field moisture — keep drainage paths clear.',
        ];
    }

    /**
     * Deterministic pick for hard-capped Low flood scenarios.
     */
    public function generateLowSeverityOutlook(): string
    {
        $n = (int) now()->format('z');

        return $this->pick($n, self::lowSeverityApprovedLines());
    }

    /**
     * Stable short lines per tier (deterministic rotation by weekday to avoid staleness).
     */
    public function generate(string $cropImpactLevel): string
    {
        $level = strtolower(trim($cropImpactLevel));
        $n = (int) now()->format('z');

        return match ($level) {
            CropImpactLevels::MINIMAL => $this->pick($n, [
                'Mostly manageable conditions with only minor weather disruption.',
                'Light weather changes may have little effect on field activity.',
            ]),
            CropImpactLevels::LOW => $this->pick($n, self::lowSeverityApprovedLines()),
            CropImpactLevels::MODERATE => $this->pick($n, [
                'Repeated rainfall may raise field moisture and create moderate crop stress in vulnerable areas.',
                'Continued wet conditions may interrupt sensitive field work and increase drainage pressure.',
            ]),
            CropImpactLevels::HIGH => $this->pick($n, [
                'Heavy rainfall may disrupt field activity and increase crop stress in exposed areas.',
                'Strong weather stress may increase the risk of operational and field damage.',
            ]),
            CropImpactLevels::SEVERE => $this->pick($n, [
                'Extreme weather conditions may cause serious field disruption and substantial crop stress.',
                'Severe rain and environmental pressure may result in major damage risk.',
            ]),
            default => 'Monitor short-range weather and adjust field timing if conditions shift.',
        };
    }

    /**
     * @param  list<string>  $lines
     */
    private function pick(int $salt, array $lines): string
    {
        return $lines[$salt % count($lines)];
    }
}
