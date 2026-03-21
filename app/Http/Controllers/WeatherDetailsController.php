<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TogetherAiService;
use App\Services\WeatherAdvisoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WeatherDetailsController extends Controller
{
    /**
     * Show the Weather Details page with full forecast, metrics, and charts.
     */
    public function show(WeatherAdvisoryService $weatherService, TogetherAiService $togetherAiService): View
    {
        $user = Auth::user();
        $data = $weatherService->getAdvisoryData($user);

        $weather = $data['weather'] ?? null;
        $forecast = $data['forecast'] ?? [];
        $locationDisplay = $data['location_display'] ?? '—';
        $advisory = $data['advisory'] ?? [];

        if ($weather !== null) {
            $weather['wind_direction_label'] = WeatherAdvisoryService::windDirectionLabel(
                isset($weather['wind_direction']) ? (float) $weather['wind_direction'] : null
            );
        }

        foreach ($forecast as $i => $day) {
            $forecast[$i]['wind_direction_label'] = WeatherAdvisoryService::windDirectionLabel(
                isset($day['wind_deg']) ? (float) $day['wind_deg'] : null
            );
            $forecast[$i]['simple_label'] = self::simpleWeatherLabel($day['condition']['id'] ?? 800);
            $forecast[$i]['simple_icon'] = self::simpleWeatherIcon($day['condition']['id'] ?? 800);
        }

        if ($weather !== null) {
            $weather['simple_label'] = self::simpleWeatherLabel($weather['condition']['id'] ?? 800);
            $weather['simple_icon'] = self::simpleWeatherIcon($weather['condition']['id'] ?? 800);
        }

        $summaryMessage = self::weatherSummaryMessage($forecast);

        $chartLabels = array_map(fn ($d) => $d['day_name'], $forecast);
        $chartTempMin = array_column($forecast, 'temp_min');
        $chartTempMax = array_column($forecast, 'temp_max');
        $chartPop = array_column($forecast, 'pop');
        $chartWind = array_map(fn ($d) => $d['wind_speed'] ?? 0, $forecast);

        $farmLocationDisplay = $user->farm_location_display;

        $charts = $data['charts'] ?? [];
        $smartAdvisory = $data['smart_advisory'] ?? [];
        $forecastRainProb = $data['forecast_rain_probability'] ?? null;
        $rainProbDisplay = $data['rain_probability_display'] ?? $forecastRainProb ?? (empty($forecast) ? null : (int) max(array_column($forecast, 'pop')));

        $farmImpactMessage = self::buildFarmImpactMessage(
            $weather,
            $forecast,
            $rainProbDisplay ?? $forecastRainProb,
            $user->crop_type,
            $user->farming_stage
        );

        $dewPoint = self::estimateDewPoint(
            isset($weather['temp']) ? (float) $weather['temp'] : null,
            isset($weather['humidity']) ? (float) $weather['humidity'] : null
        );
        $cloudCover = self::estimateCloudCover($weather, $forecast);
        $agriInsights = self::buildAgricultureInsights(
            $weather,
            $forecast,
            $rainProbDisplay,
            $user->crop_type,
            $user->farming_stage
        );

        $hourlySummary = $this->summarizeHourlyRainChances($data['hourly_forecast'] ?? []);
        $todayRainfallMm = $weather['today_expected_rainfall'] ?? null;
        $weekRainfallMm = is_numeric($todayRainfallMm) ? ((float) $todayRainfallMm * 7) : null;
        $monthRainfallMm = is_numeric($todayRainfallMm) ? ((float) $todayRainfallMm * 30) : null;

        $smartRecommendation = $this->generateWeatherSmartRecommendation(
            $user,
            $togetherAiService,
            $weather,
            $forecast,
            $hourlySummary,
            $rainProbDisplay,
            $todayRainfallMm,
            $weekRainfallMm,
            $monthRainfallMm,
            $locationDisplay
        );

        return view('user.weather.weather-details', [
            'weather' => $weather,
            'forecast' => $forecast,
            'hourly_forecast' => $data['hourly_forecast'] ?? [],
            'location_display' => $locationDisplay,
            'farm_location_display' => $farmLocationDisplay,
            'advisory' => $advisory,
            'smart_advisory' => $smartAdvisory,
            'forecast_rain_probability' => $forecastRainProb,
            'rain_probability_display' => $rainProbDisplay,
            'farm_impact_message' => $farmImpactMessage,
            'chart_labels' => $chartLabels,
            'chart_temp_min' => $chartTempMin,
            'chart_temp_max' => $chartTempMax,
            'chart_pop' => $chartPop,
            'chart_wind' => $chartWind,
            'summary_message' => $summaryMessage,
            'charts' => $charts,
            'crop_type' => $user->crop_type,
            'weatherData' => $data['weather_data'] ?? [],
            'last_updated' => $data['last_updated'] ?? null,
            'dew_point' => $dewPoint,
            'cloud_cover' => $cloudCover,
            'agri_insights' => $agriInsights,
            'recommendation' => $smartRecommendation['recommendation'],
            'recommendation_failed' => $smartRecommendation['failed'],
        ]);
    }

    private function summarizeHourlyRainChances(array $hourlyForecast): array
    {
        $segments = [
            'morning_rain_chance' => [],
            'afternoon_rain_chance' => [],
            'evening_rain_chance' => [],
        ];

        foreach ($hourlyForecast as $row) {
            $time = (string) ($row['time'] ?? '');
            $hour = is_numeric(substr($time, 0, 2)) ? (int) substr($time, 0, 2) : null;
            $pop = is_numeric($row['pop'] ?? null) ? (float) $row['pop'] : null;

            if ($hour === null || $pop === null) {
                continue;
            }

            if ($hour < 12) {
                $segments['morning_rain_chance'][] = $pop;
            } elseif ($hour < 18) {
                $segments['afternoon_rain_chance'][] = $pop;
            } else {
                $segments['evening_rain_chance'][] = $pop;
            }
        }

        return [
            'morning_rain_chance' => ! empty($segments['morning_rain_chance']) ? (int) round(array_sum($segments['morning_rain_chance']) / count($segments['morning_rain_chance'])) : null,
            'afternoon_rain_chance' => ! empty($segments['afternoon_rain_chance']) ? (int) round(array_sum($segments['afternoon_rain_chance']) / count($segments['afternoon_rain_chance'])) : null,
            'evening_rain_chance' => ! empty($segments['evening_rain_chance']) ? (int) round(array_sum($segments['evening_rain_chance']) / count($segments['evening_rain_chance'])) : null,
        ];
    }

    private function generateWeatherSmartRecommendation(
        User $user,
        TogetherAiService $togetherAiService,
        ?array $weather,
        array $forecast,
        array $hourlySummary,
        mixed $rainProbDisplay,
        mixed $todayRainfallMm,
        mixed $weekRainfallMm,
        mixed $monthRainfallMm,
        string $locationDisplay
    ): array {
        $payload = [
            'crop_type' => $user->crop_type,
            'growth_stage' => $user->farming_stage,
            'farm_location' => $locationDisplay,
            'current_weather' => [
                'condition' => $weather['condition']['main'] ?? ($weather['condition']['description'] ?? 'Unknown'),
                'temperature' => is_numeric($weather['temp'] ?? null) ? (float) $weather['temp'] : null,
                'humidity' => is_numeric($weather['humidity'] ?? null) ? (int) round((float) $weather['humidity']) : null,
                'rain_chance' => is_numeric($rainProbDisplay) ? (int) round((float) $rainProbDisplay) : null,
                'wind_speed' => is_numeric($weather['wind_speed'] ?? null) ? (float) $weather['wind_speed'] : null,
            ],
            'forecast_data' => array_map(static function (array $day): array {
                return [
                    'day' => (string) ($day['day_name'] ?? ''),
                    'condition' => (string) ($day['condition']['main'] ?? ($day['condition']['description'] ?? 'Unknown')),
                    'temp_min' => is_numeric($day['temp_min'] ?? null) ? (float) $day['temp_min'] : null,
                    'temp_max' => is_numeric($day['temp_max'] ?? null) ? (float) $day['temp_max'] : null,
                    'rain_chance' => is_numeric($day['pop'] ?? null) ? (int) round((float) $day['pop']) : null,
                    'wind_speed' => is_numeric($day['wind_speed'] ?? null) ? (float) $day['wind_speed'] : null,
                ];
            }, array_slice($forecast, 0, 5)),
            'hourly_rain_chance' => $hourlySummary,
            'rainfall_indicators' => [
                'today_mm' => is_numeric($todayRainfallMm) ? (float) $todayRainfallMm : null,
                'week_mm' => is_numeric($weekRainfallMm) ? (float) $weekRainfallMm : null,
                'month_mm' => is_numeric($monthRainfallMm) ? (float) $monthRainfallMm : null,
                'flood_risk' => is_numeric($rainProbDisplay) && (int) $rainProbDisplay >= 75,
                'soil_saturation_risk' => is_numeric($monthRainfallMm) && (float) $monthRainfallMm >= 220,
            ],
        ];

        $fallback = $this->weatherRecommendationFallback($payload);
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));

        try {
            $response = $togetherAiService->generateRecommendation($payload, $this->weatherRecommendationPrompt());
            $decoded = $this->decodeRecommendationJson((string) ($response['raw_content'] ?? ''));

            if (! is_array($decoded)) {
                throw new \RuntimeException('Together AI returned malformed JSON payload.');
            }

            return [
                'recommendation' => array_merge(
                    $this->normalizeWeatherRecommendation($decoded, $fallback),
                    [
                        'ai_status' => 'success',
                        'ai_model' => (string) ($response['model_used'] ?? $modelName),
                        'ai_error' => '',
                    ]
                ),
                'failed' => false,
                'meta' => [
                    'ai_status' => 'success',
                    'ai_model' => (string) ($response['model_used'] ?? $modelName),
                    'ai_error' => '',
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('Weather page AI recommendation failed', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'location' => $locationDisplay,
                'payload' => $payload,
            ]);

            return [
                'recommendation' => array_merge(
                    $fallback,
                    [
                        'ai_status' => 'failed',
                        'ai_model' => $modelName,
                        'ai_error' => 'Weather recommendation AI unavailable.',
                    ]
                ),
                'failed' => true,
                'meta' => [
                    'ai_status' => 'failed',
                    'ai_model' => $modelName,
                    'ai_error' => 'Weather recommendation AI unavailable.',
                ],
            ];
        }
    }

    private function weatherRecommendationPrompt(): string
    {
        return <<<'PROMPT'
You are an agricultural weather advisor for smallholder farmers.
Analyze only the provided JSON input and produce one compact recommendation for today.

Return valid JSON only with exactly these keys:
{
  "main_recommendation": "string",
  "farm_score": 1-10 integer,
  "ai_confidence": "Low|Medium|High",
  "why": "string",
  "today_plan": {
    "morning": "string",
    "afternoon": "string",
    "evening": "string"
  },
  "avoid": "string",
  "water_strategy": "string",
  "risk_level": "Low|Moderate|High"
}

Rules:
- Keep language simple for farmers.
- Keep each field concise and actionable.
- Respect crop_type and growth_stage.
- Use weather, humidity, rain chance, wind, forecast_data, and rainfall_indicators.
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

    private function normalizeWeatherRecommendation(array $raw, array $fallback): array
    {
        $farmScore = is_numeric($raw['farm_score'] ?? null) ? (int) round((float) $raw['farm_score']) : (int) $fallback['farm_score'];
        $farmScore = max(1, min(10, $farmScore));

        $confidence = strtolower((string) ($raw['ai_confidence'] ?? ''));
        $confidence = match ($confidence) {
            'high' => 'High',
            'medium', 'med' => 'Medium',
            'low' => 'Low',
            default => $fallback['ai_confidence'],
        };

        $risk = strtolower((string) ($raw['risk_level'] ?? ''));
        $risk = match ($risk) {
            'high' => 'High',
            'moderate', 'medium' => 'Moderate',
            'low' => 'Low',
            default => $fallback['risk_level'],
        };

        $planRaw = is_array($raw['today_plan'] ?? null) ? $raw['today_plan'] : [];

        return [
            'main_recommendation' => trim((string) ($raw['main_recommendation'] ?? '')) !== ''
                ? trim((string) $raw['main_recommendation'])
                : $fallback['main_recommendation'],
            'farm_score' => $farmScore,
            'ai_confidence' => $confidence,
            'why' => trim((string) ($raw['why'] ?? '')) !== ''
                ? trim((string) $raw['why'])
                : $fallback['why'],
            'today_plan' => [
                'morning' => trim((string) ($planRaw['morning'] ?? '')) !== ''
                    ? trim((string) $planRaw['morning'])
                    : $fallback['today_plan']['morning'],
                'afternoon' => trim((string) ($planRaw['afternoon'] ?? '')) !== ''
                    ? trim((string) $planRaw['afternoon'])
                    : $fallback['today_plan']['afternoon'],
                'evening' => trim((string) ($planRaw['evening'] ?? '')) !== ''
                    ? trim((string) $planRaw['evening'])
                    : $fallback['today_plan']['evening'],
            ],
            'avoid' => trim((string) ($raw['avoid'] ?? '')) !== ''
                ? trim((string) $raw['avoid'])
                : $fallback['avoid'],
            'water_strategy' => trim((string) ($raw['water_strategy'] ?? '')) !== ''
                ? trim((string) $raw['water_strategy'])
                : $fallback['water_strategy'],
            'risk_level' => $risk,
        ];
    }

    private function weatherRecommendationFallback(array $payload): array
    {
        $rainChance = $payload['current_weather']['rain_chance'] ?? null;
        $windSpeed = $payload['current_weather']['wind_speed'] ?? null;
        $cropType = (string) ($payload['crop_type'] ?? 'your crop');

        $riskLevel = 'Moderate';
        if (is_numeric($rainChance) && (int) $rainChance >= 75) {
            $riskLevel = 'High';
        } elseif (is_numeric($rainChance) && (int) $rainChance < 35) {
            $riskLevel = 'Low';
        }

        $mainRecommendation = 'Do light farm work early, then monitor weather changes before noon.';
        if ($riskLevel === 'High') {
            $mainRecommendation = 'Prioritize drainage and crop protection, and postpone non-urgent field work.';
        } elseif ($riskLevel === 'Low') {
            $mainRecommendation = 'Good weather window for key field tasks and careful water management.';
        }

        return [
            'main_recommendation' => $mainRecommendation,
            'farm_score' => $riskLevel === 'High' ? 4 : ($riskLevel === 'Low' ? 8 : 6),
            'ai_confidence' => 'Medium',
            'why' => 'Based on current rain chance, wind, and short-term forecast trends for your location.',
            'today_plan' => [
                'morning' => "Inspect {$cropType} field condition and finish priority tasks while weather is manageable.",
                'afternoon' => $riskLevel === 'High'
                    ? 'Limit field exposure and secure drainage lines before heavier rain.'
                    : 'Continue moderate tasks and recheck rain updates.',
                'evening' => 'Prepare tools and field channels for overnight weather changes.',
            ],
            'avoid' => is_numeric($windSpeed) && (float) $windSpeed >= 20
                ? 'Avoid spraying during strong winds and before possible rain.'
                : 'Avoid heavy fertilizer application right before possible rain.',
            'water_strategy' => $riskLevel === 'High'
                ? 'Reduce irrigation and focus on draining excess water from low areas.'
                : ($riskLevel === 'Low'
                    ? 'Use normal irrigation with moisture checks in the late afternoon.'
                    : 'Apply balanced irrigation and adjust based on rain updates.'),
            'risk_level' => $riskLevel,
        ];
    }

    /**
     * Map OpenWeatherMap condition id to simple farmer-friendly label.
     */
    public static function simpleWeatherLabel(int $conditionId): string
    {
        if ($conditionId >= 200 && $conditionId < 300) {
            return 'Storm';
        }
        if ($conditionId >= 300 && $conditionId < 600) {
            return 'Rain';
        }
        if ($conditionId >= 600 && $conditionId < 700) {
            return 'Snow';
        }
        if ($conditionId === 800) {
            return 'Clear';
        }
        if ($conditionId === 801) {
            return 'Partly cloudy';
        }
        if ($conditionId === 802 || $conditionId === 803) {
            return 'Cloudy';
        }
        if ($conditionId === 804) {
            return 'Overcast';
        }
        return 'Cloudy';
    }

    /**
     * Map OpenWeatherMap condition id to Lucide icon name.
     */
    public static function simpleWeatherIcon(int $conditionId): string
    {
        if ($conditionId >= 200 && $conditionId < 300) {
            return 'cloud-lightning';
        }
        if ($conditionId >= 300 && $conditionId < 600) {
            return 'cloud-rain';
        }
        if ($conditionId >= 600 && $conditionId < 700) {
            return 'cloud-snow';
        }
        if ($conditionId === 800) {
            return 'sun';
        }
        if ($conditionId === 801 || $conditionId === 802) {
            return 'cloud-sun';
        }
        return 'cloud';
    }

    /**
     * Build a short farm impact message based on weather and crop.
     */
    public static function buildFarmImpactMessage(?array $weather, array $forecast, ?int $rainProb, ?string $cropType, ?string $farmingStage): string
    {
        $cropLower = $cropType ? strtolower($cropType) : '';
        $condId = $weather['condition']['id'] ?? 800;
        $humidity = $weather['humidity'] ?? 0;
        $windSpeed = (float) ($weather['wind_speed'] ?? 0);

        if ($condId >= 300 && $condId < 600) {
            if (str_contains($cropLower, 'rice')) {
                return 'Rain may affect paddy water level. Check drainage and avoid fertilizer application before heavy rain.';
            }
            if (str_contains($cropLower, 'corn')) {
                return 'Rain may cause runoff. Young corn roots need good soil drainage. Monitor for standing water.';
            }
            if (str_contains($cropLower, 'vegetable')) {
                return 'Rain may damage sensitive vegetables. Protect raised beds and harvest ready produce early if needed.';
            }
            return 'Rain may cause water buildup in low areas. Prepare drainage and monitor field conditions.';
        }

        if ($rainProb !== null && $rainProb >= 60) {
            if ($farmingStage === 'planting' && str_contains($cropLower, 'rice')) {
                return 'Newly planted rice may be vulnerable to washout. Ensure bunds are secure and drainage is clear.';
            }
            if ($farmingStage === 'early_growth' && str_contains($cropLower, 'corn')) {
                return 'Young corn plants may be affected by prolonged wet soil. Watch for root stress.';
            }
            if ($farmingStage === 'harvesting') {
                return 'Harvest-ready crops may need early harvest if heavy rain continues. Avoid spoilage and water damage.';
            }
        }

        if ($humidity >= 80) {
            return 'High humidity may increase moisture-related crop stress. Monitor for disease and ensure good air flow.';
        }

        if ($windSpeed >= 20) {
            return 'Strong winds may affect young corn or vegetable crops. Secure covers and check plant stability.';
        }

        if ($condId === 800 || $condId === 801) {
            return 'Conditions are stable. Light rain may help maintain soil moisture. Good for normal farm activities.';
        }

        return 'Monitor weather and field conditions. Prepare drainage for possible rain.';
    }

    /**
     * Short farmer-friendly weather summary based on forecast.
     */
    public static function weatherSummaryMessage(array $forecast): string
    {
        if (empty($forecast)) {
            return 'Check back later for weather updates.';
        }
        $maxPop = max(array_column($forecast, 'pop'));
        $rainyDays = count(array_filter($forecast, fn ($d) => ($d['pop'] ?? 0) >= 50));
        if ($maxPop >= 70 || $rainyDays >= 2) {
            return 'Rain expected in the next few days. Prepare farm drainage.';
        }
        if ($maxPop >= 40 || $rainyDays >= 1) {
            return 'Some rain possible. Keep an eye on the forecast.';
        }
        return 'Weather looks stable. Low chance of rain.';
    }

    /**
     * Approximate dew point in Celsius using the Magnus formula.
     */
    public static function estimateDewPoint(?float $tempC, ?float $humidityPercent): ?float
    {
        if ($tempC === null || $humidityPercent === null || $humidityPercent <= 0) {
            return null;
        }

        $humidityPercent = max(1.0, min(100.0, $humidityPercent));
        $a = 17.27;
        $b = 237.7;
        $alpha = (($a * $tempC) / ($b + $tempC)) + log($humidityPercent / 100.0);
        $dewPoint = ($b * $alpha) / ($a - $alpha);

        return round($dewPoint, 1);
    }

    /**
     * Estimate cloud cover percentage based on current condition and forecast tendency.
     */
    public static function estimateCloudCover(?array $weather, array $forecast): ?int
    {
        $conditionId = (int) ($weather['condition']['id'] ?? 800);
        $base = match (true) {
            $conditionId === 800 => 10,
            $conditionId === 801 => 30,
            $conditionId === 802 => 55,
            $conditionId === 803 => 75,
            $conditionId === 804 => 90,
            $conditionId >= 200 && $conditionId < 600 => 85,
            default => 60,
        };

        if (empty($forecast)) {
            return $base;
        }

        $avgPop = (int) round(array_sum(array_map(fn ($day) => (int) ($day['pop'] ?? 0), $forecast)) / max(count($forecast), 1));
        $adjusted = (int) round($base + ($avgPop - 30) * 0.25);

        return max(5, min(100, $adjusted));
    }

    /**
     * Build practical weather-to-farming recommendations.
     */
    public static function buildAgricultureInsights(?array $weather, array $forecast, ?int $rainProbDisplay, ?string $cropType, ?string $farmingStage): array
    {
        $crop = $cropType ?: 'Crops';
        $rainChance = $rainProbDisplay ?? ($weather['today_rain_probability'] ?? null);
        $rainMm = $weather['today_expected_rainfall'] ?? null;
        $humidity = $weather['humidity'] ?? null;

        $soilMoisture = 'Normal moisture retention expected. Keep routine field checks.';
        if (is_numeric($rainChance) && (int) $rainChance >= 55) {
            $soilMoisture = 'Rain is likely. Reduce manual watering and improve drainage channels.';
        } elseif (is_numeric($rainMm) && (float) $rainMm >= 5) {
            $soilMoisture = 'Light to moderate rain expected. Monitor low-lying plots for pooling.';
        }

        $irrigation = 'Proceed with standard irrigation schedule.';
        if (is_numeric($rainChance) && (int) $rainChance >= 60) {
            $irrigation = 'Delay irrigation for now and reassess after the next forecast update.';
        } elseif (is_numeric($humidity) && (int) $humidity >= 80) {
            $irrigation = 'Use lighter irrigation cycles to avoid excess moisture stress.';
        }

        $cropSafety = 'Safe for normal farm tasks today.';
        if ($farmingStage === 'planting' && is_numeric($rainChance) && (int) $rainChance <= 60) {
            $cropSafety = "Good window for {$crop} planting. Soil moisture and rainfall outlook are manageable.";
        } elseif (is_numeric($rainChance) && (int) $rainChance >= 70) {
            $cropSafety = 'Moderate risk for planting operations. Protect seedlings and postpone fertilizer spreading.';
        }

        $nextTwoDaysRain = array_sum(array_map(fn ($day) => (int) ($day['pop'] ?? 0), array_slice($forecast, 0, 2)));
        $alert = $nextTwoDaysRain >= 120
            ? 'Rain trend is rising in the next 48 hours. Secure channels and check runoff paths.'
            : 'No severe short-term rain signal. Continue with planned field activities.';

        return [
            'soil_moisture' => $soilMoisture,
            'irrigation' => $irrigation,
            'crop_safety' => $cropSafety,
            'alert' => $alert,
        ];
    }
}
