<?php

namespace App\Support;

/**
 * Shared thresholds for “moderate field stress” — used by rank assignment and output validation
 * so tier, outlook, and loss bands stay aligned.
 */
final class AdvisorySeverityRules
{
    /**
     * Conditions that justify Moderate impact (6–10%) when flood risk is only low/unknown.
     * Borderline POP-only or light breeze signals must NOT pass this gate.
     */
    public static function moderateFieldStressEvidence(
        float $rainSignalMm,
        float $effectivePop,
        float $effectiveWind,
        float $heat
    ): bool {
        if ($rainSignalMm >= 42.0) {
            return true;
        }
        if ($effectiveWind >= 30.0) {
            return true;
        }
        if ($heat >= 35.0 && $rainSignalMm < 14.0) {
            return true;
        }
        if ($rainSignalMm >= 22.0 && $effectivePop >= 64.0) {
            return true;
        }
        if ($rainSignalMm >= 28.0 && $effectivePop >= 52.0) {
            return true;
        }

        return false;
    }
}
