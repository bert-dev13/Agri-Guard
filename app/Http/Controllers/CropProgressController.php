<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TogetherAiService;
use App\Services\WeatherAdvisoryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CropProgressController extends Controller
{
    private const TIMELINE_STAGES = [
        'planting' => 'Planting',
        'early_growth' => 'Early Growth',
        'growing' => 'Vegetative',
        'flowering_fruiting' => 'Flowering',
        'harvesting' => 'Harvesting',
    ];

    private const STAGE_LABELS = [
        'land_preparation' => 'Land Preparation',
        'planting' => 'Planting',
        'early_growth' => 'Early Growth',
        'growing' => 'Vegetative',
        'flowering_fruiting' => 'Flowering',
        'harvesting' => 'Harvesting',
    ];

    public function index(WeatherAdvisoryService $weatherAdvisoryService, TogetherAiService $togetherAiService): View
    {
        /** @var User $user */
        $user = Auth::user();
        $weatherContext = $this->buildWeatherContext($user, $weatherAdvisoryService);
        $stageInsights = $this->generateStageAdvice($user, $weatherContext, $togetherAiService);

        return view('user.crop-progress.index', [
            'user' => $user,
            'stages' => self::TIMELINE_STAGES,
            'farm_name' => trim((string) $user->name) . ' Farm',
            'current_stage' => $user->farming_stage,
            'current_stage_label' => $this->stageLabel($user->farming_stage),
            'weather_context' => $weatherContext,
            'recommendation' => $stageInsights['recommendation'],
            'recommendation_failed' => $stageInsights['failed'],
            'timeline' => $stageInsights['recommendation']['timeline'] ?? [],
            'next_stage' => $stageInsights['recommendation']['next_stage'] ?? null,
            'next_stage_target_date' => $stageInsights['recommendation']['next_stage_target_date'] ?? null,
            'days_remaining' => $stageInsights['recommendation']['days_remaining'] ?? null,
            'timeline_adjustment_reason' => $stageInsights['recommendation']['timeline_adjustment_reason'] ?? 'Timeline is based on recent farm weather context.',
            'timeline_adjustment_label' => $stageInsights['recommendation']['timeline_adjustment_label'] ?? 'On schedule',
        ]);
    }

    public function updateStage(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'farming_stage' => ['required', 'string', 'in:' . implode(',', array_keys(self::TIMELINE_STAGES))],
            'planting_date' => ['required', 'date'],
        ], [
            'farming_stage.required' => 'Please select your current growth stage.',
            'farming_stage.in' => 'Selected growth stage is invalid.',
            'planting_date.required' => 'Please enter the planting date.',
            'planting_date.date' => 'Please provide a valid planting date.',
        ]);

        $user->update([
            'farming_stage' => $validated['farming_stage'],
            'planting_date' => $validated['planting_date'],
        ]);

        return redirect()
            ->route('crop-progress.index')
            ->with('success', 'Crop progress updated successfully.');
    }

    public function generateStageAdvice(User $user, array $weatherContext, TogetherAiService $togetherAiService): array
    {
        $payload = [
            'crop_type' => $user->crop_type,
            'current_growth_stage' => $user->farming_stage,
            'planting_date' => $user->planting_date?->format('Y-m-d'),
            'farm_location' => $user->farm_location_display,
            'current_weather' => [
                'condition' => $weatherContext['condition'],
                'temperature' => $weatherContext['temperature'],
                'humidity' => $weatherContext['humidity'],
                'rain_chance' => $weatherContext['rain_chance'],
                'wind_speed' => $weatherContext['wind_speed'],
            ],
            'recent_weather' => $weatherContext['recent_weather'],
            'forecast' => $weatherContext['forecast'],
            'rainfall_trend' => $weatherContext['rainfall_trend'],
        ];

        $fallback = $this->stageAdviceFallback($user, $weatherContext);
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));

        try {
            $response = $togetherAiService->generateRecommendation($payload, $this->stageAdvicePrompt());
            $decoded = $this->decodeRecommendationJson((string) ($response['raw_content'] ?? ''));

            if (! is_array($decoded)) {
                throw new \RuntimeException('Together AI returned malformed crop progress JSON payload.');
            }

            return [
                'recommendation' => array_merge(
                    $this->normalizeStageAdvice($decoded, $fallback),
                    [
                        'ai_status' => 'success',
                        'ai_model' => (string) ($response['model_used'] ?? $modelName),
                        'ai_error' => '',
                    ]
                ),
                'failed' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('Crop progress AI advice failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'payload' => $payload,
            ]);

            return [
                'recommendation' => array_merge(
                    $fallback,
                    [
                        'ai_status' => 'failed',
                        'ai_model' => $modelName,
                        'ai_error' => 'Stage recommendation AI unavailable.',
                    ]
                ),
                'failed' => true,
            ];
        }
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

    private function stageAdvicePrompt(): string
    {
        return <<<'PROMPT'
You are an agricultural advisor for smallholder farmers.
Use only the provided JSON input and return stage-based advice.

Return valid JSON only with exactly these keys:
{
  "current_stage": "Planting|Early Growth|Vegetative|Flowering|Harvesting",
  "next_stage": "Early Growth|Vegetative|Flowering|Harvesting|Harvesting",
  "next_stage_target_date": "YYYY-MM-DD",
  "days_remaining": 0,
  "timeline": [
    {
      "stage": "Planting|Early Growth|Vegetative|Flowering|Harvesting",
      "target_date": "YYYY-MM-DD",
      "estimated_day_count": 0,
      "status": "completed|current|upcoming"
    }
  ],
  "timeline_adjustment_reason": "string",
  "main_advice": "string",
  "what_to_do": "string",
  "what_to_watch": "string",
  "what_to_avoid": "string",
  "risk_level": "Low|Moderate|High"
}

Rules:
- Keep language simple and farmer-friendly.
- Respect crop_type, planting_date, and current_growth_stage.
- Adjust timeline dates based on weather and rainfall context.
- If weather is favorable, timeline may be on schedule or faster.
- If heavy rain/high humidity/temperature stress exists, show slight delays.
- Keep each field concise and actionable.
- Do not add markdown, code fences, or extra keys.
PROMPT;
    }

    private function decodeRecommendationJson(string $rawContent): ?array
    {
        $trimmed = trim($rawContent);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            $decoded = json_decode((string) $matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function normalizeStageAdvice(array $raw, array $fallback): array
    {
        $risk = strtolower(trim((string) ($raw['risk_level'] ?? '')));
        $risk = match ($risk) {
            'low' => 'Low',
            'high' => 'High',
            'moderate', 'medium' => 'Moderate',
            default => $fallback['risk_level'],
        };

        $timelineRaw = is_array($raw['timeline'] ?? null) ? $raw['timeline'] : [];
        $timeline = $this->normalizeTimeline($timelineRaw, $fallback['timeline']);
        $nextTargetDate = $this->normalizeDate($raw['next_stage_target_date'] ?? null, $fallback['next_stage_target_date']);
        $daysRemaining = is_numeric($raw['days_remaining'] ?? null) ? max(0, (int) $raw['days_remaining']) : $fallback['days_remaining'];
        $adjustmentReason = $this->textOrFallback($raw['timeline_adjustment_reason'] ?? null, $fallback['timeline_adjustment_reason']);

        return [
            'current_stage' => $this->textOrFallback($raw['current_stage'] ?? null, $fallback['current_stage']),
            'next_stage' => $this->textOrFallback($raw['next_stage'] ?? null, $fallback['next_stage']),
            'next_stage_target_date' => $nextTargetDate,
            'days_remaining' => $daysRemaining,
            'timeline' => $timeline,
            'timeline_adjustment_reason' => $adjustmentReason,
            'timeline_adjustment_label' => $this->timelineAdjustmentLabel($adjustmentReason),
            'main_advice' => $this->textOrFallback($raw['main_advice'] ?? null, $fallback['main_advice']),
            'what_to_do' => $this->textOrFallback($raw['what_to_do'] ?? null, $fallback['what_to_do']),
            'what_to_watch' => $this->textOrFallback($raw['what_to_watch'] ?? null, $fallback['what_to_watch']),
            'what_to_avoid' => $this->textOrFallback($raw['what_to_avoid'] ?? null, $fallback['what_to_avoid']),
            'risk_level' => $risk,
        ];
    }

    private function stageAdviceFallback(User $user, array $weatherContext): array
    {
        $currentStage = $this->normalizeStageKey((string) ($user->farming_stage ?: 'planting'));
        $plantingDate = $user->planting_date instanceof Carbon ? $user->planting_date->copy() : now()->startOfDay();
        $timeline = $this->buildFallbackTimeline((string) $user->crop_type, $plantingDate, $currentStage, $weatherContext);

        $currentStageLabel = $this->stageLabel($currentStage);
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
        $isCorn = strcasecmp(trim($cropType), 'Corn') === 0;

        return $isCorn
            ? [
                'planting' => 8,
                'early_growth' => 16,
                'growing' => 20,
                'flowering_fruiting' => 18,
                'harvesting' => 30,
            ]
            : [
                'planting' => 7,
                'early_growth' => 14,
                'growing' => 21,
                'flowering_fruiting' => 24,
                'harvesting' => 28,
            ];
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

    private function normalizeTimeline(array $rawTimeline, array $fallbackTimeline): array
    {
        if (empty($rawTimeline)) {
            return $fallbackTimeline;
        }

        $normalized = [];
        foreach ($rawTimeline as $row) {
            if (! is_array($row)) {
                continue;
            }

            $status = strtolower((string) ($row['status'] ?? 'upcoming'));
            if (! in_array($status, ['completed', 'current', 'upcoming'], true)) {
                $status = 'upcoming';
            }

            $normalized[] = [
                'stage' => trim((string) ($row['stage'] ?? '')) ?: 'Unknown Stage',
                'target_date' => $this->normalizeDate($row['target_date'] ?? null, now()->format('Y-m-d')),
                'estimated_day_count' => is_numeric($row['estimated_day_count'] ?? null) ? max(0, (int) $row['estimated_day_count']) : 0,
                'status' => $status,
            ];
        }

        return empty($normalized) ? $fallbackTimeline : $normalized;
    }

    private function normalizeDate(mixed $dateValue, string $fallback): string
    {
        $date = trim((string) $dateValue);
        if ($date === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return $fallback;
        }
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

    private function textOrFallback(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : $fallback;
    }

    private function stageLabel(?string $stage): string
    {
        return self::STAGE_LABELS[$stage ?? ''] ?? 'Not set';
    }

    private function normalizeStageKey(string $stage): string
    {
        if ($stage === 'land_preparation') {
            return 'planting';
        }

        return array_key_exists($stage, self::TIMELINE_STAGES) ? $stage : 'planting';
    }
}
