<?php

namespace App\Services;

/**
 * Maps internal flood / impact keys to farmer-facing labels and tone hints.
 */
class RiskLabelMapper
{
    /**
     * Flood assessment uses LOW / MODERATE / HIGH; snapshot may use lowercase.
     */
    public function floodDisplayLabel(string $level): string
    {
        return match (strtolower(trim($level))) {
            'high', 'critical' => 'High',
            'moderate' => 'Moderate',
            'low' => 'Low',
            default => 'Unknown',
        };
    }

    /**
     * Practical flood-line action (single sentence), proportional to risk tier.
     */
    public function floodRecommendedAction(string $level): string
    {
        return match (strtolower(trim($level))) {
            'high', 'critical' => 'Move inputs, tools, and equipment to safer ground and open drainage paths.',
            'moderate' => 'Prepare drainage channels and watch low-lying sections of the farm.',
            'low' => 'Keep drainage clear and monitor low spots.',
            default => 'Keep drainage clear and monitor conditions.',
        };
    }

    /**
     * CSS / tone token for crop impact cards.
     */
    public function cropImpactTone(string $impactLevel): string
    {
        return match (strtolower(trim($impactLevel))) {
            'severe' => 'severe',
            'high' => 'high',
            'moderate' => 'moderate',
            'low' => 'low',
            'minimal' => 'minimal',
            default => 'unknown',
        };
    }

    /**
     * Tone for 3-day outlook text (heuristic from keywords).
     */
    public function outlookTone(string $outlook): string
    {
        $t = strtolower($outlook);
        if ($t === '' || str_contains($t, 'unable') || str_contains($t, 'missing')) {
            return 'unknown';
        }
        if (str_contains($t, 'strong') || str_contains($t, 'flooding') || str_contains($t, 'typhoon') || str_contains($t, 'severe')) {
            return 'high';
        }
        if (str_contains($t, 'repeated') || str_contains($t, 'waterlog') || str_contains($t, 'stress')) {
            return 'moderate';
        }

        return 'low';
    }
}
