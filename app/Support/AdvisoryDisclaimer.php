<?php

namespace App\Support;

/**
 * Canonical disclaimer for all farmer-facing advisory surfaces.
 */
final class AdvisoryDisclaimer
{
    public const TEXT = 'Estimated advisory only. Based on forecast and farm conditions. Not a guaranteed loss value.';

    /** Short line for main farmer-facing advisory cards */
    public const SUPPORT_CARD = 'Advisory only. Based on forecast and farm conditions.';

    public static function supportCard(): string
    {
        return self::SUPPORT_CARD;
    }
}
