<?php

namespace App\Services;

use App\Models\User;
use App\Support\CropImpactLevels;

/**
 * Final override pass after flood limiting — deterministic, no keyword inflation.
 *
 * {@see validateAdvisoryFinal()} is the non-bypassable low-flood hard cap before rendering.
 */
final class AdvisoryConsistencyValidator
{
    /**
     * Last-line consistency before outlook generation.
     *
     * @param  array<string, mixed>  $normalized
     */
    public function finalize(
        string $afterFloodLevel,
        string $floodRiskNormalized,
        array $normalized,
        string $weatherSeverity,
        string $actionSeverity,
        FloodRiskLimiter $floodLimiter
    ): string {
        $flood = strtolower(trim($floodRiskNormalized));
        if ($flood === 'unknown') {
            $flood = 'low';
        }

        $rank = $this->rankFromLevel($afterFloodLevel);
        $extreme = $floodLimiter->extremeNonFloodThreat($normalized);
        $mildAction = strtolower(trim($actionSeverity)) === ActionSeverityResolver::MILD;

        // Mild synoptic situation cannot justify Moderate or higher impact.
        if ($weatherSeverity === WeatherSeverityClassifier::MILD && $rank >= $this->rankFromLevel(CropImpactLevels::MODERATE)) {
            $rank = min($rank, $this->rankFromLevel(CropImpactLevels::LOW));
        }

        // Elevated-only weather caps at Moderate unless extreme hazard flags lift further.
        if ($weatherSeverity === WeatherSeverityClassifier::ELEVATED && $rank >= $this->rankFromLevel(CropImpactLevels::HIGH)) {
            $rank = min($rank, $this->rankFromLevel(CropImpactLevels::MODERATE));
        }

        // Strong weather tier required before showing High crop impact without explicit extreme hazard.
        if ($rank >= $this->rankFromLevel(CropImpactLevels::HIGH)
            && ! in_array($weatherSeverity, [WeatherSeverityClassifier::STRONG, WeatherSeverityClassifier::SEVERE], true)
            && ! $extreme) {
            $rank = min($rank, $this->rankFromLevel(CropImpactLevels::MODERATE));
        }

        // Severe impact label reserved for Severe-class weather or highest flood tiers.
        if ($rank >= $this->rankFromLevel(CropImpactLevels::SEVERE)
            && $weatherSeverity !== WeatherSeverityClassifier::SEVERE
            && ! in_array($flood, ['high', 'critical'], true)) {
            $rank = min($rank, $this->rankFromLevel(CropImpactLevels::HIGH));
        }

        // Monitor / delay / adjust only → loss band stays at Low (3–6%) maximum.
        if ($mildAction) {
            $rank = min($rank, $this->rankFromLevel(CropImpactLevels::LOW));
        }

        // Low flood + calm scenario: keep messaging at Low for mild operational disruption examples.
        if ($flood === 'low'
            && $weatherSeverity === WeatherSeverityClassifier::MILD
            && $mildAction
            && ! $extreme) {
            $rank = $this->rankFromLevel(CropImpactLevels::LOW);
        }

        // Moderate flood: Severe wording only when forecast classification is Severe-class.
        if ($flood === 'moderate' && $rank >= $this->rankFromLevel(CropImpactLevels::SEVERE)) {
            if ($weatherSeverity !== WeatherSeverityClassifier::SEVERE && ! $extreme) {
                $rank = $this->rankFromLevel(CropImpactLevels::HIGH);
            }
        }

        return $this->levelFromRank($rank);
    }

    /**
     * Non-bypassable final gate: Flood Low + no measurable extreme hazard → impact Low, loss 3–6%, Low-only outlook.
     * Runs after {@see finalize()} and replaces outlook generation for that path.
     *
     * @param  array<string, mixed>  $normalized
     * @return array{
     *     crop_impact_level: string,
     *     crop_impact_label: string,
     *     possible_loss_range: string,
     *     three_day_outlook: string,
     *     hard_capped_low_flood: bool
     * }
     */
    public function validateAdvisoryFinal(
        User $user,
        string $candidateImpactLevel,
        string $floodRiskNormalized,
        array $normalized,
        string $weatherSeverity,
        LowFloodEscalationGate $gate,
        OutlookTextGenerator $outlookGenerator,
    ): array {
        $flood = strtolower(trim($floodRiskNormalized));
        if ($flood === 'unknown') {
            $flood = 'low';
        }

        if ($flood === 'low' && ! $gate->allowsEscalationAboveLow($user, $normalized, $weatherSeverity)) {
            $lvl = CropImpactLevels::LOW;

            return [
                'crop_impact_level' => $lvl,
                'crop_impact_label' => CropImpactLevels::label($lvl),
                'possible_loss_range' => CropImpactLevels::possibleLossRange($lvl),
                'three_day_outlook' => $outlookGenerator->generateLowSeverityOutlook(),
                'hard_capped_low_flood' => true,
            ];
        }

        $lvl = strtolower(trim($candidateImpactLevel));
        $text = $outlookGenerator->generate($candidateImpactLevel);
        $text = $this->enforceOutlookAllowedForLevel($candidateImpactLevel, $text, $outlookGenerator);

        return [
            'crop_impact_level' => $lvl,
            'crop_impact_label' => CropImpactLevels::label($lvl),
            'possible_loss_range' => CropImpactLevels::possibleLossRange($lvl),
            'three_day_outlook' => $text,
            'hard_capped_low_flood' => false,
        ];
    }

    /**
     * Strip impossible wording if any upstream bug leaked stronger language into a lower tier.
     */
    public function enforceOutlookAllowedForLevel(string $cropImpactLevel, string $sentence, OutlookTextGenerator $generator): string
    {
        $lvl = strtolower(trim($cropImpactLevel));
        $t = strtolower($sentence);

        $strongPhrases = [
            'moderate crop stress',
            'significant stress',
            'serious impact',
            'strong damage risk',
            'major damage risk',
            'serious field disruption',
            'substantial crop stress',
            'repeated rainfall may raise',
            'continued wet conditions may interrupt sensitive field work',
            'strong weather stress',
            'field damage',
            'operational and field damage',
            'high risk',
        ];

        if (in_array($lvl, [CropImpactLevels::MINIMAL, CropImpactLevels::LOW], true)) {
            foreach ($strongPhrases as $phrase) {
                if (str_contains($t, $phrase)) {
                    return $lvl === CropImpactLevels::LOW
                        ? $generator->generateLowSeverityOutlook()
                        : $generator->generate($cropImpactLevel);
                }
            }
        }

        $highOnly = [
            'heavy rainfall may disrupt field activity',
            'strong weather stress may increase',
            'extreme weather conditions',
            'severe rain and environmental pressure',
        ];

        if (in_array($lvl, [
            CropImpactLevels::MINIMAL,
            CropImpactLevels::LOW,
            CropImpactLevels::MODERATE,
        ], true)) {
            foreach ($highOnly as $phrase) {
                if (str_contains($t, $phrase)) {
                    return $lvl === CropImpactLevels::LOW
                        ? $generator->generateLowSeverityOutlook()
                        : $generator->generate($cropImpactLevel);
                }
            }
        }

        return $sentence;
    }

    private function rankFromLevel(string $level): int
    {
        return match (strtolower(trim($level))) {
            CropImpactLevels::MINIMAL => 0,
            CropImpactLevels::LOW => 1,
            CropImpactLevels::MODERATE => 2,
            CropImpactLevels::HIGH => 3,
            CropImpactLevels::SEVERE => 4,
            default => 1,
        };
    }

    private function levelFromRank(int $rank): string
    {
        return match (max(0, min(4, $rank))) {
            0 => CropImpactLevels::MINIMAL,
            1 => CropImpactLevels::LOW,
            2 => CropImpactLevels::MODERATE,
            3 => CropImpactLevels::HIGH,
            default => CropImpactLevels::SEVERE,
        };
    }
}
