<?php

namespace App\Support;

/**
 * Canonical crop impact tiers for AGRIGUARD (decision-support only).
 * Possible loss values are advisory ranges, not guaranteed outcomes.
 */
final class CropImpactLevels
{
    public const MINIMAL = 'minimal';

    public const LOW = 'low';

    public const MODERATE = 'moderate';

    public const HIGH = 'high';

    public const SEVERE = 'severe';

    /**
     * @return array<string, array{label: string, loss_range: string}>
     */
    public static function catalog(): array
    {
        return [
            self::MINIMAL => ['label' => 'Minimal', 'loss_range' => '0–3%'],
            self::LOW => ['label' => 'Low', 'loss_range' => '3–6%'],
            self::MODERATE => ['label' => 'Moderate', 'loss_range' => '6–10%'],
            self::HIGH => ['label' => 'High', 'loss_range' => '10–15%'],
            self::SEVERE => ['label' => 'Severe', 'loss_range' => '15–25%'],
        ];
    }

    public static function label(string $level): string
    {
        return self::catalog()[$level]['label'] ?? ucfirst($level);
    }

    public static function possibleLossRange(string $level): string
    {
        return self::catalog()[$level]['loss_range'] ?? '—';
    }
}
