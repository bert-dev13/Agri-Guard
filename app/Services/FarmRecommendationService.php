<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FarmRecommendationService
{
    private const CACHE_TTL_SECONDS = 7200; // 2 hours
    private const CACHE_VERSION = 'v3';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an agricultural assistant specializing in crop-specific farming advice in the Philippines.

You must generate recommendations strictly based on the provided crop_type, growth_stage, weather data, and rainfall data.

Rules:

* You must generate recommendations strictly based on the provided crop_type and growth_stage.
* Never give generic advice.
* Do not mention crops not included in the input.
* Never infer or substitute a different crop.
* Use weather data and rainfall data from the payload.
* Use short_forecast and risk indicators when available.
* Use simple, farmer-friendly language.
* Keep recommendations short and actionable.
* Return valid JSON only.
* Do not use markdown, code fences, or extra commentary.

Output JSON structure:

{
"dashboard": {
"daily_recommendation": "",
"tasks": [],
"alerts": [],
"water_level_suggestion": "",
"smart_recommendation": {
"action": "",
"score": "",
"confidence": "",
"why": "",
"plan": {
"morning": [],
"afternoon": [],
"evening": []
},
"avoid": [],
"water": "",
"risk": ""
}
},
"weather_page": {
"interpretation": "",
"impact_tags": [],
"best_time_today": "",
"rain_timeline_summary": {
"morning": "",
"afternoon": "",
"evening": ""
}
},
"rainfall_page": {
"trend_insight": "",
"flood_risk_text": "",
"soil_saturation_text": "",
"comparison_text": ""
}
}
PROMPT;

    public function __construct(private readonly TogetherAiService $togetherAiService) {}

    /**
     * Get recommendations from Together AI only.
     */
    public function getRecommendations(User $user, array $payload, string $page): array
    {
        $cacheKey = sprintf('user_%d_ai_recommendation_%s_%s', $user->id, $page, self::CACHE_VERSION);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (! $this->hasRequiredCropContext($payload)) {
            Log::warning('Skipping AI recommendation due to missing crop context', [
                'user_id' => $user->id,
                'crop_type' => data_get($payload, 'crop_type'),
                'growth_stage' => data_get($payload, 'growth_stage'),
            ]);

            return $this->missingContextRecommendation();
        }

        try {
            $result = $this->togetherAiService->generateRecommendation($payload, self::SYSTEM_PROMPT);
            $parsed = $this->parseAndValidateRecommendation($result['raw_content'] ?? '');
            if ($parsed !== null) {
                if ($this->containsUnrelatedCropMentions($parsed, (string) data_get($payload, 'crop_type', ''))) {
                    Log::warning('AI recommendation rejected due to unrelated crop mention', [
                        'user_id' => $user->id,
                        'crop_type' => data_get($payload, 'crop_type'),
                    ]);

                    return $this->aiUnavailableRecommendation('AI response failed crop validation.');
                }

                $parsed['_meta'] = [
                    'ai_status' => 'success',
                    'source' => 'together_ai',
                    'fallback_used' => false,
                    'error' => null,
                    'model' => (string) ($result['model_used'] ?? ''),
                    'requested_at' => (string) ($result['requested_at'] ?? ''),
                ];
                Cache::put($cacheKey, $parsed, self::CACHE_TTL_SECONDS);

                return $parsed;
            }

            Log::error('Together AI returned invalid JSON recommendation', [
                'user_id' => $user->id,
                'raw_content' => (string) ($result['raw_content'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            Log::error('AI recommendation failed', [
                'user_id' => $user->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->aiUnavailableRecommendation($e->getMessage());
        }

        return $this->aiUnavailableRecommendation('Invalid AI response format.');
    }

    /**
     * Build the common payload requested by the AI prompt.
     */
    public function buildPayload(User $user, array $context): array
    {
        $weather = $context['weather'] ?? [];
        $hourly = $context['hourly_summary'] ?? [];
        $shortForecast = $context['short_forecast'] ?? [];
        $rainfall = $context['rainfall_summary'] ?? [];
        $flags = $context['system_flags'] ?? [];

        return [
            'farm_name' => trim((string) ($user->name ?? 'Farmer')) . ' Farm',
            'location' => (string) ($user->farm_location_display ?? 'Amulung, Cagayan'),
            'crop_type' => trim((string) ($user->crop_type ?? '')),
            'growth_stage' => trim((string) ($user->farming_stage ?? '')),
            'weather' => [
                'temperature' => $this->numericOrNull($weather['temperature'] ?? null),
                'humidity' => $this->numericOrNull($weather['humidity'] ?? null),
                'wind_speed' => $this->numericOrNull($weather['wind_speed'] ?? null),
                'condition' => (string) ($weather['condition'] ?? 'Unknown'),
                'rain_chance' => $this->numericOrNull($weather['rain_chance'] ?? null),
                'hourly_summary' => [
                    'morning_rain_chance' => $this->numericOrNull($hourly['morning_rain_chance'] ?? null),
                    'afternoon_rain_chance' => $this->numericOrNull($hourly['afternoon_rain_chance'] ?? null),
                    'evening_rain_chance' => $this->numericOrNull($hourly['evening_rain_chance'] ?? null),
                ],
                'short_forecast' => $this->normalizeShortForecast($shortForecast),
            ],
            'rainfall' => [
                'today_mm' => $this->numericOrNull($rainfall['today_mm'] ?? null),
                'week_mm' => $this->numericOrNull($rainfall['week_mm'] ?? null),
                'month_mm' => $this->numericOrNull($rainfall['month_mm'] ?? null),
                'trend' => (string) ($rainfall['trend'] ?? 'stable'),
            ],
            'system_flags' => [
                'flood_risk' => (bool) ($flags['flood_risk'] ?? false),
                'soil_saturation' => (bool) ($flags['soil_saturation'] ?? false),
                'irrigation_needed' => (bool) ($flags['irrigation_needed'] ?? false),
                'good_for_spraying' => (bool) ($flags['good_for_spraying'] ?? false),
            ],
        ];
    }

    /**
     * Normalize AI dashboard content into the Smart Recommendation card structure.
     */
    public function toSmartRecommendation(array $recommendations): array
    {
        $smart = data_get($recommendations, 'dashboard.smart_recommendation', []);
        if (! is_array($smart)) {
            $smart = [];
        }

        $fallbackAction = trim((string) data_get($recommendations, 'dashboard.daily_recommendation', ''));
        $fallbackTasks = $this->toStringArray(data_get($recommendations, 'dashboard.tasks', []));
        $fallbackAvoid = $this->toStringArray(data_get($recommendations, 'dashboard.alerts', []));
        $fallbackWater = trim((string) data_get($recommendations, 'dashboard.water_level_suggestion', ''));
        $fallbackRisk = $this->inferRiskFromAlerts($fallbackAvoid);

        $plan = is_array($smart['plan'] ?? null) ? $smart['plan'] : [];

        return [
            'action' => $this->filledOrFallback($smart['action'] ?? null, $fallbackAction, 'Check weather before field work today.'),
            'score' => $this->normalizeScore($smart['score'] ?? null),
            'confidence' => $this->normalizeConfidence($smart['confidence'] ?? null),
            'why' => $this->filledOrFallback($smart['why'] ?? null, $fallbackAction, 'Weather and crop-stage data are limited today.'),
            'plan' => [
                'morning' => $this->nonEmptyStringArray($plan['morning'] ?? $fallbackTasks),
                'afternoon' => $this->nonEmptyStringArray($plan['afternoon'] ?? []),
                'evening' => $this->nonEmptyStringArray($plan['evening'] ?? []),
            ],
            'avoid' => $this->nonEmptyStringArray($smart['avoid'] ?? $fallbackAvoid),
            'water' => $this->filledOrFallback($smart['water'] ?? null, $fallbackWater, 'Maintain normal irrigation and monitor rain updates.'),
            'risk' => $this->normalizeRisk($smart['risk'] ?? null, $fallbackRisk),
        ];
    }

    private function parseAndValidateRecommendation(string $rawContent): ?array
    {
        $clean = trim($rawContent);
        $clean = preg_replace('/^```json\s*/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s*```$/', '', $clean) ?? $clean;
        $clean = $this->extractJsonObject($clean);
        if ($clean === '') {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($clean, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        $dashboard = $decoded['dashboard'] ?? null;
        $weatherPage = $decoded['weather_page'] ?? null;
        $rainfallPage = $decoded['rainfall_page'] ?? null;

        if (! is_array($dashboard) || ! is_array($weatherPage) || ! is_array($rainfallPage)) {
            return null;
        }

        return [
            'dashboard' => [
                'daily_recommendation' => (string) ($dashboard['daily_recommendation'] ?? ''),
                'tasks' => $this->toStringArray($dashboard['tasks'] ?? []),
                'alerts' => $this->toStringArray($dashboard['alerts'] ?? []),
                'water_level_suggestion' => (string) ($dashboard['water_level_suggestion'] ?? ''),
                'smart_recommendation' => $this->normalizeSmartRecommendationBlock(
                    $dashboard['smart_recommendation'] ?? ($decoded['smart_recommendation'] ?? [])
                ),
            ],
            'weather_page' => [
                'interpretation' => (string) ($weatherPage['interpretation'] ?? ''),
                'impact_tags' => $this->toStringArray($weatherPage['impact_tags'] ?? []),
                'best_time_today' => (string) ($weatherPage['best_time_today'] ?? ''),
                'rain_timeline_summary' => [
                    'morning' => (string) data_get($weatherPage, 'rain_timeline_summary.morning', ''),
                    'afternoon' => (string) data_get($weatherPage, 'rain_timeline_summary.afternoon', ''),
                    'evening' => (string) data_get($weatherPage, 'rain_timeline_summary.evening', ''),
                ],
            ],
            'rainfall_page' => [
                'trend_insight' => (string) ($rainfallPage['trend_insight'] ?? ''),
                'flood_risk_text' => (string) ($rainfallPage['flood_risk_text'] ?? ''),
                'soil_saturation_text' => (string) ($rainfallPage['soil_saturation_text'] ?? ''),
                'comparison_text' => (string) ($rainfallPage['comparison_text'] ?? ''),
            ],
        ];
    }

    private function hasRequiredCropContext(array $payload): bool
    {
        return trim((string) data_get($payload, 'crop_type', '')) !== ''
            && trim((string) data_get($payload, 'growth_stage', '')) !== '';
    }

    private function missingContextRecommendation(): array
    {
        $message = 'Please update your crop type and growth stage to receive AI recommendations.';

        return [
            'dashboard' => [
                'daily_recommendation' => $message,
                'tasks' => [],
                'alerts' => [],
                'water_level_suggestion' => $message,
                'smart_recommendation' => $this->defaultSmartRecommendation($message),
            ],
            'weather_page' => [
                'interpretation' => $message,
                'impact_tags' => [],
                'best_time_today' => $message,
                'rain_timeline_summary' => [
                    'morning' => $message,
                    'afternoon' => $message,
                    'evening' => $message,
                ],
            ],
            'rainfall_page' => [
                'trend_insight' => $message,
                'flood_risk_text' => $message,
                'soil_saturation_text' => $message,
                'comparison_text' => $message,
            ],
            '_meta' => [
                'ai_status' => 'failed',
                'source' => 'missing_context',
                'fallback_used' => true,
                'error' => $message,
            ],
        ];
    }

    private function aiUnavailableRecommendation(?string $error = null): array
    {
        $errorMessage = trim((string) $error);
        $userMessage = 'AI recommendation is temporarily unavailable due to API connection or model error. Showing backup advice.';

        return [
            'dashboard' => [
                'daily_recommendation' => 'Continue field checks today and adjust tasks based on rain and water level.',
                'tasks' => [
                    'Inspect drainage canals and low-lying plots.',
                    'Monitor field water level before midday.',
                    'Check leaves for early stress or disease signs.',
                ],
                'alerts' => [],
                'water_level_suggestion' => 'Maintain normal irrigation and reduce watering if rain starts.',
                'smart_recommendation' => [
                    'action' => 'Continue field checks and monitor water level today.',
                    'score' => 6,
                    'confidence' => 'Low',
                    'why' => $userMessage,
                    'plan' => [
                        'morning' => ['Inspect drainage canals and low-lying plots.'],
                        'afternoon' => ['Monitor field water level before midday.'],
                        'evening' => ['Check leaves for early stress or disease signs.'],
                    ],
                    'avoid' => [],
                    'water' => 'Maintain normal irrigation and reduce watering if rain starts.',
                    'risk' => 'Moderate',
                ],
            ],
            'weather_page' => [
                'interpretation' => 'Weather advisory is in fallback mode.',
                'impact_tags' => [],
                'best_time_today' => 'Proceed with caution and check updated weather before major field work.',
                'rain_timeline_summary' => [
                    'morning' => 'Check current weather before starting.',
                    'afternoon' => 'Watch for rain changes and adjust irrigation.',
                    'evening' => 'Re-check field water level before end of day.',
                ],
            ],
            'rainfall_page' => [
                'trend_insight' => 'Use current rain chance and recent field condition for decisions.',
                'flood_risk_text' => 'Monitor low-lying areas after prolonged rain.',
                'soil_saturation_text' => 'Check soil moisture before additional watering.',
                'comparison_text' => 'Fallback advisory is active while AI service recovers.',
            ],
            '_meta' => [
                'ai_status' => 'failed',
                'source' => 'fallback',
                'fallback_used' => true,
                'error' => $errorMessage !== '' ? $errorMessage : 'Together AI request failed.',
                'model' => (string) (config('togetherai.model') ?? config('services.togetherai.model', '')),
                'requested_at' => now()->toIso8601String(),
            ],
        ];
    }

    private function containsUnrelatedCropMentions(array $recommendation, string $cropType): bool
    {
        $target = strtolower(trim($cropType));
        if ($target === '') {
            return true;
        }

        $texts = [
            (string) data_get($recommendation, 'dashboard.daily_recommendation', ''),
            (string) data_get($recommendation, 'dashboard.water_level_suggestion', ''),
            implode(' ', data_get($recommendation, 'dashboard.tasks', [])),
            implode(' ', data_get($recommendation, 'dashboard.alerts', [])),
            (string) data_get($recommendation, 'weather_page.interpretation', ''),
            (string) data_get($recommendation, 'weather_page.best_time_today', ''),
            implode(' ', data_get($recommendation, 'weather_page.impact_tags', [])),
            (string) data_get($recommendation, 'weather_page.rain_timeline_summary.morning', ''),
            (string) data_get($recommendation, 'weather_page.rain_timeline_summary.afternoon', ''),
            (string) data_get($recommendation, 'weather_page.rain_timeline_summary.evening', ''),
            (string) data_get($recommendation, 'rainfall_page.trend_insight', ''),
            (string) data_get($recommendation, 'rainfall_page.flood_risk_text', ''),
            (string) data_get($recommendation, 'rainfall_page.soil_saturation_text', ''),
            (string) data_get($recommendation, 'rainfall_page.comparison_text', ''),
        ];
        $joined = strtolower(implode(' ', $texts));

        $cropTerms = [
            'rice' => ['rice'],
            'corn' => ['corn', 'maize'],
            'vegetables' => ['vegetable', 'vegetables', 'tomato', 'eggplant', 'pepper', 'leafy'],
            'vegetable' => ['vegetable', 'vegetables', 'tomato', 'eggplant', 'pepper', 'leafy'],
        ];
        $allKeys = array_keys($cropTerms);

        $targetKey = str_contains($target, 'rice') ? 'rice'
            : (str_contains($target, 'corn') ? 'corn'
                : ((str_contains($target, 'vegetable') || str_contains($target, 'veggie')) ? 'vegetables' : $target));

        foreach ($allKeys as $key) {
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

    private function toStringArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn ($item) => is_scalar($item) ? (string) $item : '',
            $value
        ));
    }

    private function nonEmptyStringArray(mixed $value): array
    {
        return array_values(array_filter($this->toStringArray($value), fn (string $item) => trim($item) !== ''));
    }

    private function normalizeSmartRecommendationBlock(mixed $value): array
    {
        if (! is_array($value)) {
            return $this->defaultSmartRecommendation();
        }

        $plan = is_array($value['plan'] ?? null) ? $value['plan'] : [];

        return [
            'action' => (string) ($value['action'] ?? ''),
            'score' => $this->normalizeScore($value['score'] ?? null),
            'confidence' => $this->normalizeConfidence($value['confidence'] ?? null),
            'why' => (string) ($value['why'] ?? ''),
            'plan' => [
                'morning' => $this->nonEmptyStringArray($plan['morning'] ?? []),
                'afternoon' => $this->nonEmptyStringArray($plan['afternoon'] ?? []),
                'evening' => $this->nonEmptyStringArray($plan['evening'] ?? []),
            ],
            'avoid' => $this->nonEmptyStringArray($value['avoid'] ?? []),
            'water' => (string) ($value['water'] ?? ''),
            'risk' => $this->normalizeRisk($value['risk'] ?? null, 'Moderate'),
        ];
    }

    private function defaultSmartRecommendation(string $action = 'Check weather before field work today.'): array
    {
        return [
            'action' => $action,
            'score' => 5,
            'confidence' => 'Low',
            'why' => 'AI recommendation data is limited right now.',
            'plan' => [
                'morning' => [],
                'afternoon' => [],
                'evening' => [],
            ],
            'avoid' => [],
            'water' => 'Maintain normal irrigation and monitor rain updates.',
            'risk' => 'Moderate',
        ];
    }

    private function normalizeScore(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 5;
        }

        return max(0, min(10, (int) round((float) $value)));
    }

    private function normalizeConfidence(mixed $value): string
    {
        $confidence = strtolower(trim((string) $value));

        return match ($confidence) {
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };
    }

    private function normalizeRisk(mixed $value, string $fallback): string
    {
        $risk = strtolower(trim((string) $value));
        if ($risk === 'low' || $risk === 'moderate' || $risk === 'high') {
            return ucfirst($risk);
        }

        $fallbackRisk = strtolower(trim($fallback));
        if ($fallbackRisk === 'low' || $fallbackRisk === 'moderate' || $fallbackRisk === 'high') {
            return ucfirst($fallbackRisk);
        }

        return 'Moderate';
    }

    private function inferRiskFromAlerts(array $alerts): string
    {
        $text = strtolower(implode(' ', $alerts));
        if ($text === '') {
            return 'Moderate';
        }
        if (str_contains($text, 'flood') || str_contains($text, 'storm') || str_contains($text, 'heavy rain')) {
            return 'High';
        }
        if (str_contains($text, 'watch') || str_contains($text, 'warning')) {
            return 'Moderate';
        }

        return 'Low';
    }

    private function filledOrFallback(mixed $value, string $fallback, string $default): string
    {
        $normalized = trim((string) $value);
        if ($normalized !== '') {
            return $normalized;
        }

        $fallbackNormalized = trim($fallback);
        if ($fallbackNormalized !== '') {
            return $fallbackNormalized;
        }

        return $default;
    }

    private function numericOrNull(mixed $value): float|int|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    private function extractJsonObject(string $content): string
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return '';
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start === false || $end === false || $end < $start) {
            return '';
        }

        return trim(substr($trimmed, $start, ($end - $start) + 1));
    }

    private function normalizeShortForecast(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'day' => trim((string) ($row['day'] ?? '')),
                'condition' => trim((string) ($row['condition'] ?? 'Unknown')),
                'temp_min' => $this->numericOrNull($row['temp_min'] ?? null),
                'temp_max' => $this->numericOrNull($row['temp_max'] ?? null),
                'rain_chance' => $this->numericOrNull($row['rain_chance'] ?? null),
                'wind_speed' => $this->numericOrNull($row['wind_speed'] ?? null),
            ];
        }

        return $rows;
    }
}
