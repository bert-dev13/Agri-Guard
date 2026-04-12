<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class CropTimelineService
{
    /**
     * Canonical five growth stages (DB values, snake_case).
     * Order: Planting → Early Growth → Vegetative → Flowering → Harvest
     */
    public const STAGE_ORDER = ['planting', 'early_growth', 'vegetative', 'flowering', 'harvest'];

    public const STAGE_LABELS = [
        'planting' => 'Planting',
        'early_growth' => 'Early Growth',
        'vegetative' => 'Vegetative',
        'flowering' => 'Flowering',
        'harvest' => 'Harvest',
    ];

    /** @var array<string, string> Legacy / alternate keys → canonical */
    private const LEGACY_TO_CANONICAL = [
        'land_preparation' => 'planting',
        'growing' => 'vegetative',
        'flowering_fruiting' => 'flowering',
        'harvesting' => 'harvest',
    ];

    /**
     * Shift ISO date strings on timeline rows by offset days (user correction).
     *
     * @param  array<int, array{stage?: string, target_date?: string, estimated_day_count?: int, status?: string}>  $timeline
     * @return array<int, array<string, mixed>>
     */
    public function applyOffsetToTimeline(array $timeline, int $offsetDays): array
    {
        if ($offsetDays === 0) {
            return $timeline;
        }

        $out = [];
        foreach ($timeline as $row) {
            if (! is_array($row)) {
                continue;
            }
            $dateStr = trim((string) ($row['target_date'] ?? ''));
            try {
                $d = Carbon::parse($dateStr)->addDays($offsetDays);
                $row['target_date'] = $d->format('Y-m-d');
            } catch (\Throwable) {
                // keep original
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Map configured crop type (e.g. "Rice") to config key.
     */
    public function cropConfigKey(string $cropType): string
    {
        $k = strtolower(trim($cropType));
        $crops = config('crop_timelines.crops', []);

        if (isset($crops[$k])) {
            return $k;
        }

        return match ($k) {
            'rice' => 'rice',
            'corn', 'maize' => 'corn',
            default => (string) (config('crop_timelines.default_crop_key') ?? 'rice'),
        };
    }

    /**
     * @return array<string, int>
     */
    public function stageDurationsForCrop(string $cropType): array
    {
        $key = $this->cropConfigKey($cropType);
        $fromConfig = config("crop_timelines.crops.{$key}", []);

        if (is_array($fromConfig) && $fromConfig !== []) {
            $out = [];
            foreach (self::STAGE_ORDER as $stage) {
                $out[$stage] = max(1, (int) ($fromConfig[$stage] ?? 14));
            }

            return $out;
        }

        return $this->defaultStageDurationsFallback($cropType);
    }

    /** @return array<string, int> */
    private function defaultStageDurationsFallback(string $cropType): array
    {
        $isCorn = strcasecmp(trim($cropType), 'Corn') === 0;

        return $isCorn
            ? [
                'planting' => 8,
                'early_growth' => 16,
                'vegetative' => 20,
                'flowering' => 18,
                'harvest' => 30,
            ]
            : [
                'planting' => 7,
                'early_growth' => 14,
                'vegetative' => 21,
                'flowering' => 24,
                'harvest' => 28,
            ];
    }

    /**
     * Calendar-based expected stage from planting date + typical durations (not AI).
     *
     * @return array{key: string, label: string, days_since_planting: int|null, days_until_planting: int|null, has_planting_date: bool}
     */
    public function inferExpectedStageFromPlanting(User $user, ?array $stageDurations = null): array
    {
        $durations = $stageDurations ?? $this->stageDurationsForCrop((string) ($user->crop_type ?? ''));

        $attrs = $user->getAttributes();
        $rawPlanting = $attrs['planting_date'] ?? null;
        if (($rawPlanting === null || $rawPlanting === '') && $user->planting_date === null) {
            return [
                'key' => 'planting',
                'label' => self::STAGE_LABELS['planting'] ?? 'Planting',
                'days_since_planting' => null,
                'days_until_planting' => null,
                'has_planting_date' => false,
            ];
        }

        $planting = Carbon::parse($rawPlanting ?? $user->planting_date)->startOfDay();
        $today = now()->startOfDay();

        if ($planting->isFuture()) {
            return [
                'key' => 'planting',
                'label' => self::STAGE_LABELS['planting'] ?? 'Planting',
                'days_since_planting' => 0,
                'days_until_planting' => (int) $today->diffInDays($planting, false),
                'has_planting_date' => true,
            ];
        }

        $elapsed = max(0, (int) $planting->diffInDays($today, false));

        $cumulative = 0;
        foreach (self::STAGE_ORDER as $key) {
            $dur = max(1, (int) ($durations[$key] ?? 14));
            if ($elapsed < $cumulative + $dur) {
                return [
                    'key' => $key,
                    'label' => self::STAGE_LABELS[$key] ?? 'Unknown',
                    'days_since_planting' => $elapsed,
                    'days_until_planting' => null,
                    'has_planting_date' => true,
                ];
            }
            $cumulative += $dur;
        }

        return [
            'key' => 'harvest',
            'label' => self::STAGE_LABELS['harvest'] ?? 'Harvest',
            'days_since_planting' => $elapsed,
            'days_until_planting' => null,
            'has_planting_date' => true,
        ];
    }

    /**
     * Resolve a timeline row's `stage` display string to a canonical stage key.
     */
    public function timelineStageRowToKey(string $stageLabel): string
    {
        $label = trim($stageLabel);
        if ($label === '') {
            return 'planting';
        }

        foreach (self::STAGE_LABELS as $key => $l) {
            if (strcasecmp($label, $l) === 0) {
                return $key;
            }
        }

        $legacyLabels = [
            'Land Preparation' => 'planting',
            'Growing' => 'vegetative',
            'Growing Stage' => 'vegetative',
            'Flowering / Fruiting' => 'flowering',
            'Flowering & fruiting' => 'flowering',
            'Harvesting' => 'harvest',
        ];
        if (isset($legacyLabels[$label])) {
            return $legacyLabels[$label];
        }

        $lower = strtolower($label);
        if (str_contains($lower, 'harvest')) {
            return 'harvest';
        }
        if (str_contains($lower, 'flower') || str_contains($lower, 'fruit')) {
            return 'flowering';
        }
        if (str_contains($lower, 'vegetative') || (str_contains($lower, 'growing') && ! str_contains($lower, 'early'))) {
            return 'vegetative';
        }
        if (str_contains($lower, 'early')) {
            return 'early_growth';
        }

        return $this->normalizeStageKey($label);
    }

    /**
     * Re-mark timeline rows as completed / current / upcoming using the calendar-derived stage.
     *
     * @param  array<int, array<string, mixed>>  $timeline
     * @return array<int, array<string, mixed>>
     */
    public function applyCalendarStatusToTimeline(array $timeline, string $expectedStageKey): array
    {
        $expectedKey = $this->normalizeStageKey($expectedStageKey);
        $expectedIndex = $this->stageIndex($expectedKey);
        $out = [];

        foreach ($timeline as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rowKey = $this->timelineStageRowToKey((string) ($row['stage'] ?? ''));
            $idx = $this->stageIndex($rowKey);
            if ($idx < $expectedIndex) {
                $row['status'] = 'completed';
            } elseif ($idx === $expectedIndex) {
                $row['status'] = 'current';
            } else {
                $row['status'] = 'upcoming';
            }
            $out[] = $row;
        }

        return $out;
    }

    public function stageIndex(?string $stageKey): int
    {
        $k = $this->normalizeStageKey((string) $stageKey);
        $i = array_search($k, self::STAGE_ORDER, true);

        return $i === false ? 0 : (int) $i;
    }

    public function normalizeStageKey(string $stage): string
    {
        $stage = strtolower(trim($stage));
        if ($stage === '' || $stage === '0') {
            return 'planting';
        }
        if (isset(self::LEGACY_TO_CANONICAL[$stage])) {
            return self::LEGACY_TO_CANONICAL[$stage];
        }

        return in_array($stage, self::STAGE_ORDER, true) ? $stage : 'planting';
    }

    public function displayLabel(?string $stored): string
    {
        if ($stored === null || trim((string) $stored) === '') {
            return 'Not set';
        }
        $k = $this->normalizeStageKey((string) $stored);

        return self::STAGE_LABELS[$k] ?? ucfirst(str_replace('_', ' ', (string) $stored));
    }

    /**
     * @return 'behind'|'match'|'ahead'
     */
    public function compareActualToExpected(string $actualKey, string $expectedKey): string
    {
        $a = $this->stageIndex($actualKey);
        $e = $this->stageIndex($expectedKey);
        if ($a < $e) {
            return 'behind';
        }
        if ($a > $e) {
            return 'ahead';
        }

        return 'match';
    }

    /**
     * @return 'fast'|'normal'|'slow'
     */
    public function growthSpeed(string $comparison, ?string $realityCheck, int $offsetDays): string
    {
        if ($realityCheck === 'behind' || $comparison === 'behind' || $offsetDays >= 5) {
            return 'slow';
        }
        if ($comparison === 'ahead' || $offsetDays <= -3) {
            return 'fast';
        }

        return 'normal';
    }

    /**
     * Symmetric calendar band around a single midpoint (e.g. a forecast milestone).
     *
     * Not suitable for crop stage rows where {@see formatStageTypicalWindow} should be used:
     * subtracting days before stage start incorrectly implies a planting date earlier than saved.
     */
    public function formatDateRange(string $isoDate, int $plusMinusDays = 3): string
    {
        try {
            $center = Carbon::parse($isoDate)->startOfDay();
        } catch (\Throwable) {
            return $isoDate;
        }

        $start = $center->copy()->subDays($plusMinusDays);
        $end = $center->copy()->addDays($plusMinusDays);

        return 'Expected: '.$this->formatWindowBetween($start, $end);
    }

    /**
     * Typical calendar window for one growth stage: from the stage start date through
     * (duration − 1) days, using configured lengths for the crop. Never moves the start
     * earlier than {@see $isoStageStartDate} (planting date remains the anchor for Planting).
     *
     * @param  string  $stageLabel  Timeline row label, e.g. "Planting", "Early Growth"
     */
    public function formatStageTypicalWindow(string $stageLabel, string $isoStageStartDate, string $cropType): string
    {
        $isoStageStartDate = trim($isoStageStartDate);
        if ($isoStageStartDate === '') {
            return '—';
        }

        try {
            $start = Carbon::parse($isoStageStartDate)->startOfDay();
        } catch (\Throwable) {
            return $isoStageStartDate;
        }

        $stageKey = $this->timelineStageRowToKey($stageLabel);
        $durations = $this->stageDurationsForCrop($cropType);
        $duration = max(1, (int) ($durations[$stageKey] ?? 14));
        $end = $start->copy()->addDays(max(0, $duration - 1));

        return 'Typical: '.$this->formatWindowBetween($start, $end);
    }

    /**
     * Two calendar dates with an en dash, e.g. "Mar 25 – Mar 31" or "Mar 27 – Apr 2".
     */
    private function formatWindowBetween(Carbon $start, Carbon $end): string
    {
        if ($start->equalTo($end)) {
            return $start->format('M j');
        }

        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->format('M j').' – '.$end->format('M j');
        }

        return $start->format('M j').' – '.$end->format('M j');
    }

    public function nextStageDaysRemaining(?string $nextTargetIso): ?int
    {
        if ($nextTargetIso === null || $nextTargetIso === '') {
            return null;
        }
        try {
            $target = Carbon::parse($nextTargetIso)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return max(0, now()->startOfDay()->diffInDays($target, false));
    }

    public function humanAdjustmentLabel(string $rawReason, string $legacyLabel): string
    {
        $t = strtolower($legacyLabel.' '.$rawReason);
        if (str_contains($t, 'delay') || str_contains($t, 'slow') || str_contains($t, 'slightly delayed')) {
            return 'Growth is slower than expected';
        }
        if (str_contains($t, 'faster') || str_contains($t, 'ahead')) {
            return 'Growing faster than typical for this season';
        }

        return 'On track with the current estimate';
    }

    /**
     * @param  array<string, mixed>|null  $weatherContext
     * @return array{label: string, level: 'low'|'medium'|'high', tooltip: string}
     */
    public function confidenceDisplay(?array $weatherContext = null): array
    {
        $isLow = false;
        if ($weatherContext !== null) {
            $condition = strtolower(trim((string) ($weatherContext['condition'] ?? '')));
            $temp = $weatherContext['temperature'] ?? null;
            if ($condition === 'unknown' || $temp === null) {
                $isLow = true;
            }
        }

        return [
            'label' => $isLow ? 'Low' : 'Medium',
            'level' => $isLow ? 'low' : 'medium',
            'tooltip' => 'Based on weather data and standard growth patterns for your crop. Your updates improve accuracy.',
        ];
    }
}
