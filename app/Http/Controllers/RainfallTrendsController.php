<?php

namespace App\Http\Controllers;

use App\Models\HistoricalWeather;
use App\Models\User;
use App\Services\TogetherAiService;
use App\Services\WeatherAdvisoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RainfallTrendsController extends Controller
{
    private const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private const MONTH_FULL = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /**
     * Show the Historical Rainfall Trends page with charts from historical_weather (2014–2024).
     */
    public function show(WeatherAdvisoryService $weatherAdvisoryService, TogetherAiService $togetherAiService): View
    {
        $user = Auth::user();
        $farmLocationDisplay = $this->farmLocationDisplay($user);

        $monthlyTrend = $this->getMonthlyRainfallTrend();
        $yearlyTotals = $this->getYearlyRainfallTotals();
        $heavyRainfallByYear = $this->getHeavyRainfallByYear();

        $avgAnnualRainfall = ! empty($yearlyTotals)
            ? (int) round(array_sum(array_column($yearlyTotals, 'total_rainfall')) / count($yearlyTotals))
            : null;
        $wettestMonth = null;
        if (! empty($monthlyTrend)) {
            $first = collect($monthlyTrend)->sortByDesc('avg_rainfall')->first();
            if ($first && $first['avg_rainfall'] > 0) {
                $idx = array_search($first['month'], self::MONTH_NAMES);
                $wettestMonth = $idx !== false ? self::MONTH_FULL[$idx] : $first['month'];
            }
        }
        $heavyRainfallTotal = array_sum(array_column($heavyRainfallByYear, 'heavy_rain_days'));
        $seasonalInsight = $this->buildSeasonalInsight($monthlyTrend);
        $wettestYear = $this->getWettestYear($yearlyTotals);
        $avgMonthlyRainfall = $this->getAvgMonthlyRainfall($monthlyTrend);
        $dataPeriod = $this->getDataPeriod($yearlyTotals);
        $rainfallInsight = $this->buildRainfallInsight($monthlyTrend, $heavyRainfallByYear);
        $preparationNote = $this->buildPreparationNote($monthlyTrend, $user->crop_type ?? null);
        $farmingStageNote = $this->buildFarmingStageNote($user->farming_stage ?? null);
        try {
            $weatherData = $weatherAdvisoryService->getAdvisoryData($user);
        } catch (\Throwable) {
            $weatherData = [];
        }
        $weather = $weatherData['weather'] ?? [];
        $todayRainChance = $weather['today_rain_probability'] ?? null;
        $todayRainMm = $weather['today_expected_rainfall'] ?? null;
        $weekMm = is_numeric($todayRainMm) ? ((float) $todayRainMm * 7) : null;
        $monthMm = $avgMonthlyRainfall;
        $trend = $this->deriveRainTrend($monthlyTrend);

        $rainfallRecommendation = $this->generateRainfallSmartRecommendation(
            $user,
            $togetherAiService,
            $farmLocationDisplay,
            $weather,
            $monthlyTrend,
            $todayRainChance,
            $todayRainMm,
            $weekMm,
            $monthMm,
            $trend,
            $heavyRainfallTotal
        );

        return view('user.rainfall.rainfall-trends', [
            'farm_location_display' => $farmLocationDisplay,
            'monthly_trend' => $monthlyTrend,
            'yearly_totals' => $yearlyTotals,
            'heavy_rainfall_by_year' => $heavyRainfallByYear,
            'avg_annual_rainfall' => $avgAnnualRainfall,
            'wettest_month' => $wettestMonth,
            'wettest_year' => $wettestYear,
            'avg_monthly_rainfall' => $avgMonthlyRainfall,
            'heavy_rainfall_total' => $heavyRainfallTotal,
            'seasonal_insight' => $seasonalInsight,
            'data_period' => $dataPeriod,
            'rainfall_insight' => $rainfallInsight,
            'preparation_note' => $preparationNote,
            'farming_stage_note' => $farmingStageNote,
            'crop_type' => $user->crop_type ?? null,
            'farming_stage' => $user->farming_stage ?? null,
            'recommendation' => $rainfallRecommendation['recommendation'],
            'recommendation_failed' => $rainfallRecommendation['failed'],
            'trend_insight' => $rainfallRecommendation['recommendation']['rainfall_insight'] ?? $rainfallInsight,
            'flood_risk_text' => $rainfallRecommendation['recommendation']['drainage_irrigation_advice'] ?? 'Monitor low-lying fields during high-rain periods.',
            'soil_saturation_text' => $rainfallRecommendation['recommendation']['drainage_irrigation_advice'] ?? 'Check soil wetness before irrigating.',
            'comparison_text' => $rainfallRecommendation['recommendation']['rainfall_insight'] ?? 'Compare this month with historical records before planning major field work.',
        ]);
    }

    private function generateRainfallSmartRecommendation(
        User $user,
        TogetherAiService $togetherAiService,
        string $farmLocationDisplay,
        array $weather,
        array $monthlyTrend,
        mixed $todayRainChance,
        mixed $todayRainMm,
        mixed $weekMm,
        mixed $monthMm,
        string $trend,
        int $heavyRainfallTotal
    ): array {
        $monthlyValues = array_map(
            static fn (array $row): float => is_numeric($row['avg_rainfall'] ?? null) ? (float) $row['avg_rainfall'] : 0.0,
            $monthlyTrend
        );
        $recentPattern = array_slice($monthlyTrend, -3);

        $payload = [
            'crop_type' => $user->crop_type,
            'growth_stage' => $user->farming_stage,
            'farm_location' => $farmLocationDisplay,
            'current_rainfall_data' => [
                'today_rainfall_mm' => is_numeric($todayRainMm) ? (float) $todayRainMm : null,
                'forecast_rain_chance' => is_numeric($todayRainChance) ? (int) round((float) $todayRainChance) : null,
                'expected_rain_intensity' => is_numeric($todayRainMm)
                    ? ((float) $todayRainMm >= 15 ? 'heavy' : (((float) $todayRainMm >= 6) ? 'moderate' : 'light'))
                    : 'unknown',
            ],
            'recent_rain_pattern' => array_map(static function (array $row): array {
                return [
                    'month' => (string) ($row['month'] ?? ''),
                    'avg_rainfall_mm' => is_numeric($row['avg_rainfall'] ?? null) ? (float) $row['avg_rainfall'] : null,
                ];
            }, $recentPattern),
            'historical_rainfall_trends' => [
                'monthly_average_mm' => is_numeric($monthMm) ? (float) $monthMm : null,
                'yearly_peak_mm' => ! empty($monthlyValues) ? max($monthlyValues) : null,
                'trend_direction' => $trend,
                'heavy_rain_days_total' => $heavyRainfallTotal,
            ],
            'flood_waterlogging_indicators' => [
                'flood_risk' => $heavyRainfallTotal >= 20 || (is_numeric($todayRainChance) && (int) $todayRainChance >= 75),
                'waterlogging_risk' => is_numeric($monthMm) && (float) $monthMm >= 220,
                'weekly_rainfall_mm' => is_numeric($weekMm) ? (float) $weekMm : null,
            ],
            'current_weather_context' => [
                'condition' => $weather['condition']['main'] ?? ($weather['condition']['description'] ?? 'Unknown'),
                'temperature' => is_numeric($weather['temp'] ?? null) ? (float) $weather['temp'] : null,
                'humidity' => is_numeric($weather['humidity'] ?? null) ? (int) round((float) $weather['humidity']) : null,
                'wind_speed' => is_numeric($weather['wind_speed'] ?? null) ? (float) $weather['wind_speed'] : null,
            ],
        ];

        $fallback = $this->rainfallRecommendationFallback($payload);
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));

        try {
            $response = $togetherAiService->generateRecommendation($payload, $this->rainfallRecommendationPrompt());
            $decoded = $this->decodeRecommendationJson((string) ($response['raw_content'] ?? ''));

            if (! is_array($decoded)) {
                throw new \RuntimeException('Together AI returned malformed rainfall JSON payload.');
            }

            return [
                'recommendation' => array_merge(
                    $this->normalizeRainfallRecommendation($decoded, $fallback),
                    [
                        'ai_status' => 'success',
                        'ai_model' => (string) ($response['model_used'] ?? $modelName),
                        'ai_error' => '',
                    ]
                ),
                'failed' => false,
            ];
        } catch (\Throwable $e) {
            Log::error('Rainfall page AI recommendation failed', [
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
                        'ai_error' => 'Rainfall recommendation AI unavailable.',
                    ]
                ),
                'failed' => true,
            ];
        }
    }

    private function rainfallRecommendationPrompt(): string
    {
        return <<<'PROMPT'
You are a rainfall-focused farm advisor for smallholder farmers.
Use only the provided JSON input and return a rainfall-specific recommendation.

Return valid JSON only with exactly these keys:
{
  "main_rainfall_advice": "string",
  "rainfall_risk_score": 1-10 integer,
  "ai_confidence": "Low|Medium|High",
  "rainfall_insight": "string",
  "field_action_plan": {
    "early_day": "string",
    "midday": "string",
    "late_day": "string"
  },
  "drainage_irrigation_advice": "string",
  "what_to_avoid_today": "string",
  "rainfall_risk_level": "Low|Moderate|High"
}

Rules:
- Keep wording simple and practical for farmers.
- Focus on rainfall pattern, trend, intensity, flooding/waterlogging, and irrigation/drainage.
- Respect crop_type and growth_stage vulnerability.
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

    private function normalizeRainfallRecommendation(array $raw, array $fallback): array
    {
        $riskScore = is_numeric($raw['rainfall_risk_score'] ?? null) ? (int) round((float) $raw['rainfall_risk_score']) : (int) $fallback['rainfall_risk_score'];
        $riskScore = max(1, min(10, $riskScore));

        $confidence = strtolower((string) ($raw['ai_confidence'] ?? ''));
        $confidence = match ($confidence) {
            'high' => 'High',
            'medium', 'med' => 'Medium',
            'low' => 'Low',
            default => $fallback['ai_confidence'],
        };

        $riskLevel = strtolower((string) ($raw['rainfall_risk_level'] ?? ''));
        $riskLevel = match ($riskLevel) {
            'high' => 'High',
            'moderate', 'medium' => 'Moderate',
            'low' => 'Low',
            default => $fallback['rainfall_risk_level'],
        };

        $plan = is_array($raw['field_action_plan'] ?? null) ? $raw['field_action_plan'] : [];

        return [
            'main_rainfall_advice' => trim((string) ($raw['main_rainfall_advice'] ?? '')) !== ''
                ? trim((string) $raw['main_rainfall_advice'])
                : $fallback['main_rainfall_advice'],
            'rainfall_risk_score' => $riskScore,
            'ai_confidence' => $confidence,
            'rainfall_insight' => trim((string) ($raw['rainfall_insight'] ?? '')) !== ''
                ? trim((string) $raw['rainfall_insight'])
                : $fallback['rainfall_insight'],
            'field_action_plan' => [
                'early_day' => trim((string) ($plan['early_day'] ?? '')) !== ''
                    ? trim((string) $plan['early_day'])
                    : $fallback['field_action_plan']['early_day'],
                'midday' => trim((string) ($plan['midday'] ?? '')) !== ''
                    ? trim((string) $plan['midday'])
                    : $fallback['field_action_plan']['midday'],
                'late_day' => trim((string) ($plan['late_day'] ?? '')) !== ''
                    ? trim((string) $plan['late_day'])
                    : $fallback['field_action_plan']['late_day'],
            ],
            'drainage_irrigation_advice' => trim((string) ($raw['drainage_irrigation_advice'] ?? '')) !== ''
                ? trim((string) $raw['drainage_irrigation_advice'])
                : $fallback['drainage_irrigation_advice'],
            'what_to_avoid_today' => trim((string) ($raw['what_to_avoid_today'] ?? '')) !== ''
                ? trim((string) $raw['what_to_avoid_today'])
                : $fallback['what_to_avoid_today'],
            'rainfall_risk_level' => $riskLevel,
        ];
    }

    private function rainfallRecommendationFallback(array $payload): array
    {
        $rainChance = $payload['current_rainfall_data']['forecast_rain_chance'] ?? null;
        $todayRainMm = $payload['current_rainfall_data']['today_rainfall_mm'] ?? null;
        $crop = (string) ($payload['crop_type'] ?? 'your crop');
        $growthStage = (string) ($payload['growth_stage'] ?? 'current stage');

        $riskLevel = 'Moderate';
        if ((is_numeric($rainChance) && (int) $rainChance >= 75) || (is_numeric($todayRainMm) && (float) $todayRainMm >= 15)) {
            $riskLevel = 'High';
        } elseif ((is_numeric($rainChance) && (int) $rainChance < 35) && (is_numeric($todayRainMm) && (float) $todayRainMm < 5)) {
            $riskLevel = 'Low';
        }

        return [
            'main_rainfall_advice' => $riskLevel === 'High'
                ? 'Prioritize drainage checks and delay non-urgent field operations.'
                : ($riskLevel === 'Low'
                    ? 'Use the lighter rain window to complete priority field tasks with controlled irrigation.'
                    : 'Adjust field work by tracking rain updates and preparing drainage in low areas.'),
            'rainfall_risk_score' => $riskLevel === 'High' ? 8 : ($riskLevel === 'Low' ? 3 : 5),
            'ai_confidence' => 'Medium',
            'rainfall_insight' => "Rain pattern and recent trend suggest careful water management for {$crop} during {$growthStage}.",
            'field_action_plan' => [
                'early_day' => 'Inspect canals and runoff paths before starting tasks.',
                'midday' => $riskLevel === 'High'
                    ? 'Limit heavy field work and monitor standing water.'
                    : 'Continue planned tasks and check rainfall updates.',
                'late_day' => 'Recheck moisture and secure field exits for overnight rain.',
            ],
            'drainage_irrigation_advice' => $riskLevel === 'High'
                ? 'Pause extra irrigation and clear drainage channels to prevent waterlogging.'
                : ($riskLevel === 'Low'
                    ? 'Maintain normal irrigation and target dry patches only.'
                    : 'Use moderate irrigation and prioritize drainage readiness.'),
            'what_to_avoid_today' => $riskLevel === 'High'
                ? 'Avoid fertilizer application and deep tillage before heavy rain.'
                : 'Avoid overwatering and unnecessary flood irrigation.',
            'rainfall_risk_level' => $riskLevel,
        ];
    }

    private function deriveRainTrend(array $monthlyTrend): string
    {
        if (count($monthlyTrend) < 2) {
            return 'stable';
        }

        $recent = array_slice($monthlyTrend, -2);
        $prev = (float) ($recent[0]['avg_rainfall'] ?? 0);
        $last = (float) ($recent[1]['avg_rainfall'] ?? 0);

        if ($last > $prev * 1.1) {
            return 'increasing';
        }
        if ($last < $prev * 0.9) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Build seasonal insight from monthly rainfall data.
     */
    private function buildSeasonalInsight(array $monthlyTrend): ?string
    {
        if (empty($monthlyTrend)) {
            return null;
        }
        $byMonth = [];
        foreach ($monthlyTrend as $row) {
            $idx = array_search($row['month'], self::MONTH_NAMES);
            if ($idx !== false) {
                $byMonth[$idx + 1] = (float) ($row['avg_rainfall'] ?? 0);
            }
        }
        if (empty($byMonth)) {
            return null;
        }
        $avg = array_sum($byMonth) / count($byMonth);
        $wetMonths = [];
        foreach ($byMonth as $m => $rain) {
            if ($rain >= $avg * 1.2) {
                $wetMonths[] = self::MONTH_FULL[$m - 1];
            }
        }
        if (empty($wetMonths)) {
            return 'Based on historical records, prepare drainage and monitor flood-prone fields during the rainy season.';
        }
        $range = count($wetMonths) >= 3
            ? $wetMonths[0] . ' to ' . end($wetMonths)
            : implode(', ', $wetMonths);
        return 'Based on historical records, heavy rainfall is more frequent from ' . $range . '. Strengthen drainage and monitor flood-prone fields during these months.';
    }

    /**
     * Average rainfall per month (all years). Returns all 12 months with month labels.
     */
    private function getMonthlyRainfallTrend(): array
    {
        $rows = HistoricalWeather::monthlyRainfallTrend();
        $byMonth = [];
        foreach ($rows as $row) {
            $m = (int) $row->month;
            $byMonth[$m] = round((float) $row->avg_rain, 2);
        }
        $data = [];
        for ($m = 1; $m <= 12; $m++) {
            $data[] = [
                'month' => self::MONTH_NAMES[$m - 1],
                'avg_rainfall' => $byMonth[$m] ?? 0,
            ];
        }
        return $data;
    }

    /**
     * Total rainfall per year.
     */
    private function getYearlyRainfallTotals(): array
    {
        $rows = HistoricalWeather::totalRainfallByYear();
        return $rows->map(fn ($row) => [
            'year' => (string) $row->year,
            'total_rainfall' => round((float) $row->total_rainfall, 2),
        ])->values()->all();
    }

    /**
     * Heavy rainfall frequency by year (rainfall >= 50 mm).
     */
    private function getHeavyRainfallByYear(): array
    {
        $rows = HistoricalWeather::heavyRainfallByYear();
        return $rows->map(fn ($row) => [
            'year' => (string) $row->year,
            'heavy_rain_days' => (int) $row->count,
        ])->values()->all();
    }

    private function getWettestYear(array $yearlyTotals): ?string
    {
        if (empty($yearlyTotals)) {
            return null;
        }
        $wettest = collect($yearlyTotals)->sortByDesc('total_rainfall')->first();

        return $wettest ? (string) $wettest['year'] : null;
    }

    private function getAvgMonthlyRainfall(array $monthlyTrend): ?float
    {
        if (empty($monthlyTrend)) {
            return null;
        }
        $sum = array_sum(array_column($monthlyTrend, 'avg_rainfall'));

        return round($sum / 12, 1);
    }

    private function getDataPeriod(array $yearlyTotals): string
    {
        if (empty($yearlyTotals)) {
            return '—';
        }
        $years = array_column($yearlyTotals, 'year');

        return min($years) . '–' . max($years);
    }

    /**
     * Build a short, farmer-friendly rainfall insight from the data.
     */
    private function buildRainfallInsight(array $monthlyTrend, array $heavyRainfallByYear): string
    {
        if (empty($monthlyTrend)) {
            return 'Historical rainfall data helps you plan for the rainy season. Prepare drainage and monitor flood-prone areas.';
        }
        $byMonth = [];
        foreach ($monthlyTrend as $row) {
            $idx = array_search($row['month'], self::MONTH_NAMES);
            if ($idx !== false) {
                $byMonth[$idx + 1] = (float) ($row['avg_rainfall'] ?? 0);
            }
        }
        if (empty($byMonth)) {
            return 'Use the charts above to see which months usually have more rain. Prepare drainage before peak rainfall months.';
        }
        $sorted = collect($byMonth)->sortByDesc(fn ($v) => $v)->take(3)->keys()->all();
        sort($sorted);
        $monthNames = array_map(fn ($m) => self::MONTH_FULL[$m - 1], $sorted);
        $peakStr = count($monthNames) >= 2
            ? implode(', ', array_slice($monthNames, 0, -1)) . ' and ' . end($monthNames)
            : ($monthNames[0] ?? 'wet season');
        $heavyTotal = array_sum(array_column($heavyRainfallByYear, 'heavy_rain_days'));
        $rangeStr = count($monthNames) >= 3 ? $monthNames[0] . ' to ' . end($monthNames) : $peakStr;
        $sentence = 'Rainfall in this area usually increases during ' . $rangeStr . '. ';
        $sentence .= $peakStr . ' show the highest average rainfall based on historical records. ';
        if ($heavyTotal > 0) {
            $sentence .= 'Heavy rainfall events are more common during the wet season. Farmers should prepare drainage systems before peak rainfall months.';
        } else {
            $sentence .= 'Prepare drainage and monitor low-lying fields before the rainy months.';
        }

        return $sentence;
    }

    /**
     * Build seasonal preparation note, optionally crop-aware.
     */
    private function buildPreparationNote(array $monthlyTrend, ?string $cropType): string
    {
        $base = 'Based on historical rainfall patterns, farmers are advised to clean drainage canals and prepare flood protection before the rainy months begin. Inspect irrigation channels before the wet season. Monitor low-lying fields during months with high rainfall.';
        $cropLower = $cropType ? strtolower($cropType) : '';
        if (str_contains($cropLower, 'rice')) {
            return $base . ' For rice farms: focus on water control, paddy bunds, and paddy drainage to avoid flooding during peak rain.';
        }
        if (str_contains($cropLower, 'corn')) {
            return $base . ' For corn: manage runoff and avoid excess standing water to protect roots.';
        }
        if (str_contains($cropLower, 'vegetable') || str_contains($cropLower, 'vegetables')) {
            return $base . ' For vegetables: use raised beds where possible and protect sensitive crops; prepare early for prolonged rain.';
        }

        return $base;
    }

    /**
     * Optional farming-stage-aware note.
     */
    private function buildFarmingStageNote(?string $farmingStage): ?string
    {
        if (! $farmingStage) {
            return null;
        }
        $stageMessages = [
            'land_preparation' => 'You are in land preparation. Prepare seedbeds and drainage before wet months.',
            'planting' => 'You are in the planting stage. Plan seed protection before peak rainfall months.',
            'early_growth' => 'You are in early growth. Monitor water accumulation during high-rain periods.',
            'growing' => 'You are in the growing stage. Monitor water accumulation during high-rain periods.',
            'flowering_fruiting' => 'You are in flowering or fruiting. Protect crops from waterlogging during wet months.',
            'harvesting' => 'You are in the harvesting stage. Plan early harvest if rainfall is historically high this season.',
        ];

        return $stageMessages[$farmingStage] ?? null;
    }

    private function farmLocationDisplay($user): string
    {
        return $user->farm_location_display;
    }
}
