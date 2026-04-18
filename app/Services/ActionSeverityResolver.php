<?php

namespace App\Services;

use App\Models\User;

/**
 * Needed farm action tier: mild | medium | strong | emergency.
 * Derived from classified weather plus conservative farm/stage context — not from free-text keywords.
 */
final class ActionSeverityResolver
{
    public const MILD = 'mild';

    public const MEDIUM = 'medium';

    public const STRONG = 'strong';

    public const EMERGENCY = 'emergency';

    /**
     * @param  array<string, mixed>  $normalized  From {@see ForecastInputNormalizer}
     */
    public function resolve(string $weatherSeverity, User $user, array $normalized): string
    {
        unset($normalized);

        $base = match ($weatherSeverity) {
            WeatherSeverityClassifier::MILD => self::MILD,
            WeatherSeverityClassifier::ELEVATED => self::MEDIUM,
            WeatherSeverityClassifier::STRONG => self::STRONG,
            WeatherSeverityClassifier::SEVERE => self::EMERGENCY,
            default => self::MILD,
        };

        $fieldBump = $this->fieldNeedsExtraCaution($user, $weatherSeverity);
        $stageBump = $this->stageNeedsExtraCaution($user, $weatherSeverity);

        $tier = $base;
        if ($fieldBump) {
            $tier = $this->bumpActionTier($tier);
        }
        if ($stageBump) {
            $tier = $this->bumpActionTier($tier);
        }

        return $tier;
    }

    /**
     * True when guidance is limited to monitoring, timing shifts, or light moisture management.
     */
    public function isMildActionTier(string $actionSeverity): bool
    {
        return strtolower(trim($actionSeverity)) === self::MILD;
    }

    private function bumpActionTier(string $tier): string
    {
        return match ($tier) {
            self::MILD => self::MEDIUM,
            self::MEDIUM => self::STRONG,
            self::STRONG => self::EMERGENCY,
            default => self::EMERGENCY,
        };
    }

    private function fieldNeedsExtraCaution(User $user, string $weatherSeverity): bool
    {
        if (in_array($weatherSeverity, [WeatherSeverityClassifier::MILD], true)) {
            return false;
        }

        $raw = strtolower(trim((string) ($user->field_condition ?? '')));
        if ($raw === '') {
            return false;
        }

        foreach (['low_lying', 'flood_prone', 'waterlogged', 'water', 'flood'] as $marker) {
            if (str_contains($raw, $marker)) {
                return true;
            }
        }

        return in_array($raw, ['low_lying', 'flood_prone'], true);
    }

    private function stageNeedsExtraCaution(User $user, string $weatherSeverity): bool
    {
        if ($weatherSeverity === WeatherSeverityClassifier::MILD) {
            return false;
        }

        $stage = strtolower(trim((string) ($user->farming_stage ?? '')));
        if ($stage === '') {
            return false;
        }

        return in_array($stage, ['flowering', 'flowering_fruiting', 'harvest', 'harvesting'], true);
    }
}
