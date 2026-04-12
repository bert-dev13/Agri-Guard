<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AiAdvisory\AiAdvisoryService;
use App\Services\CropTimelineService;
use App\Services\WeatherAdvisoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CropProgressController extends Controller
{
    /** @deprecated Use CropTimelineService::STAGE_LABELS */
    private const TIMELINE_STAGES = CropTimelineService::STAGE_LABELS;

    public function index(
        WeatherAdvisoryService $weatherAdvisoryService,
        AiAdvisoryService $aiAdvisoryService,
        CropTimelineService $timelineService,
    ): View {
        /** @var User $user */
        $user = Auth::user();
        $weatherContext = $this->buildWeatherContext($user, $weatherAdvisoryService);
        $stageInsights = $this->generateStageAdvice($user, $weatherContext, $aiAdvisoryService);

        $rec = $stageInsights['recommendation'];
        $offsetDays = (int) ($user->crop_timeline_offset_days ?? 0);

        $durations = $timelineService->stageDurationsForCrop((string) ($user->crop_type ?? ''));
        $expected = $timelineService->inferExpectedStageFromPlanting($user, $durations);

        if (! empty($rec['timeline']) && is_array($rec['timeline'])) {
            $rec['timeline'] = $timelineService->applyOffsetToTimeline($rec['timeline'], $offsetDays);
            foreach ($rec['timeline'] as &$row) {
                if (is_array($row)) {
                    $row['date_range_line'] = $timelineService->formatStageTypicalWindow(
                        (string) ($row['stage'] ?? ''),
                        (string) ($row['target_date'] ?? ''),
                        (string) ($user->crop_type ?? '')
                    );
                }
            }
            unset($row);
            $rec['timeline'] = $timelineService->applyCalendarStatusToTimeline($rec['timeline'], $expected['key']);
        }

        $nextItem = $this->resolveNextStage($rec['timeline'] ?? []);
        if ($nextItem !== null) {
            $rec['next_stage'] = $nextItem['stage'] ?? ($rec['next_stage'] ?? null);
            $rec['next_stage_target_date'] = $nextItem['target_date'] ?? ($rec['next_stage_target_date'] ?? null);
        }

        $rec['days_remaining'] = $timelineService->nextStageDaysRemaining($rec['next_stage_target_date'] ?? null)
            ?? (is_numeric($rec['days_remaining'] ?? null) ? (int) $rec['days_remaining'] : null);
        $actualKey = $timelineService->normalizeStageKey((string) ($user->farming_stage ?? 'planting'));
        $comparison = $timelineService->compareActualToExpected($actualKey, $expected['key']);

        $humanAdjustment = $timelineService->humanAdjustmentLabel(
            (string) ($rec['timeline_adjustment_reason'] ?? ''),
            (string) ($rec['timeline_adjustment_label'] ?? 'On schedule')
        );

        if ($comparison === 'behind') {
            $humanAdjustment = 'Growth is slower than expected';
        } elseif ($comparison === 'ahead' && $humanAdjustment === 'On track with the current estimate') {
            $humanAdjustment = 'Growing faster than typical for this season';
        }

        $growthSpeed = $timelineService->growthSpeed(
            $comparison,
            $user->crop_stage_reality_check,
            $offsetDays
        );

        $confidence = $timelineService->confidenceDisplay($weatherContext);
        $confidenceIsLow = ($confidence['level'] ?? 'medium') === 'low';

        $realityQuestionStage = $this->resolveRealityQuestionStageLabel($user, $rec);
        $questionStageKey = $this->normalizeStageLabelToKey($realityQuestionStage);

        $nextStageDateRange = ! empty($rec['next_stage_target_date'])
            ? $timelineService->formatStageTypicalWindow(
                (string) ($rec['next_stage'] ?? ''),
                (string) $rec['next_stage_target_date'],
                (string) ($user->crop_type ?? '')
            )
            : null;

        $realityReopened = (bool) session()->pull('reality_check_reopened', false);

        $realityUi = $this->resolveRealityCheckUiState(
            $user,
            $comparison,
            $growthSpeed,
            $confidenceIsLow,
            $questionStageKey,
            $actualKey,
            $realityReopened
        );

        $plantingDateFormatted = $user->planting_date
            ? $user->planting_date->timezone(config('app.timezone'))->format('M j, Y')
            : null;
        $plantingDayLine = ! $expected['has_planting_date']
            ? 'No planting date set — add one in Farm Settings.'
            : (($expected['days_until_planting'] ?? null) !== null && (int) $expected['days_until_planting'] > 0
                ? 'Planting in '.(int) $expected['days_until_planting'].' day'.(((int) $expected['days_until_planting'] === 1) ? '' : 's').' ('.$plantingDateFormatted.').'
                : (($expected['days_since_planting'] ?? null) !== null
                    ? (int) $expected['days_since_planting'].' day'.(((int) $expected['days_since_planting'] === 1) ? '' : 's').' since planting ('.$plantingDateFormatted.').'
                    : 'Planted '.$plantingDateFormatted.'.'));

        return view('user.crop-progress.index', [
            'user' => $user,
            'stages' => self::TIMELINE_STAGES,
            'farm_name' => trim((string) $user->name).' Farm',
            'current_stage' => $expected['key'],
            'current_stage_label' => $expected['label'],
            'manual_stage_label' => $this->stageLabel($user->farming_stage),
            'weather_context' => $weatherContext,
            'recommendation' => $rec,
            'recommendation_failed' => $stageInsights['failed'],
            'timeline' => $rec['timeline'] ?? [],
            'next_stage' => $rec['next_stage'] ?? null,
            'next_stage_target_date' => $rec['next_stage_target_date'] ?? null,
            'days_remaining' => $rec['days_remaining'] ?? null,
            'timeline_adjustment_reason' => $rec['timeline_adjustment_reason'] ?? 'Timeline is based on recent farm weather context.',
            'timeline_adjustment_label' => $humanAdjustment,
            'planting_date_formatted' => $plantingDateFormatted,
            'planting_day_line' => $plantingDayLine,
            'days_since_planting' => $expected['days_since_planting'],
            'days_until_planting' => $expected['days_until_planting'],
            'has_planting_date' => $expected['has_planting_date'],
            'growth_speed' => $growthSpeed,
            'stage_confidence' => $confidence,
            'reality_question_stage' => $realityQuestionStage,
            'crop_timeline_offset_days' => $offsetDays,
            'next_stage_date_range' => $nextStageDateRange,
            'confidence_is_low' => $confidenceIsLow,
            'show_reality_check_form' => $realityUi['show_form'],
            'show_reality_check_card' => $realityUi['show_form'],
        ]);
    }

    public function realityCheck(
        Request $request,
        CropTimelineService $timelineService,
        WeatherAdvisoryService $weatherAdvisoryService,
        AiAdvisoryService $aiAdvisoryService,
    ): RedirectResponse|JsonResponse {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'response' => ['required', 'string', 'in:yes,not_yet'],
        ]);

        $offset = (int) ($user->crop_timeline_offset_days ?? 0);

        if ($validated['response'] === 'yes') {
            $user->crop_stage_reality_check = 'confirmed';
            $user->crop_timeline_offset_days = max(0, $offset - 2);
            $user->reality_check_answered = true;
            $user->reality_check_status = 'confirmed';
            $user->stage_confirmed_at = now();
        } else {
            $user->crop_stage_reality_check = 'behind';
            $user->crop_timeline_offset_days = min(60, $offset + 5);
            $user->reality_check_answered = true;
            $user->reality_check_status = 'delayed';
            $user->stage_confirmed_at = null;
        }

        $user->save();
        $user->refresh();

        $weatherContext = $this->buildWeatherContext($user, $weatherAdvisoryService);
        $stageInsights = $this->generateStageAdvice($user, $weatherContext, $aiAdvisoryService);
        $rec = $stageInsights['recommendation'];
        $offsetDays = (int) ($user->crop_timeline_offset_days ?? 0);
        $durations = $timelineService->stageDurationsForCrop((string) ($user->crop_type ?? ''));
        $expected = $timelineService->inferExpectedStageFromPlanting($user, $durations);
        if (! empty($rec['timeline']) && is_array($rec['timeline'])) {
            $rec['timeline'] = $timelineService->applyOffsetToTimeline($rec['timeline'], $offsetDays);
            $rec['timeline'] = $timelineService->applyCalendarStatusToTimeline($rec['timeline'], $expected['key']);
        }

        $confidence = $timelineService->confidenceDisplay($weatherContext);
        $confidenceIsLow = ($confidence['level'] ?? 'medium') === 'low';
        $actualKey = $timelineService->normalizeStageKey((string) ($user->farming_stage ?? 'planting'));
        $comparison = $timelineService->compareActualToExpected($actualKey, $expected['key']);
        $growthSpeed = $timelineService->growthSpeed($comparison, $user->crop_stage_reality_check, $offsetDays);
        $realityQuestionStage = $this->resolveRealityQuestionStageLabel($user, $rec);
        $questionStageKey = $this->normalizeStageLabelToKey($realityQuestionStage);
        $ui = $this->resolveRealityCheckUiState(
            $user,
            $comparison,
            $growthSpeed,
            $confidenceIsLow,
            $questionStageKey,
            $actualKey,
            false
        );

        if ($this->wantsJsonRealityResponse($request)) {
            return response()->json([
                'ok' => true,
                'show_form' => $ui['show_form'],
            ]);
        }

        $message = $validated['response'] === 'yes'
            ? 'Stage confirmation saved.'
            : 'Timeline adjusted for slower progress.';

        return redirect()
            ->route('crop-progress.index')
            ->with('success', $message);
    }

    public function reopenRealityCheck(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $user->reality_check_answered = false;
        $user->reality_check_status = null;
        $user->stage_confirmed_at = null;
        $user->crop_stage_reality_check = null;
        $user->save();

        if ($this->wantsJsonRealityResponse($request)) {
            return response()->json([
                'ok' => true,
                'show_form' => true,
            ]);
        }

        return redirect()
            ->route('crop-progress.index')
            ->with('success', 'You can update your field status again.')
            ->with('reality_check_reopened', true);
    }

    public function updateCurrentStage(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'farming_stage' => ['required', 'string', 'in:'.implode(',', CropTimelineService::STAGE_ORDER)],
        ]);

        $user->farming_stage = $validated['farming_stage'];
        $this->applyManualStageConfirmation($user);
        $user->save();

        return redirect()
            ->route('crop-progress.index')
            ->with('success', 'Current stage updated. Timeline and advice were refreshed.');
    }

    public function updateStage(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'farming_stage' => ['required', 'string', 'in:'.implode(',', CropTimelineService::STAGE_ORDER)],
            'planting_date' => ['nullable', 'date'],
        ], [
            'farming_stage.required' => 'Please select your current growth stage.',
            'farming_stage.in' => 'Selected growth stage is invalid.',
            'planting_date.date' => 'Please provide a valid planting date.',
        ]);

        $data = ['farming_stage' => $validated['farming_stage']];
        if (! empty($validated['planting_date'])) {
            $data['planting_date'] = $validated['planting_date'];
        }

        $user->update(array_merge($data, $this->manualStageConfirmationAttributes()));

        return redirect()
            ->route('crop-progress.index')
            ->with('success', 'Crop progress updated successfully.');
    }

    public function generateStageAdvice(User $user, array $weatherContext, AiAdvisoryService $aiAdvisoryService): array
    {
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));
        $pack = $aiAdvisoryService->runCropProgress($user, $weatherContext);

        if ($pack['failed'] === false) {
            return $pack;
        }

        $reco = $pack['recommendation'] ?? [];
        if (($reco['ai_status'] ?? '') === 'missing_context') {
            return $pack;
        }

        $fallback = $this->stageAdviceFallback($user, $weatherContext);
        foreach (['main_advice', 'what_to_do', 'what_to_watch', 'what_to_avoid', 'timeline_adjustment_reason', 'timeline_adjustment_label'] as $k) {
            $fallback[$k] = '';
        }
        $fallback['risk_level'] = '';
        $fallback['ai_status'] = 'failed';
        $fallback['ai_model'] = $modelName;
        $fallback['ai_error'] = 'AI advisory temporarily unavailable.';

        return [
            'recommendation' => $fallback,
            'failed' => true,
        ];
    }

    private function buildWeatherContext(User $user, WeatherAdvisoryService $weatherAdvisoryService): array
    {
        try {
            $advisory = $weatherAdvisoryService->getAdvisoryData($user);
            $weather = $advisory['weather'] ?? [];
            $monthlyTrend = $advisory['charts']['monthly_trend'] ?? [];

            return [
                'condition' => (string) ($weather['condition']['main'] ?? ($weather['condition']['description'] ?? 'Unknown')),
                'temperature' => is_numeric($weather['temp'] ?? null) ? (float) $weather['temp'] : null,
                'humidity' => is_numeric($weather['humidity'] ?? null) ? (int) round((float) $weather['humidity']) : null,
                'rain_chance' => is_numeric($advisory['rain_probability_display'] ?? null)
                    ? (int) round((float) $advisory['rain_probability_display'])
                    : null,
                'wind_speed' => is_numeric($weather['wind_speed'] ?? null) ? (float) $weather['wind_speed'] : null,
                'recent_weather' => [
                    'last_updated' => (string) ($advisory['last_updated'] ?? ''),
                    'condition' => (string) ($weather['condition']['description'] ?? ($weather['condition']['main'] ?? 'Unknown')),
                    'rainfall_today_mm' => is_numeric($weather['today_expected_rainfall'] ?? null) ? (float) $weather['today_expected_rainfall'] : null,
                ],
                'forecast' => array_map(static function (array $day): array {
                    return [
                        'day' => (string) ($day['day_name'] ?? ''),
                        'condition' => (string) ($day['condition']['main'] ?? ($day['condition']['description'] ?? 'Unknown')),
                        'temp_min' => is_numeric($day['temp_min'] ?? null) ? (float) $day['temp_min'] : null,
                        'temp_max' => is_numeric($day['temp_max'] ?? null) ? (float) $day['temp_max'] : null,
                        'rain_chance' => is_numeric($day['pop'] ?? null) ? (int) round((float) $day['pop']) : null,
                    ];
                }, array_slice(($advisory['forecast'] ?? []), 0, 5)),
                'rainfall_trend' => $this->deriveRainfallTrend($monthlyTrend),
            ];
        } catch (\Throwable $e) {
            Log::warning('Crop progress weather context unavailable', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return [
                'condition' => 'Unknown',
                'temperature' => null,
                'humidity' => null,
                'rain_chance' => null,
                'wind_speed' => null,
                'recent_weather' => [],
                'forecast' => [],
                'rainfall_trend' => 'stable',
            ];
        }
    }

    private function deriveRainfallTrend(array $monthlyTrend): string
    {
        $values = [];
        foreach ($monthlyTrend as $row) {
            $rain = $row['avg_rain'] ?? $row['avg_rainfall'] ?? null;
            if (is_numeric($rain)) {
                $values[] = (float) $rain;
            }
        }

        if (count($values) < 2) {
            return 'stable';
        }

        $recent = array_slice($values, -2);
        $prev = $recent[0];
        $last = $recent[1];

        if ($last > ($prev * 1.1)) {
            return 'increasing';
        }

        if ($last < ($prev * 0.9)) {
            return 'decreasing';
        }

        return 'stable';
    }

    private function stageAdviceFallback(User $user, array $weatherContext): array
    {
        $durations = $this->cropStageDurations((string) $user->crop_type);
        $currentStage = app(CropTimelineService::class)->inferExpectedStageFromPlanting($user, $durations)['key'];
        $plantingDate = $user->planting_date instanceof Carbon ? $user->planting_date->copy()->startOfDay() : now()->startOfDay();
        $timeline = $this->buildFallbackTimeline((string) $user->crop_type, $plantingDate, $currentStage, $weatherContext);

        $currentStageLabel = app(CropTimelineService::class)->displayLabel($currentStage);
        $next = $this->resolveNextStage($timeline);
        $riskLevel = $this->fallbackRiskLevel($weatherContext);

        $reason = $this->fallbackTimelineReason($weatherContext);

        return [
            'current_stage' => $currentStageLabel,
            'next_stage' => $next['stage'] ?? $currentStageLabel,
            'next_stage_target_date' => $next['target_date'] ?? ($timeline[count($timeline) - 1]['target_date'] ?? now()->format('Y-m-d')),
            'days_remaining' => isset($next['target_date']) ? max(0, now()->startOfDay()->diffInDays(Carbon::parse($next['target_date']), false)) : 0,
            'timeline' => $timeline,
            'timeline_adjustment_reason' => $reason,
            'timeline_adjustment_label' => $this->timelineAdjustmentLabel($reason),
            'main_advice' => 'Monitor crop progress daily and adjust field tasks based on weather.',
            'what_to_do' => 'Check drainage, crop condition, and stage progress before major field work.',
            'what_to_watch' => 'Watch moisture, humidity, and early signs of crop stress.',
            'what_to_avoid' => 'Avoid overwatering and delayed action during adverse weather.',
            'risk_level' => $riskLevel,
        ];
    }

    private function buildFallbackTimeline(string $cropType, Carbon $plantingDate, string $currentStage, array $weatherContext): array
    {
        $durations = $this->cropStageDurations($cropType);
        $adjustment = $this->weatherDayAdjustment($weatherContext);
        $stageOrder = array_keys(self::TIMELINE_STAGES);
        $currentIndex = array_search($currentStage, $stageOrder, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $timeline = [];
        $dayCursor = 0;
        foreach ($stageOrder as $index => $stageKey) {
            $duration = $durations[$stageKey] ?? 14;
            if ($index >= $currentIndex) {
                $duration += $adjustment;
            }
            $duration = max(4, $duration);

            $target = $plantingDate->copy()->addDays($dayCursor);
            $status = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'current' : 'upcoming');
            $timeline[] = [
                'stage' => self::TIMELINE_STAGES[$stageKey],
                'target_date' => $target->format('Y-m-d'),
                'estimated_day_count' => $dayCursor,
                'status' => $status,
            ];

            $dayCursor += $duration;
        }

        return $timeline;
    }

    private function cropStageDurations(string $cropType): array
    {
        return app(CropTimelineService::class)->stageDurationsForCrop($cropType);
    }

    private function weatherDayAdjustment(array $weatherContext): int
    {
        $delay = 0;
        $rainChance = $weatherContext['rain_chance'] ?? null;
        $humidity = $weatherContext['humidity'] ?? null;
        $temp = $weatherContext['temperature'] ?? null;
        $trend = strtolower((string) ($weatherContext['rainfall_trend'] ?? 'stable'));

        if (is_numeric($rainChance) && (int) $rainChance >= 70) {
            $delay += 2;
        } elseif (is_numeric($rainChance) && (int) $rainChance <= 30) {
            $delay -= 1;
        }

        if (is_numeric($humidity) && (int) $humidity >= 85) {
            $delay += 1;
        }

        if (is_numeric($temp) && ((float) $temp >= 34 || (float) $temp <= 18)) {
            $delay += 1;
        } elseif (is_numeric($temp) && (float) $temp >= 24 && (float) $temp <= 31) {
            $delay -= 1;
        }

        if ($trend === 'increasing') {
            $delay += 1;
        } elseif ($trend === 'decreasing') {
            $delay -= 1;
        }

        return max(-2, min(4, $delay));
    }

    private function fallbackTimelineReason(array $weatherContext): string
    {
        $rainChance = $weatherContext['rain_chance'] ?? null;
        $humidity = $weatherContext['humidity'] ?? null;
        $temp = $weatherContext['temperature'] ?? null;

        if (is_numeric($rainChance) && (int) $rainChance >= 70) {
            return 'Frequent rain may slightly delay crop development.';
        }

        if (is_numeric($humidity) && (int) $humidity >= 85) {
            return 'High humidity may slow stage transitions slightly.';
        }

        if (is_numeric($temp) && (float) $temp >= 24 && (float) $temp <= 31 && is_numeric($rainChance) && (int) $rainChance <= 50) {
            return 'Current weather supports normal crop development.';
        }

        return 'Timeline is estimated from planting date and current weather context.';
    }

    private function fallbackRiskLevel(array $weatherContext): string
    {
        $rainChance = $weatherContext['rain_chance'] ?? null;
        if (is_numeric($rainChance) && (int) $rainChance >= 75) {
            return 'High';
        }
        if (is_numeric($rainChance) && (int) $rainChance <= 30) {
            return 'Low';
        }

        return 'Moderate';
    }

    private function resolveNextStage(array $timeline): ?array
    {
        foreach ($timeline as $item) {
            if (($item['status'] ?? '') === 'upcoming') {
                return $item;
            }
        }

        return null;
    }

    private function timelineAdjustmentLabel(string $reason): string
    {
        $text = strtolower($reason);
        if (str_contains($text, 'delay') || str_contains($text, 'slow')) {
            return 'Slightly delayed';
        }
        if (str_contains($text, 'faster') || str_contains($text, 'ahead') || str_contains($text, 'good sun')) {
            return 'Faster than usual';
        }

        return 'On schedule';
    }

    private function stageLabel(?string $stage): string
    {
        return app(CropTimelineService::class)->displayLabel($stage);
    }

    private function normalizeStageKey(string $stage): string
    {
        return app(CropTimelineService::class)->normalizeStageKey($stage);
    }

    /**
     * Timeline "current" row stage label (AI), or the user's profile stage label as fallback.
     *
     * @param  array<string, mixed>  $rec
     */
    private function resolveRealityQuestionStageLabel(User $user, array $rec): string
    {
        foreach ($rec['timeline'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['status'] ?? '') === 'current' && ! empty($row['stage'])) {
                return (string) $row['stage'];
            }
        }

        return $this->stageLabel($user->farming_stage);
    }

    /**
     * Map AI or display labels ("Early Growth", "Harvest") to internal stage keys.
     */
    private function normalizeStageLabelToKey(string $label): string
    {
        return app(CropTimelineService::class)->timelineStageRowToKey($label);
    }

    private function applyManualStageConfirmation(User $user): void
    {
        $user->reality_check_answered = true;
        $user->reality_check_status = 'confirmed';
        $user->stage_confirmed_at = now();
        $user->crop_stage_reality_check = 'confirmed';
    }

    /**
     * @return array<string, mixed>
     */
    private function manualStageConfirmationAttributes(): array
    {
        return [
            'reality_check_answered' => true,
            'reality_check_status' => 'confirmed',
            'stage_confirmed_at' => now(),
            'crop_stage_reality_check' => 'confirmed',
        ];
    }

    /**
     * @return array{show_form: bool, show_success_banner: bool, show_delay_banner: bool}
     */
    private function resolveRealityCheckUiState(
        User $user,
        string $comparison,
        string $growthSpeed,
        bool $confidenceIsLow,
        string $questionStageKey,
        string $actualKey,
        bool $realityReopened,
    ): array {
        $answered = (bool) $user->reality_check_answered;
        $status = (string) ($user->reality_check_status ?? '');

        $promptMatchesActual = $questionStageKey === $actualKey;

        $showDelayBanner = $answered && $status === 'delayed';
        $showSuccessBanner = $answered && $status === 'confirmed'
            && $comparison === 'match'
            && $growthSpeed !== 'slow'
            && ! $confidenceIsLow;

        $showForm = false;
        if (! $answered) {
            if ($realityReopened) {
                $showForm = true;
            } else {
                $showForm = ! $promptMatchesActual;
            }
        } elseif ($status === 'confirmed') {
            $showForm = $confidenceIsLow;
        }

        $showForm = $showForm && ! $showDelayBanner && ! $showSuccessBanner;

        return [
            'show_form' => $showForm,
            'show_success_banner' => $showSuccessBanner,
            'show_delay_banner' => $showDelayBanner,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function realityCheckResetAttributes(): array
    {
        return [
            'reality_check_answered' => false,
            'reality_check_status' => null,
            'stage_confirmed_at' => null,
            'crop_stage_reality_check' => null,
        ];
    }

    private function wantsJsonRealityResponse(Request $request): bool
    {
        return $request->wantsJson()
            || $request->expectsJson()
            || $request->ajax()
            || $request->boolean('json');
    }
}
