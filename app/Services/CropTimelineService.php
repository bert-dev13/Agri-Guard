<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class CropTimelineService
{
    /**
     * Growth stages with configured durations (calendar / config). Excludes terminal {@see completed}.
     */
    public const GROWTH_STAGE_ORDER = ['planting', 'early_growth', 'vegetative', 'flowering', 'harvest'];

    /**
     * Full lifecycle order including terminal completed state (no duration in config).
     */
    public const STAGE_ORDER = ['planting', 'early_growth', 'vegetative', 'flowering', 'harvest', 'completed'];

    public const STAGE_LABELS = [
        'planting' => 'Planting',
        'early_growth' => 'Early Growth',
        'vegetative' => 'Vegetative',
        'flowering' => 'Flowering',
        'harvest' => 'Harvest',
        'completed' => 'Completed',
    ];

    /**
     * @return array<string, string> stage_key => label for forms (excludes completed; use “Mark harvested” for that).
     */
    public static function growthStageLabels(): array
    {
        $out = [];
        foreach (self::GROWTH_STAGE_ORDER as $key) {
            $out[$key] = self::STAGE_LABELS[$key] ?? $key;
        }

        return $out;
    }

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
            foreach (self::GROWTH_STAGE_ORDER as $stage) {
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
        foreach (self::GROWTH_STAGE_ORDER as $key) {
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
            'key' => 'completed',
            'label' => self::STAGE_LABELS['completed'] ?? 'Completed',
            'days_since_planting' => $elapsed,
            'days_until_planting' => null,
            'has_planting_date' => true,
        ];
    }

    /**
     * Expected stage using planting date shifted by offset days.
     *
     * @return array{key: string, label: string, days_since_planting: int|null, days_until_planting: int|null, has_planting_date: bool}
     */
    public function inferExpectedStageFromPlantingWithOffset(User $user, ?array $stageDurations = null, int $offsetDays = 0): array
    {
        $durations = $stageDurations ?? $this->stageDurationsForCrop((string) ($user->crop_type ?? ''));
        $planting = $this->resolvePlantingDate($user);
        if ($planting === null) {
            return [
                'key' => 'planting',
                'label' => self::STAGE_LABELS['planting'] ?? 'Planting',
                'days_since_planting' => null,
                'days_until_planting' => null,
                'has_planting_date' => false,
            ];
        }

        $anchor = $planting->copy()->addDays($offsetDays);
        $today = now()->startOfDay();
        if ($anchor->isFuture()) {
            return [
                'key' => 'planting',
                'label' => self::STAGE_LABELS['planting'] ?? 'Planting',
                'days_since_planting' => 0,
                'days_until_planting' => (int) $today->diffInDays($anchor, false),
                'has_planting_date' => true,
            ];
        }

        $elapsed = max(0, (int) $anchor->diffInDays($today, false));
        $cumulative = 0;
        foreach (self::GROWTH_STAGE_ORDER as $key) {
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
            'key' => 'completed',
            'label' => self::STAGE_LABELS['completed'] ?? 'Completed',
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
        if ($stageKey === 'completed') {
            return 'Cycle completed';
        }
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

    /**
     * True when calendar elapsed time has reached or passed the end of the harvest stage
     * (first day of terminal "completed" / cycle wrap-up).
     */
    public function shouldAutoCompleteCropCycle(User $user, ?array $stageDurations = null, int $offsetDays = 0): bool
    {
        $durations = $stageDurations ?? $this->stageDurationsForCrop((string) ($user->crop_type ?? ''));
        $planting = $this->resolvePlantingDate($user);
        if ($planting === null) {
            return false;
        }

        $anchor = $planting->copy()->addDays($offsetDays);
        if ($anchor->isFuture()) {
            return false;
        }

        $totalGrowthDays = 0;
        foreach (self::GROWTH_STAGE_ORDER as $key) {
            $totalGrowthDays += max(1, (int) ($durations[$key] ?? 14));
        }

        $elapsed = max(0, (int) $anchor->diffInDays(now()->startOfDay(), false));

        return $elapsed >= $totalGrowthDays;
    }

    /**
     * Progress within the active growth stage (user-selected when provided).
     *
     * When the user selects a stage ahead of the calendar-from-planting estimate, raw day-within-stage
     * would stay near zero; progress is then mapped using elapsed days through the stage's calendar
     * window (start of stage through end of stage) so the bar reflects real time since planting.
     *
     * @param  array<string, int>|null  $stageDurations
     * @return array{
     *   progress_percent: int,
     *   days_elapsed_in_stage: int,
     *   stage_duration_days: int,
     *   days_remaining_to_next_stage: int|null,
     *   next_stage_start_date: string|null
     * }
     */
    public function computeStageProgressFromPlanting(User $user, ?array $stageDurations = null, int $offsetDays = 0, ?string $activeStageKey = null): array
    {
        $durations = $stageDurations ?? $this->stageDurationsForCrop((string) ($user->crop_type ?? ''));
        $expected = $this->inferExpectedStageFromPlantingWithOffset($user, $durations, $offsetDays);
        $currentStageKey = $activeStageKey !== null && trim($activeStageKey) !== ''
            ? $this->normalizeStageKey($activeStageKey)
            : $expected['key'];

        if ($currentStageKey === 'completed') {
            return [
                'progress_percent' => 100,
                'days_elapsed_in_stage' => 0,
                'stage_duration_days' => 0,
                'days_remaining_to_next_stage' => null,
                'next_stage_start_date' => null,
            ];
        }

        $currentDuration = max(1, (int) ($durations[$currentStageKey] ?? 14));

        $planting = $this->resolvePlantingDate($user);
        if ($planting === null) {
            return [
                'progress_percent' => 0,
                'days_elapsed_in_stage' => 0,
                'stage_duration_days' => $currentDuration,
                'days_remaining_to_next_stage' => null,
                'next_stage_start_date' => null,
            ];
        }
        $planting = $planting->addDays($offsetDays);
        $today = now()->startOfDay();
        if ($planting->isFuture()) {
            return [
                'progress_percent' => 0,
                'days_elapsed_in_stage' => 0,
                'stage_duration_days' => $currentDuration,
                'days_remaining_to_next_stage' => (int) $today->diffInDays($planting, false),
                'next_stage_start_date' => $planting->format('Y-m-d'),
            ];
        }

        $elapsedSincePlanting = max(0, (int) $planting->diffInDays($today, false));
        $currentIndex = $this->stageIndex($currentStageKey);

        $daysBeforeCurrent = 0;
        for ($i = 0; $i < $currentIndex; $i++) {
            $sk = self::STAGE_ORDER[$i];
            if ($sk === 'completed') {
                break;
            }
            $daysBeforeCurrent += max(1, (int) ($durations[$sk] ?? 14));
        }

        $expectedIndex = $this->stageIndex($expected['key']);
        $daysElapsedInStage = max(0, $elapsedSincePlanting - $daysBeforeCurrent);
        $isManualOverride = $activeStageKey !== null && trim($activeStageKey) !== '' && $currentStageKey !== $expected['key'];

        [$bandMin, $bandMax] = $this->stageProgressBand($currentStageKey);
        $bandSpan = max(1, $bandMax - $bandMin);
        $stageFraction = min(1.0, max(0.0, ($daysElapsedInStage + 1) / $currentDuration));
        $progressPercent = $bandMin + (int) floor($stageFraction * $bandSpan);

        if ($isManualOverride) {
            // Manual stage updates must immediately move the progress into the selected stage range.
            $progressPercent = max($progressPercent, $this->manualStageBaseProgress($currentStageKey));

            // If user selected a stage ahead of calendar, avoid low percentages from planting-date lag.
            if ($currentIndex > $expectedIndex) {
                $progressPercent = max($progressPercent, min($bandMax, $bandMin + 1));
            }
        }

        $progressPercent = max($bandMin, min($bandMax, $progressPercent));
        if ($currentStageKey === 'harvest') {
            $progressPercent = min(99, $progressPercent);
        }

        $relativeWithinBand = ($progressPercent - $bandMin) / max(1, $bandSpan);
        $daysElapsedInStage = min(
            $currentDuration,
            max(0, (int) round($relativeWithinBand * $currentDuration))
        );

        $daysRemainingToNext = null;
        $nextStageStart = null;

        $nextStageStartDate = $planting->copy()->addDays($daysBeforeCurrent + $currentDuration);
        $nextStageStart = $nextStageStartDate->format('Y-m-d');
        $daysRemainingToNext = max(0, (int) $today->diffInDays($nextStageStartDate, false));

        return [
            'progress_percent' => $progressPercent,
            'days_elapsed_in_stage' => $daysElapsedInStage,
            'stage_duration_days' => $currentDuration,
            'days_remaining_to_next_stage' => $daysRemainingToNext,
            'next_stage_start_date' => $nextStageStart,
        ];
    }

    /**
     * Build a deterministic, sequential timeline from one canonical source.
     *
     * @param  array<string, int>|null  $stageDurations
     * @return array<int, array<string, mixed>>
     */
    public function buildSequentialTimelineFromPlanting(User $user, ?array $stageDurations = null, int $offsetDays = 0, ?string $activeStageKey = null): array
    {
        $durations = $stageDurations ?? $this->stageDurationsForCrop((string) ($user->crop_type ?? ''));
        $expected = $this->inferExpectedStageFromPlantingWithOffset($user, $durations, $offsetDays);
        $currentIndex = $activeStageKey !== null && trim($activeStageKey) !== ''
            ? $this->stageIndex($activeStageKey)
            : $this->stageIndex($expected['key']);
        $planting = $this->resolvePlantingDate($user);
        $anchor = ($planting ?? now()->startOfDay())->addDays($offsetDays);

        $timeline = [];
        $dayCursor = 0;
        foreach (self::GROWTH_STAGE_ORDER as $index => $stageKey) {
            $start = $anchor->copy()->addDays($dayCursor);
            $status = $index < $currentIndex
                ? 'completed'
                : ($index === $currentIndex ? 'current' : 'upcoming');

            $timeline[] = [
                'stage' => self::STAGE_LABELS[$stageKey] ?? ucfirst(str_replace('_', ' ', $stageKey)),
                'target_date' => $start->format('Y-m-d'),
                'estimated_day_count' => $dayCursor,
                'status' => $status,
                'date_range_line' => $this->formatStageTypicalWindow(
                    self::STAGE_LABELS[$stageKey] ?? ucfirst(str_replace('_', ' ', $stageKey)),
                    $start->format('Y-m-d'),
                    (string) ($user->crop_type ?? '')
                ),
            ];

            $dayCursor += max(1, (int) ($durations[$stageKey] ?? 14));
        }

        $completedIndex = count(self::GROWTH_STAGE_ORDER);
        $startCompleted = $anchor->copy()->addDays($dayCursor);
        $timeline[] = [
            'stage' => self::STAGE_LABELS['completed'],
            'target_date' => $startCompleted->format('Y-m-d'),
            'estimated_day_count' => $dayCursor,
            'status' => $currentIndex === $completedIndex ? 'current' : 'upcoming',
            'date_range_line' => $this->formatStageTypicalWindow(
                'Completed',
                $startCompleted->format('Y-m-d'),
                (string) ($user->crop_type ?? '')
            ),
        ];

        return $timeline;
    }

    private function resolvePlantingDate(User $user): ?Carbon
    {
        $attrs = $user->getAttributes();
        $rawPlanting = $attrs['planting_date'] ?? null;
        if (($rawPlanting === null || $rawPlanting === '') && $user->planting_date === null) {
            return null;
        }

        try {
            return Carbon::parse($rawPlanting ?? $user->planting_date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0:int,1:int}
     */
    private function stageProgressBand(string $stageKey): array
    {
        $key = $this->normalizeStageKey($stageKey);

        return match ($key) {
            'planting' => [0, 5],
            'early_growth' => [5, 25],
            'vegetative' => [25, 60],
            'flowering' => [60, 85],
            'harvest' => [85, 99],
            'completed' => [100, 100],
            default => [0, 100],
        };
    }

    private function manualStageBaseProgress(string $stageKey): int
    {
        $key = $this->normalizeStageKey($stageKey);

        return match ($key) {
            'planting' => 2,
            'early_growth' => 12,
            'vegetative' => 40,
            'flowering' => 70,
            'harvest' => 92,
            'completed' => 100,
            default => 10,
        };
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
