<?php

namespace App\Services\AiAdvisory;

use App\Models\User;
use App\Services\CropTimelineService;
use App\Services\TogetherAiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Single Together AI entry point for page-specific farm advisories.
 * Validates a strict unified JSON shape; adapters map to legacy view/JS shapes.
 */
final class AiAdvisoryService
{
    public const PAGE_DASHBOARD = 'dashboard';

    public const PAGE_WEATHER = 'weather';

    public const PAGE_RAINFALL = 'rainfall';

    public const PAGE_CROP = 'crop';

    public const PAGE_MAP = 'map';

    private const CACHE_TTL_SECONDS = 1200;

    private const CACHE_VERSION = 'unified_v1';

    private const UNIFIED_SCHEMA_INSTRUCTION = <<<'TXT'
Return valid JSON only (no markdown, no code fences, no keys beyond those listed).

Required shape:
{
  "status": "active",
  "risk": "low" | "moderate" | "high",
  "confidence": "low" | "medium" | "high",
  "summary": "one clear primary action or headline for the farmer (plain English)",
  "insight": "one short sentence tying advice to the input (may be empty string only if nothing credible can be said)",
  "plan": {
    "morning": "non-empty one-line guidance for early day",
    "afternoon": "non-empty one-line guidance for midday",
    "evening": "non-empty one-line guidance for late day"
  },
  "avoid": "non-empty one-line: what not to do today",
  "water": "non-empty one-line: irrigation / drainage / soil moisture guidance"
}

Rules:
- status must be exactly "active" when guidance is safe to show.
- risk/confidence must be lowercase tokens exactly as enumerated.
- All string fields except insight must be non-empty after trimming.
- Use only facts supported by the input JSON; do not invent locations or measurements not given.
- Mention at least two concrete facts from the input (names, numbers, crop, stage, weather, rainfall, dates, map signals).
- Wording must change meaningfully when the input changes (do not reuse a fixed template).
TXT;

    public function __construct(
        private readonly TogetherAiService $together,
        private readonly CropTimelineService $cropTimeline,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Page-specific context JSON sent to the model (already compact)
     * @return array{_meta: array<string, mixed>, unified?: array<string, mixed>, crop_extension?: array<string, mixed>}
     */
    public function run(string $page, User $user, array $input): array
    {
        $page = strtolower(trim($page));
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));

        if (! in_array($page, [self::PAGE_DASHBOARD, self::PAGE_WEATHER, self::PAGE_RAINFALL, self::PAGE_MAP], true)) {
            return $this->failureMeta($modelName, 'Invalid advisory page mode.', ['page' => $page]);
        }

        if ($page !== self::PAGE_MAP && ! $this->hasCropContext($user)) {
            return [
                '_meta' => [
                    'ai_status' => 'missing_context',
                    'model' => $modelName,
                    'error' => 'Please set crop type and farming stage in Farm Settings to receive AI advisory.',
                    'page' => $page,
                ],
            ];
        }

        $fingerprint = $this->payloadFingerprint($page, $user->id, $input);
        $cacheKey = sprintf('ai_advisory:%s:%d:%s:%s', self::CACHE_VERSION, $user->id, $page, $fingerprint);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['_meta'])) {
            Log::info('AiAdvisoryService cache hit', ['page' => $page, 'user_id' => $user->id]);

            return $cached;
        }

        $system = $this->systemPromptFor($page);
        $userInstruction = $this->userInstructionFor($page);

        Log::info('AiAdvisoryService request', [
            'page' => $page,
            'user_id' => $user->id,
            'input' => $input,
        ]);

        try {
            $result = $this->together->generateRecommendation(
                array_merge(['page' => $page], $input),
                $system,
                $userInstruction
            );
            $raw = (string) ($result['raw_content'] ?? '');
            $decoded = $this->decodeJsonObject($raw);

            if (! is_array($decoded)) {
                Log::error('AiAdvisoryService parse failed', [
                    'page' => $page,
                    'user_id' => $user->id,
                    'raw_excerpt' => mb_substr($raw, 0, 2000),
                ]);

                return $this->remember($cacheKey, $this->failureMeta($modelName, 'Invalid AI response format.', ['page' => $page, 'raw_excerpt' => mb_substr($raw, 0, 500)]));
            }

            $unified = $this->validateAndNormalizeUnified($decoded);
            if ($unified === null) {
                Log::error('AiAdvisoryService validation failed', [
                    'page' => $page,
                    'user_id' => $user->id,
                    'decoded_keys' => array_keys($decoded),
                    'raw_excerpt' => mb_substr($raw, 0, 2000),
                ]);

                return $this->remember($cacheKey, $this->failureMeta($modelName, 'AI response failed validation.', ['page' => $page, 'raw_excerpt' => mb_substr($raw, 0, 500)]));
            }

            if ($page === self::PAGE_DASHBOARD && $this->containsUnrelatedCropMentions($unified, (string) data_get($input, 'crop_type', ''))) {
                Log::warning('AiAdvisoryService crop guard rejected response', ['user_id' => $user->id, 'page' => $page]);

                return $this->remember($cacheKey, $this->failureMeta($modelName, 'AI response failed crop validation.', ['page' => $page]));
            }

            $out = [
                '_meta' => [
                    'ai_status' => 'success',
                    'model' => (string) ($result['model_used'] ?? $modelName),
                    'error' => '',
                    'page' => $page,
                    'requested_at' => (string) ($result['requested_at'] ?? now()->toIso8601String()),
                ],
                'unified' => $unified,
            ];

            Log::info('AiAdvisoryService success', [
                'page' => $page,
                'user_id' => $user->id,
                'model' => $out['_meta']['model'],
            ]);

            return $this->remember($cacheKey, $out);
        } catch (RuntimeException $e) {
            Log::warning('AiAdvisoryService Together unavailable', [
                'page' => $page,
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return $this->remember($cacheKey, $this->failureMeta($modelName, $e->getMessage(), ['page' => $page]));
        } catch (Throwable $e) {
            Log::error('AiAdvisoryService error', [
                'page' => $page,
                'user_id' => $user->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->remember($cacheKey, $this->failureMeta($modelName, 'Together AI request failed.', ['page' => $page]));
        }
    }

    /**
     * @param  array<string, mixed>  $weatherContext  From CropProgressController::buildWeatherContext
     * @return array{recommendation: array<string, mixed>, failed: bool}
     */
    public function runCropProgress(User $user, array $weatherContext): array
    {
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));

        if (! $this->hasCropContext($user)) {
            return [
                'recommendation' => $this->emptyCropRecommendation($modelName, 'missing_context', 'Please set crop type and farming stage in Farm Settings to receive AI advisory.'),
                'failed' => true,
            ];
        }

        $payload = [
            'page' => self::PAGE_CROP,
            'crop_type' => $user->crop_type,
            'current_growth_stage' => $user->farming_stage,
            'planting_date' => $user->planting_date?->format('Y-m-d'),
            'farm_location' => $user->farm_location_display,
            'field_condition' => $user->field_condition ?? null,
            'current_weather' => $weatherContext['current_weather'] ?? [],
            'recent_weather' => $weatherContext['recent_weather'] ?? [],
            'forecast' => $weatherContext['forecast'] ?? [],
            'rainfall_trend' => $weatherContext['rainfall_trend'] ?? 'stable',
        ];

        $fingerprint = $this->payloadFingerprint(self::PAGE_CROP, $user->id, $payload);
        $cacheKey = sprintf('ai_advisory:%s:%d:%s:%s', self::CACHE_VERSION, $user->id, self::PAGE_CROP, $fingerprint);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['recommendation'], $cached['failed']) && $cached['failed'] === false) {
            return ['recommendation' => $cached['recommendation'], 'failed' => false];
        }

        $system = $this->cropSystemPrompt();
        Log::info('AiAdvisoryService crop request', ['user_id' => $user->id, 'input' => $payload]);

        try {
            $result = $this->together->generateRecommendation($payload, $system);
            $raw = (string) ($result['raw_content'] ?? '');
            $decoded = $this->decodeJsonObject($raw);
            if (! is_array($decoded)) {
                Log::error('AiAdvisoryService crop parse failed', ['user_id' => $user->id, 'raw_excerpt' => mb_substr($raw, 0, 2000)]);

                return ['recommendation' => $this->emptyCropRecommendation($modelName, 'failed', 'Invalid AI response format.'), 'failed' => true];
            }

            $normalized = $this->validateAndNormalizeCropResponse($decoded);
            if ($normalized === null) {
                Log::error('AiAdvisoryService crop validation failed', ['user_id' => $user->id, 'raw_excerpt' => mb_substr($raw, 0, 2000)]);

                return ['recommendation' => $this->emptyCropRecommendation($modelName, 'failed', 'AI response failed validation.'), 'failed' => true];
            }

            $rec = array_merge($normalized, [
                'ai_status' => 'success',
                'ai_model' => (string) ($result['model_used'] ?? $modelName),
                'ai_error' => '',
            ]);

            $pack = ['recommendation' => $rec, 'failed' => false];
            Cache::put($cacheKey, $pack, self::CACHE_TTL_SECONDS);

            return $pack;
        } catch (Throwable $e) {
            Log::error('AiAdvisoryService crop error', [
                'user_id' => $user->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return ['recommendation' => $this->emptyCropRecommendation($modelName, 'failed', 'Together AI request failed.'), 'failed' => true];
        }
    }

    /**
     * @param  array<string, mixed>  $runResult  Output of run() for dashboard page
     * @return array<string, mixed> Same keys as legacy FarmRecommendationService::toSmartRecommendation
     */
    public function formatDashboardCard(array $runResult): array
    {
        $meta = $runResult['_meta'] ?? [];
        $status = strtolower((string) ($meta['ai_status'] ?? 'failed'));

        $empty = $this->emptyDashboardCard();

        if ($status === 'missing_context') {
            $empty['ai_status'] = 'missing_context';

            return $empty;
        }

        if ($status !== 'success') {
            $empty['ai_status'] = $status !== '' ? $status : 'failed';

            return $empty;
        }

        $u = $runResult['unified'] ?? null;
        if (! is_array($u)) {
            return $empty;
        }

        $morning = trim((string) data_get($u, 'plan.morning', ''));
        $afternoon = trim((string) data_get($u, 'plan.afternoon', ''));
        $evening = trim((string) data_get($u, 'plan.evening', ''));
        $avoid = trim((string) ($u['avoid'] ?? ''));

        return [
            'main_recommendation' => trim((string) ($u['summary'] ?? '')),
            'action' => trim((string) ($u['summary'] ?? '')),
            'score' => $this->riskToScore((string) ($u['risk'] ?? '')),
            'confidence' => $this->normalizeConfidenceLabel((string) ($u['confidence'] ?? '')),
            'ai_confidence' => $this->normalizeConfidenceLabel((string) ($u['confidence'] ?? '')),
            'why' => trim((string) ($u['insight'] ?? '')),
            'plan' => [
                'morning' => $morning !== '' ? [$morning] : [],
                'afternoon' => $afternoon !== '' ? [$afternoon] : [],
                'evening' => $evening !== '' ? [$evening] : [],
            ],
            'today_plan' => [
                'morning' => $morning,
                'afternoon' => $afternoon,
                'evening' => $evening,
            ],
            'avoid' => $avoid,
            'avoid_list' => $avoid !== '' ? [$avoid] : [],
            'water' => trim((string) ($u['water'] ?? '')),
            'water_strategy' => trim((string) ($u['water'] ?? '')),
            'risk' => $this->riskTitleCase((string) ($u['risk'] ?? '')),
            'ai_status' => 'success',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatWeatherRecommendation(array $runResult, string $modelName): array
    {
        $meta = $runResult['_meta'] ?? [];
        $status = strtolower((string) ($meta['ai_status'] ?? 'failed'));
        $empty = $this->emptyWeatherShape($modelName);

        if ($status === 'missing_context') {
            return array_merge($empty, [
                'ai_status' => 'missing_context',
                'ai_error' => (string) ($meta['error'] ?? ''),
            ]);
        }

        if ($status !== 'success') {
            return array_merge($empty, [
                'ai_status' => 'failed',
                'ai_error' => (string) ($meta['error'] ?? 'AI advisory temporarily unavailable.'),
            ]);
        }

        $u = $runResult['unified'] ?? [];
        if (! is_array($u)) {
            return $empty;
        }

        $morning = trim((string) data_get($u, 'plan.morning', ''));
        $afternoon = trim((string) data_get($u, 'plan.afternoon', ''));
        $evening = trim((string) data_get($u, 'plan.evening', ''));

        return [
            'main_recommendation' => trim((string) ($u['summary'] ?? '')),
            'farm_score' => $this->riskToScore((string) ($u['risk'] ?? '')),
            'ai_confidence' => $this->normalizeConfidenceLabel((string) ($u['confidence'] ?? '')),
            'why' => trim((string) ($u['insight'] ?? '')),
            'today_plan' => [
                'morning' => $morning,
                'afternoon' => $afternoon,
                'evening' => $evening,
            ],
            'avoid' => trim((string) ($u['avoid'] ?? '')),
            'water_strategy' => trim((string) ($u['water'] ?? '')),
            'risk_level' => strtolower($this->riskTitleCase((string) ($u['risk'] ?? ''))),
            'risk' => strtolower($this->riskTitleCase((string) ($u['risk'] ?? ''))),
            'ai_status' => 'success',
            'ai_model' => (string) ($meta['model'] ?? $modelName),
            'ai_error' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRainfallRecommendation(array $runResult, string $modelName): array
    {
        $meta = $runResult['_meta'] ?? [];
        $status = strtolower((string) ($meta['ai_status'] ?? 'failed'));
        $empty = $this->emptyRainfallShape($modelName);

        if ($status === 'missing_context') {
            return array_merge($empty, [
                'ai_status' => 'missing_context',
                'ai_error' => (string) ($meta['error'] ?? ''),
            ]);
        }

        if ($status !== 'success') {
            return array_merge($empty, [
                'ai_status' => 'failed',
                'ai_error' => (string) ($meta['error'] ?? 'AI advisory temporarily unavailable.'),
            ]);
        }

        $u = $runResult['unified'] ?? [];
        if (! is_array($u)) {
            return $empty;
        }

        $risk = strtolower($this->riskTitleCase((string) ($u['risk'] ?? '')));

        return [
            'main_rainfall_advice' => trim((string) ($u['summary'] ?? '')),
            'rainfall_risk_score' => $this->riskToScore((string) ($u['risk'] ?? '')),
            'ai_confidence' => $this->normalizeConfidenceLabel((string) ($u['confidence'] ?? '')),
            'rainfall_insight' => trim((string) ($u['insight'] ?? '')),
            'field_action_plan' => [
                'early_day' => trim((string) data_get($u, 'plan.morning', '')),
                'midday' => trim((string) data_get($u, 'plan.afternoon', '')),
                'late_day' => trim((string) data_get($u, 'plan.evening', '')),
            ],
            'drainage_irrigation_advice' => trim((string) ($u['water'] ?? '')),
            'what_to_avoid_today' => trim((string) ($u['avoid'] ?? '')),
            'rainfall_risk_level' => $risk,
            'risk' => $risk,
            'ai_status' => 'success',
            'ai_model' => (string) ($meta['model'] ?? $modelName),
            'ai_error' => '',
        ];
    }

    /**
     * Map unified advisory to legacy farm-map.js structure.
     *
     * @return array<string, mixed>
     */
    public function formatMapAdvisory(array $runResult, string $generatedAtIso): array
    {
        $meta = $runResult['_meta'] ?? [];
        $status = strtolower((string) ($meta['ai_status'] ?? 'failed'));

        if ($status !== 'success') {
            return [
                'status' => 'unavailable',
                'risk_level' => 'low',
                'smart_action' => '',
                'advice_summary' => '',
                'map_insight' => [],
                'what_to_do' => [],
                'what_to_watch' => [],
                'what_to_avoid' => [],
                'why_this_matters' => '',
                'generated_at' => $generatedAtIso,
            ];
        }

        $u = $runResult['unified'] ?? [];
        if (! is_array($u)) {
            return [
                'status' => 'unavailable',
                'risk_level' => 'low',
                'smart_action' => '',
                'advice_summary' => '',
                'map_insight' => [],
                'what_to_do' => [],
                'what_to_watch' => [],
                'what_to_avoid' => [],
                'why_this_matters' => '',
                'generated_at' => $generatedAtIso,
            ];
        }

        $risk = strtolower($this->riskTitleCase((string) ($u['risk'] ?? 'low')));
        $summary = trim((string) ($u['summary'] ?? ''));
        $insight = trim((string) ($u['insight'] ?? ''));
        $m = trim((string) data_get($u, 'plan.morning', ''));
        $a = trim((string) data_get($u, 'plan.afternoon', ''));
        $e = trim((string) data_get($u, 'plan.evening', ''));
        $avoid = trim((string) ($u['avoid'] ?? ''));
        $water = trim((string) ($u['water'] ?? ''));

        $whatToDo = array_values(array_filter([$m, $a], fn ($s) => is_string($s) && $s !== ''));
        $whatToWatch = $e !== '' ? [$e] : [];
        $whatToAvoid = $avoid !== '' ? [$avoid] : [];

        return [
            'status' => 'active',
            'risk_level' => $risk,
            'smart_action' => $summary,
            'advice_summary' => $insight !== '' ? $insight : $summary,
            'map_insight' => [],
            'what_to_do' => $whatToDo,
            'what_to_watch' => $whatToWatch,
            'what_to_avoid' => $whatToAvoid,
            'why_this_matters' => $water,
            'generated_at' => $generatedAtIso,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $forecast
     * @param  list<array<string, mixed>>  $hourlyForecast
     * @return array<string, mixed>
     */
    public function buildWeatherInput(
        User $user,
        ?array $weather,
        array $forecast,
        array $hourlyForecast,
        string $locationDisplay,
        mixed $rainProbDisplay,
        mixed $todayRainfallMm,
        mixed $weekRainfallMm,
        mixed $monthRainfallMm
    ): array {
        $hourlyRows = array_slice(array_values($hourlyForecast), 0, 8);
        $hourlyForAi = array_map(static function (array $h): array {
            return [
                'time_local' => (string) ($h['time'] ?? ''),
                'rain_chance_pct' => isset($h['pop']) && is_numeric($h['pop']) ? (int) round((float) $h['pop']) : null,
                'temp_c' => isset($h['temp']) && is_numeric($h['temp']) ? (int) round((float) $h['temp']) : null,
                'condition_id' => isset($h['condition_id']) && is_numeric($h['condition_id']) ? (int) $h['condition_id'] : null,
            ];
        }, $hourlyRows);

        $popsFive = array_filter(array_column(array_slice($forecast, 0, 5), 'pop'), static fn ($v) => is_numeric($v));
        $maxPopFive = $popsFive !== [] ? (int) round((float) max($popsFive)) : null;

        $forecastNextDays = array_map(static function (array $day): array {
            return [
                'day_name' => (string) ($day['day_name'] ?? ''),
                'date' => (string) ($day['date'] ?? ''),
                'condition' => (string) ($day['condition']['main'] ?? ($day['condition']['description'] ?? '')),
                'temp_min_c' => isset($day['temp_min']) && is_numeric($day['temp_min']) ? round((float) $day['temp_min'], 1) : null,
                'temp_max_c' => isset($day['temp_max']) && is_numeric($day['temp_max']) ? round((float) $day['temp_max'], 1) : null,
                'rain_chance_pct' => isset($day['pop']) && is_numeric($day['pop']) ? (int) round((float) $day['pop']) : null,
                'expected_rain_mm' => isset($day['rain_mm']) && is_numeric($day['rain_mm']) ? round((float) $day['rain_mm'], 1) : null,
            ];
        }, array_slice($forecast, 0, 5));

        $stageKey = $this->cropTimeline->normalizeStageKey((string) ($user->farming_stage ?? ''));
        $stageLabel = CropTimelineService::STAGE_LABELS[$stageKey] ?? trim((string) ($user->farming_stage ?? ''));

        $w = is_array($weather) ? $weather : [];

        return [
            'task' => 'weather_page_advisory',
            'farm_name' => trim((string) ($user->name ?? 'Farmer')).' Farm',
            'location' => $locationDisplay,
            'barangay' => trim((string) ($user->farm_barangay_name ?? '')),
            'municipality' => trim((string) ($user->farm_municipality ?? 'Amulung')),
            'crop_type' => trim((string) ($user->crop_type ?? '')),
            'growth_stage' => trim((string) ($user->farming_stage ?? '')),
            'farming_stage_label' => $stageLabel,
            'current_weather' => [
                'condition' => (string) ($w['condition']['main'] ?? ($w['condition']['description'] ?? 'Unknown')),
                'temperature_c' => isset($w['temp']) && is_numeric($w['temp']) ? round((float) $w['temp'], 1) : null,
                'humidity_pct' => isset($w['humidity']) && is_numeric($w['humidity']) ? (int) round((float) $w['humidity']) : null,
                'rain_chance_pct' => is_numeric($rainProbDisplay) ? (int) round((float) $rainProbDisplay) : null,
                'wind_speed_kmh' => isset($w['wind_speed']) && is_numeric($w['wind_speed']) ? round((float) $w['wind_speed'], 1) : null,
                'today_expected_rainfall_mm' => is_numeric($todayRainfallMm) ? round((float) $todayRainfallMm, 1) : null,
            ],
            'rainfall' => [
                'today_mm' => is_numeric($todayRainfallMm) ? round((float) $todayRainfallMm, 1) : null,
                'week_mm' => is_numeric($weekRainfallMm) ? round((float) $weekRainfallMm, 1) : null,
                'month_mm' => is_numeric($monthRainfallMm) ? round((float) $monthRainfallMm, 1) : null,
                'max_rain_chance_next_5_days_pct' => $maxPopFive,
            ],
            'forecast_next_days' => $forecastNextDays,
            'hourly_next_slots' => $hourlyForAi,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $forecast
     * @param  list<array<string, mixed>>  $monthlyTrend
     * @param  list<array<string, mixed>>  $yearlyTotals
     * @param  list<array<string, mixed>>  $heavyRainfallByYear
     * @return array<string, mixed>
     */
    public function buildRainfallInput(
        User $user,
        string $farmLocationDisplay,
        array $weather,
        array $forecast,
        array $monthlyTrend,
        array $yearlyTotals,
        array $heavyRainfallByYear,
        string $trendDirection,
        mixed $todayRainChance,
        mixed $todayRainMm,
        mixed $weekMm,
        mixed $monthMm,
        int $heavyRainfallTotal
    ): array {
        $w = $weather;
        $stageKey = $this->cropTimeline->normalizeStageKey((string) ($user->farming_stage ?? ''));
        $stageLabel = CropTimelineService::STAGE_LABELS[$stageKey] ?? trim((string) ($user->farming_stage ?? ''));

        $forecastSlice = array_map(static function (array $day): array {
            return [
                'day_name' => (string) ($day['day_name'] ?? ''),
                'rain_chance_pct' => isset($day['pop']) && is_numeric($day['pop']) ? (int) round((float) $day['pop']) : null,
                'expected_rain_mm' => isset($day['rain_mm']) && is_numeric($day['rain_mm']) ? round((float) $day['rain_mm'], 1) : null,
                'condition' => (string) ($day['condition']['main'] ?? ($day['condition']['description'] ?? '')),
            ];
        }, array_slice($forecast, 0, 5));

        $monthlyForAi = array_map(static function (array $row): array {
            return [
                'month' => (string) ($row['month'] ?? ''),
                'avg_rainfall_mm' => isset($row['avg_rainfall']) && is_numeric($row['avg_rainfall']) ? round((float) $row['avg_rainfall'], 2) : null,
            ];
        }, $monthlyTrend);

        $yearlySlice = array_slice(array_values($yearlyTotals), -12);
        $yearlyForAi = array_map(static function (array $row): array {
            return [
                'year' => (string) ($row['year'] ?? ''),
                'total_rainfall_mm' => isset($row['total_rainfall']) && is_numeric($row['total_rainfall']) ? round((float) $row['total_rainfall'], 1) : null,
            ];
        }, $yearlySlice);

        $heavyForAi = array_map(static function (array $row): array {
            return [
                'year' => (string) ($row['year'] ?? ''),
                'heavy_rain_days' => isset($row['heavy_rain_days']) ? (int) $row['heavy_rain_days'] : null,
            ];
        }, array_slice($heavyRainfallByYear, -12));

        return [
            'task' => 'rainfall_trends_advisory',
            'location' => $farmLocationDisplay,
            'barangay' => trim((string) ($user->farm_barangay_name ?? '')),
            'municipality' => trim((string) ($user->farm_municipality ?? 'Amulung')),
            'crop_type' => trim((string) ($user->crop_type ?? '')),
            'growth_stage' => trim((string) ($user->farming_stage ?? '')),
            'farming_stage_label' => $stageLabel,
            'historical' => [
                'monthly_average_by_month' => $monthlyForAi,
                'yearly_totals_recent' => $yearlyForAi,
                'heavy_rain_days_by_year_recent' => $heavyForAi,
                'heavy_rain_days_all_time_sum' => $heavyRainfallTotal,
                'monthly_climatology_avg_mm' => is_numeric($monthMm) ? round((float) $monthMm, 1) : null,
                'last_two_months_trend_label' => $trendDirection,
            ],
            'recent_and_current_rainfall' => [
                'today_expected_rainfall_mm' => is_numeric($todayRainMm) ? round((float) $todayRainMm, 2) : null,
                'today_or_forecast_rain_chance_pct' => is_numeric($todayRainChance) ? (int) round((float) $todayRainChance) : null,
                'rough_week_total_mm_from_today' => is_numeric($weekMm) ? round((float) $weekMm, 1) : null,
            ],
            'short_forecast_days' => $forecastSlice,
            'current_weather' => [
                'condition' => is_array($w) ? (string) ($w['condition']['main'] ?? ($w['condition']['description'] ?? 'Unknown')) : 'Unknown',
                'temperature_c' => is_array($w) && isset($w['temp']) && is_numeric($w['temp']) ? round((float) $w['temp'], 1) : null,
                'humidity_pct' => is_array($w) && isset($w['humidity']) && is_numeric($w['humidity']) ? (int) round((float) $w['humidity']) : null,
                'wind_speed_kmh' => is_array($w) && isset($w['wind_speed']) && is_numeric($w['wind_speed']) ? round((float) $w['wind_speed'], 1) : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $runResult
     * @return array<string, mixed>
     */
    public function buildDashboardRawForMeta(array $runResult): array
    {
        return $runResult;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    private function validateAndNormalizeUnified(array $decoded): ?array
    {
        $status = strtolower(trim((string) ($decoded['status'] ?? '')));
        if ($status !== 'active') {
            return null;
        }

        $risk = strtolower(trim((string) ($decoded['risk'] ?? '')));
        if ($risk === 'medium') {
            $risk = 'moderate';
        }
        if (! in_array($risk, ['low', 'moderate', 'high'], true)) {
            return null;
        }

        $confidence = strtolower(trim((string) ($decoded['confidence'] ?? '')));
        if (! in_array($confidence, ['low', 'medium', 'high'], true)) {
            return null;
        }

        $summary = trim((string) ($decoded['summary'] ?? ''));
        $insight = trim((string) ($decoded['insight'] ?? ''));
        $avoid = trim((string) ($decoded['avoid'] ?? ''));
        $water = trim((string) ($decoded['water'] ?? ''));

        if ($summary === '' || $avoid === '' || $water === '') {
            return null;
        }

        $plan = $decoded['plan'] ?? null;
        if (! is_array($plan)) {
            return null;
        }
        $morning = trim((string) ($plan['morning'] ?? ''));
        $afternoon = trim((string) ($plan['afternoon'] ?? ''));
        $evening = trim((string) ($plan['evening'] ?? ''));
        if ($morning === '' || $afternoon === '' || $evening === '') {
            return null;
        }

        return [
            'status' => 'active',
            'risk' => $risk,
            'confidence' => $confidence,
            'summary' => $summary,
            'insight' => $insight,
            'plan' => [
                'morning' => $morning,
                'afternoon' => $afternoon,
                'evening' => $evening,
            ],
            'avoid' => $avoid,
            'water' => $water,
        ];
    }

    private function cropSystemPrompt(): string
    {
        $base = self::UNIFIED_SCHEMA_INSTRUCTION;

        return <<<PROMPT
You are an agricultural advisor for smallholder farmers.
Use only the provided JSON input. Also return CROP PROGRESS fields alongside the unified advisory keys.

{$base}

Additionally include these keys at the top level (same JSON object):
- "current_stage": one of Planting|Early Growth|Vegetative|Flowering|Harvesting
- "next_stage": one of Early Growth|Vegetative|Flowering|Harvesting|Harvesting
- "next_stage_target_date": "YYYY-MM-DD"
- "days_remaining": non-negative integer
- "timeline": array of objects { "stage", "target_date", "estimated_day_count", "status" } with status in completed|current|upcoming
- "timeline_adjustment_reason": one short sentence explaining timeline vs weather (non-empty)
- "main_advice": non-empty paragraph-style summary of today's crop priorities (must not duplicate summary exactly)
- "what_to_do": non-empty string; use newlines between bullet ideas if needed
- "what_to_watch": non-empty string
- "what_to_avoid": non-empty string
- "why_this_matters": non-empty one or two sentences linking weather and stage decisions (plain language)

Do not add markdown, code fences, or extra keys.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>|null
     */
    private function validateAndNormalizeCropResponse(array $decoded): ?array
    {
        $u = $this->validateAndNormalizeUnified($decoded);
        if ($u === null) {
            return null;
        }

        $required = ['current_stage', 'next_stage', 'next_stage_target_date', 'days_remaining', 'timeline', 'timeline_adjustment_reason', 'main_advice', 'what_to_do', 'what_to_watch', 'what_to_avoid', 'why_this_matters'];
        foreach ($required as $k) {
            if (! array_key_exists($k, $decoded)) {
                return null;
            }
        }

        if (! is_array($decoded['timeline']) || $decoded['timeline'] === []) {
            return null;
        }

        foreach (['main_advice', 'what_to_do', 'what_to_watch', 'what_to_avoid', 'timeline_adjustment_reason', 'next_stage_target_date', 'why_this_matters'] as $k) {
            if (trim((string) $decoded[$k]) === '') {
                return null;
            }
        }

        if (! is_numeric($decoded['days_remaining']) || (int) $decoded['days_remaining'] < 0) {
            return null;
        }

        $risk = strtolower(trim((string) ($decoded['risk'] ?? '')));
        if ($risk === 'medium') {
            $risk = 'moderate';
        }
        $riskLabel = match ($risk) {
            'low' => 'Low',
            'high' => 'High',
            'moderate' => 'Moderate',
            default => '',
        };
        if ($riskLabel === '') {
            return null;
        }

        $timeline = [];
        foreach ($decoded['timeline'] as $row) {
            if (! is_array($row)) {
                return null;
            }
            $timeline[] = [
                'stage' => trim((string) ($row['stage'] ?? '')),
                'target_date' => trim((string) ($row['target_date'] ?? '')),
                'estimated_day_count' => is_numeric($row['estimated_day_count'] ?? null) ? (int) $row['estimated_day_count'] : 0,
                'status' => trim((string) ($row['status'] ?? '')),
            ];
        }

        return [
            'current_stage' => trim((string) $decoded['current_stage']),
            'next_stage' => trim((string) $decoded['next_stage']),
            'next_stage_target_date' => trim((string) $decoded['next_stage_target_date']),
            'days_remaining' => (int) $decoded['days_remaining'],
            'timeline' => $timeline,
            'timeline_adjustment_reason' => trim((string) $decoded['timeline_adjustment_reason']),
            'timeline_adjustment_label' => $this->cropTimeline->humanAdjustmentLabel(
                trim((string) $decoded['timeline_adjustment_reason']),
                'On schedule'
            ),
            'main_advice' => trim((string) $decoded['main_advice']),
            'what_to_do' => trim((string) $decoded['what_to_do']),
            'what_to_watch' => trim((string) $decoded['what_to_watch']),
            'what_to_avoid' => trim((string) $decoded['what_to_avoid']),
            'why_this_matters' => trim((string) $decoded['why_this_matters']),
            'risk_level' => $riskLabel,
        ];
    }

    private function systemPromptFor(string $page): string
    {
        $schema = self::UNIFIED_SCHEMA_INSTRUCTION;

        return match ($page) {
            self::PAGE_DASHBOARD => <<<PROMPT
You are AgriGuard’s field advisor for Philippine smallholder farmers (Amulung / Cagayan when location matches).

Focus: overall daily farm summary, weather-aware actions, quick daily plan, general risk.

{$schema}
PROMPT,
            self::PAGE_WEATHER => <<<PROMPT
You are AgriGuard’s weather-focused field advisor.

Focus: current weather impact on farming; spraying, fertilizer, irrigation, and field-work timing; short-term guidance.

{$schema}
PROMPT,
            self::PAGE_RAINFALL => <<<PROMPT
You are AgriGuard’s rainfall-pattern advisor.

Focus: rainfall pattern vs history, irrigation adjustment, drainage readiness, flood awareness, soil moisture.
Map plan.morning → early-day field rhythm, plan.afternoon → midday, plan.evening → late-day.

{$schema}
PROMPT,
            self::PAGE_MAP => <<<PROMPT
You are AgriGuard’s map and location-aware farm advisor.

Focus: safe field access, low-lying / flood awareness, drainage preparation, movement between plots, using map/weather/rainfall context.
Do not paste raw GPS coordinates into farmer-facing strings. Use plain language (“your saved farm pin”, “low-lying areas”).

{$schema}
PROMPT,
            default => $schema,
        };
    }

    private function userInstructionFor(string $page): ?string
    {
        if ($page === self::PAGE_MAP) {
            return <<<'TXT'
The input JSON includes map context (selected layer, overlays, weather at pin, rainfall overlay labels, flood overlay).
Write guidance a farmer can act on today. Never mention JSON keys in the output text.
TXT;
        }

        return null;
    }

    private function decodeJsonObject(string $raw): ?array
    {
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $t, $m)) {
            $t = trim($m[1]);
        }
        $start = strpos($t, '{');
        $end = strrpos($t, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }
        $t = trim(substr($t, $start, ($end - $start) + 1));
        try {
            $decoded = json_decode($t, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $unified
     */
    private function containsUnrelatedCropMentions(array $unified, string $cropType): bool
    {
        $target = strtolower(trim($cropType));
        if ($target === '') {
            return true;
        }

        $texts = [
            (string) ($unified['summary'] ?? ''),
            (string) ($unified['insight'] ?? ''),
            (string) ($unified['avoid'] ?? ''),
            (string) ($unified['water'] ?? ''),
            (string) data_get($unified, 'plan.morning', ''),
            (string) data_get($unified, 'plan.afternoon', ''),
            (string) data_get($unified, 'plan.evening', ''),
        ];
        $joined = strtolower(implode(' ', $texts));

        $cropTerms = [
            'rice' => ['rice'],
            'corn' => ['corn', 'maize'],
            'vegetables' => ['vegetable', 'vegetables', 'tomato', 'eggplant', 'pepper', 'leafy'],
        ];

        $targetKey = str_contains($target, 'rice') ? 'rice'
            : (str_contains($target, 'corn') ? 'corn'
                : ((str_contains($target, 'vegetable') || str_contains($target, 'veggie')) ? 'vegetables' : $target));

        foreach (array_keys($cropTerms) as $key) {
            if ($key === $targetKey) {
                continue;
            }
            foreach ($cropTerms[$key] as $term) {
                if (str_contains($joined, $term)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasCropContext(User $user): bool
    {
        return trim((string) ($user->crop_type ?? '')) !== ''
            && trim((string) ($user->farming_stage ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function payloadFingerprint(string $page, int $userId, array $input): string
    {
        try {
            $json = json_encode(['page' => $page, 'user' => $userId, 'input' => $input], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            $json = serialize([$page, $userId, $input]);
        }

        return substr(hash('sha256', $json), 0, 40);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function remember(string $cacheKey, array $payload): array
    {
        if (($payload['_meta']['ai_status'] ?? '') === 'success') {
            Cache::put($cacheKey, $payload, self::CACHE_TTL_SECONDS);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{_meta: array<string, mixed>}
     */
    private function failureMeta(string $model, string $error, array $extra): array
    {
        return [
            '_meta' => array_merge([
                'ai_status' => 'failed',
                'model' => $model,
                'error' => $error !== '' ? $error : 'Together AI request failed.',
            ], $extra),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboardCard(): array
    {
        return [
            'main_recommendation' => '',
            'action' => '',
            'score' => 0,
            'confidence' => '',
            'ai_confidence' => '',
            'why' => '',
            'plan' => ['morning' => [], 'afternoon' => [], 'evening' => []],
            'today_plan' => ['morning' => '', 'afternoon' => '', 'evening' => ''],
            'avoid' => '',
            'avoid_list' => [],
            'water' => '',
            'water_strategy' => '',
            'risk' => '',
            'ai_status' => 'failed',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyWeatherShape(string $modelName): array
    {
        return [
            'main_recommendation' => '',
            'farm_score' => 0,
            'ai_confidence' => '',
            'why' => '',
            'today_plan' => ['morning' => '', 'afternoon' => '', 'evening' => ''],
            'avoid' => '',
            'water_strategy' => '',
            'risk_level' => '',
            'risk' => '',
            'ai_status' => 'failed',
            'ai_model' => $modelName,
            'ai_error' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyRainfallShape(string $modelName): array
    {
        return [
            'main_rainfall_advice' => '',
            'rainfall_risk_score' => 0,
            'ai_confidence' => '',
            'rainfall_insight' => '',
            'field_action_plan' => ['early_day' => '', 'midday' => '', 'late_day' => ''],
            'drainage_irrigation_advice' => '',
            'what_to_avoid_today' => '',
            'rainfall_risk_level' => '',
            'risk' => '',
            'ai_status' => 'failed',
            'ai_model' => $modelName,
            'ai_error' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCropRecommendation(string $modelName, string $status, string $error): array
    {
        return [
            'current_stage' => '',
            'next_stage' => '',
            'next_stage_target_date' => '',
            'days_remaining' => 0,
            'timeline' => [],
            'timeline_adjustment_reason' => '',
            'timeline_adjustment_label' => '',
            'main_advice' => '',
            'what_to_do' => '',
            'what_to_watch' => '',
            'what_to_avoid' => '',
            'why_this_matters' => '',
            'risk_level' => '',
            'ai_status' => $status,
            'ai_model' => $modelName,
            'ai_error' => $error,
        ];
    }

    private function normalizeConfidenceLabel(string $c): string
    {
        return match (strtolower(trim($c))) {
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };
    }

    private function riskTitleCase(string $risk): string
    {
        $r = strtolower(trim($risk));
        if ($r === 'medium') {
            $r = 'moderate';
        }

        return match ($r) {
            'low' => 'Low',
            'high' => 'High',
            'moderate' => 'Moderate',
            default => '',
        };
    }

    private function riskToScore(string $risk): int
    {
        $r = strtolower(trim($risk));
        if ($r === 'medium') {
            $r = 'moderate';
        }

        return match ($r) {
            'low' => 3,
            'moderate' => 6,
            'high' => 9,
            default => 5,
        };
    }
}
