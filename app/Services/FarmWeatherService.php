<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for weather data in AGRIGUARD.
 *
 * All pages (Dashboard, Weather Details, Advisory, API) must use this service
 * so that the same location, date, and forecast produce the same values everywhere.
 */
class FarmWeatherService
{
    private const FORECAST_TIMEZONE = 'Asia/Manila';
    private const CACHE_KEY_PREFIX = 'agriguard_weather_';
    private const CACHE_COORDS_PREFIX = 'agriguard_farm_coords_';
    private const CACHE_COORDS_WEATHER_PREFIX = 'agriguard_weather_coords_';
    private const CACHE_TTL_MINUTES = 15;

    /**
     * Get normalized weather data for the user's farm location.
     * Uses cache; all pages share the same cached record for the same user.
     *
     * @return array{location_name: string, current_temperature: int|float|null, condition: string|null, feels_like: int|float|null, humidity: int|null, wind_speed: float|null, pressure: int|null, visibility_km: float|null, uv_index: float|null, today_rain_probability: int|null, today_expected_rainfall: float|null, sunrise: string|null, sunset: string|null, hourly_forecast: array, daily_forecast: array, last_updated: string|null, condition_id: int|null, wind_direction: float|null, raw_current: array|null, raw_daily_for_advisory: array}
     */
    public function getNormalizedWeatherForUser(User $user): array
    {
        $cacheKey = self::CACHE_KEY_PREFIX . $user->id;

        return Cache::remember($cacheKey, self::CACHE_TTL_MINUTES * 60, function () use ($user) {
            return $this->fetchAndNormalize($user);
        });
    }

    /**
     * Force refresh (bypass cache). Used after farm location change.
     */
    public function refreshWeatherForUser(User $user): array
    {
        $this->invalidateCacheForUser($user);
        return $this->getNormalizedWeatherForUser($user);
    }

    /**
     * Invalidate cached weather and farm coordinates when farm location or settings change.
     */
    public function invalidateCacheForUser(User $user): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . $user->id);
        Cache::forget(self::CACHE_COORDS_PREFIX . $user->id);
    }

    /**
     * Get farm coordinates (lat, lon) for the user. Geocodes and caches if not already available.
     * Use when you need coordinates without full weather (e.g. map display). Coordinates from
     * getNormalizedWeatherForUser are preferred when weather is available.
     *
     * @return array{lat: float, lon: float, location_name: string}|null
     */
    public function getCoordinatesForUser(User $user): ?array
    {
        $cacheKey = self::CACHE_COORDS_PREFIX . $user->id;

        return Cache::remember($cacheKey, self::CACHE_TTL_MINUTES * 60, function () use ($user) {
            $apiKey = config('services.openweathermap.key') ?: env('OPENWEATHERMAP_API_KEY');
            if (empty($apiKey)) {
                return null;
            }

            $locationQuery = implode(', ', array_filter([
                $user->farm_municipality,
                'Cagayan',
                'Philippines',
            ]));

            if (empty(trim(str_replace(',', '', $locationQuery)))) {
                return null;
            }

            try {
                $geoRes = Http::timeout(10)->get('https://api.openweathermap.org/geo/1.0/direct', [
                    'q' => $locationQuery,
                    'limit' => 1,
                    'appid' => $apiKey,
                ]);

                if (! $geoRes->successful() || empty($geoRes->json())) {
                    return null;
                }

                $geo = $geoRes->json()[0];
                return [
                    'lat' => (float) $geo['lat'],
                    'lon' => (float) $geo['lon'],
                    'location_name' => $geo['name'] ?? ($user->farm_municipality ?: '—'),
                ];
            } catch (\Throwable $e) {
                Log::warning('FarmWeatherService: geocode for coords failed', ['message' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Fetch from API and return normalized structure.
     * Do not default rain probability to 0 when API does not provide it.
     */
    private function fetchAndNormalize(User $user): array
    {
        $apiKey = config('services.openweathermap.key') ?: env('OPENWEATHERMAP_API_KEY');
        if (empty($apiKey)) {
            Log::warning('FarmWeatherService: OPENWEATHERMAP_API_KEY not set');
            return $this->emptyNormalizedPayload($user);
        }

        $locationQuery = implode(', ', array_filter([
            $user->farm_municipality,
            'Cagayan',
            'Philippines',
        ]));

        if (empty(trim(str_replace(',', '', $locationQuery)))) {
            return $this->emptyNormalizedPayload($user);
        }

        try {
            $geoRes = Http::timeout(10)->get('https://api.openweathermap.org/geo/1.0/direct', [
                'q' => $locationQuery,
                'limit' => 1,
                'appid' => $apiKey,
            ]);

            if (! $geoRes->successful() || empty($geoRes->json())) {
                Log::warning('FarmWeatherService: geocoding failed', ['query' => $locationQuery]);
                return $this->emptyNormalizedPayload($user);
            }

            $geo = $geoRes->json()[0];
            $lat = $geo['lat'];
            $lon = $geo['lon'];
            $locationName = $geo['name'] ?? ($user->farm_municipality ?: '—');

            $baseParams = [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey,
                'units' => 'metric',
            ];

            $currentRes = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', $baseParams);
            $forecastRes = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/forecast', array_merge($baseParams, ['cnt' => 40]));

            if (! $currentRes->successful()) {
                Log::warning('FarmWeatherService: current weather request failed');
                return $this->emptyNormalizedPayload($user);
            }

            $current = $currentRes->json();
            $forecastList = $forecastRes->successful() ? ($forecastRes->json()['list'] ?? []) : [];

            $dailyForecast = $this->buildDailyForecasts($forecastList);
            $hourlyForecast = $this->buildHourlyForecast($forecastList);

            // Today's values: from first day of forecast (same date reference for all pages)
            $todayRainProbability = null;
            $todayExpectedRainfall = null;
            if (! empty($dailyForecast)) {
                $firstDay = $dailyForecast[0];
                $todayRainProbability = isset($firstDay['pop']) ? (int) $firstDay['pop'] : null;
                $todayExpectedRainfall = isset($firstDay['rain_mm']) && $firstDay['rain_mm'] > 0
                    ? (float) $firstDay['rain_mm'] : null;
            }

            $conditionId = isset($current['weather'][0]['id']) ? (int) $current['weather'][0]['id'] : null;
            $conditionText = null;
            if (isset($current['weather'][0]['description'])) {
                $conditionText = ucfirst((string) $current['weather'][0]['description']);
            }

            $sunrise = null;
            $sunset = null;
            if (isset($current['sys']['sunrise'])) {
                $sunrise = (new \DateTimeImmutable)->setTimestamp($current['sys']['sunrise'])
                    ->setTimezone(new \DateTimeZone(self::FORECAST_TIMEZONE))
                    ->format('g:i A');
            }
            if (isset($current['sys']['sunset'])) {
                $sunset = (new \DateTimeImmutable)->setTimestamp($current['sys']['sunset'])
                    ->setTimezone(new \DateTimeZone(self::FORECAST_TIMEZONE))
                    ->format('g:i A');
            }

            $currentTemp = isset($current['main']['temp']) ? round((float) $current['main']['temp'], 1) : null;
            $feelsLike = isset($current['main']['feels_like']) ? round((float) $current['main']['feels_like'], 1) : null;
            $humidity = isset($current['main']['humidity']) ? (int) $current['main']['humidity'] : null;
            $pressure = isset($current['main']['pressure']) ? (int) $current['main']['pressure'] : null;
            $visibilityKm = isset($current['visibility']) ? round(((float) $current['visibility']) / 1000, 1) : null;
            $windSpeed = isset($current['wind']['speed']) ? round((float) $current['wind']['speed'] * 3.6, 1) : null;
            $windDirection = isset($current['wind']['deg']) ? (float) $current['wind']['deg'] : null;
            $uvIndex = $this->fetchUvIndex((float) $lat, (float) $lon, (string) $apiKey);

            $lastUpdated = now()->format('Y-m-d H:i:s');

            return [
                'lat' => $lat,
                'lon' => $lon,
                'location_name' => $locationName,
                'current_temperature' => $currentTemp,
                'condition' => $conditionText,
                'condition_id' => $conditionId,
                'feels_like' => $feelsLike,
                'humidity' => $humidity,
                'wind_speed' => $windSpeed,
                'wind_direction' => $windDirection,
                'pressure' => $pressure,
                'visibility_km' => $visibilityKm,
                'uv_index' => $uvIndex,
                'today_rain_probability' => $todayRainProbability,
                'today_expected_rainfall' => $todayExpectedRainfall,
                'sunrise' => $sunrise,
                'sunset' => $sunset,
                'hourly_forecast' => $hourlyForecast,
                'daily_forecast' => $dailyForecast,
                'last_updated' => $lastUpdated,
                'raw_current' => [
                    'temp' => $currentTemp,
                    'feels_like' => $feelsLike,
                    'humidity' => $humidity,
                    'pressure' => $pressure,
                    'visibility_km' => $visibilityKm,
                    'uv_index' => $uvIndex,
                    'wind_speed' => $windSpeed,
                    'wind_direction' => $windDirection,
                    'sunrise_ts' => $current['sys']['sunrise'] ?? null,
                    'sunset_ts' => $current['sys']['sunset'] ?? null,
                    'condition' => [
                        'id' => $conditionId ?? 800,
                        'main' => $current['weather'][0]['main'] ?? 'Clear',
                        'description' => $current['weather'][0]['description'] ?? '',
                        'icon' => $current['weather'][0]['icon'] ?? '01d',
                    ],
                ],
                'raw_daily_for_advisory' => $dailyForecast,
            ];
        } catch (\Throwable $e) {
            Log::error('FarmWeatherService: API error', ['message' => $e->getMessage()]);
            return $this->emptyNormalizedPayload($user);
        }
    }

    private function emptyNormalizedPayload(User $user): array
    {
        $locationName = implode(', ', array_filter([$user->farm_municipality, 'Cagayan', 'Philippines'])) ?: '—';
        return [
            'lat' => null,
            'lon' => null,
            'location_name' => $locationName,
            'current_temperature' => null,
            'condition' => null,
            'condition_id' => null,
            'feels_like' => null,
            'humidity' => null,
            'wind_speed' => null,
            'wind_direction' => null,
            'pressure' => null,
            'visibility_km' => null,
            'uv_index' => null,
            'today_rain_probability' => null,
            'today_expected_rainfall' => null,
            'sunrise' => null,
            'sunset' => null,
            'hourly_forecast' => [],
            'daily_forecast' => [],
            'last_updated' => null,
            'raw_current' => null,
            'raw_daily_for_advisory' => [],
        ];
    }

    private function buildHourlyForecast(array $forecastList): array
    {
        $tz = new \DateTimeZone(self::FORECAST_TIMEZONE);
        $slots = array_slice($forecastList, 0, 8);
        $result = [];
        foreach ($slots as $item) {
            $dt = (new \DateTimeImmutable)->setTimestamp($item['dt'])->setTimezone($tz);
            $weather = $item['weather'][0] ?? ['id' => 800];
            $result[] = [
                'time' => $dt->format('g A'),
                'temp' => (int) round($item['main']['temp'] ?? 0),
                'pop' => isset($item['pop']) ? (int) round((float) $item['pop'] * 100) : null,
                'condition_id' => $weather['id'] ?? 800,
            ];
        }
        return $result;
    }

    private function buildDailyForecasts(array $forecastList): array
    {
        $tz = new \DateTimeZone(self::FORECAST_TIMEZONE);
        $grouped = [];
        foreach ($forecastList as $item) {
            $dt = (new \DateTimeImmutable)->setTimestamp($item['dt'])->setTimezone($tz);
            $date = $dt->format('Y-m-d');
            $grouped[$date][] = $item;
        }
        ksort($grouped);
        $dailyForecasts = [];
        $count = 0;
        foreach ($grouped as $date => $items) {
            if ($count >= 5) {
                break;
            }
            $tempsMin = array_map(fn ($x) => $x['main']['temp_min'] ?? $x['main']['temp'], $items);
            $tempsMax = array_map(fn ($x) => $x['main']['temp_max'] ?? $x['main']['temp'], $items);
            $pops = array_map(fn ($x) => $x['pop'] ?? 0, $items);
            $midIdx = (int) floor(count($items) / 2);
            $midWeather = $items[$midIdx]['weather'][0] ?? ['id' => 800, 'main' => 'Clear', 'description' => '', 'icon' => '01d'];
            $rainMm = 0;
            foreach ($items as $x) {
                $rainMm += (float) ($x['rain']['3h'] ?? $x['rain']['1h'] ?? 0);
            }
            $windSpeeds = [];
            $windDegs = [];
            foreach ($items as $x) {
                if (isset($x['wind']['speed'])) {
                    $windSpeeds[] = (float) $x['wind']['speed'] * 3.6;
                    if (isset($x['wind']['deg'])) {
                        $windDegs[] = (float) $x['wind']['deg'];
                    }
                }
            }
            $windSpeed = ! empty($windSpeeds) ? round(max($windSpeeds), 1) : null;
            $windDegIdx = ! empty($windSpeeds) ? array_search(max($windSpeeds), $windSpeeds) : null;
            $windDeg = ($windDegIdx !== false && isset($windDegs[$windDegIdx])) ? (int) round($windDegs[$windDegIdx]) : (! empty($windDegs) ? (int) round(array_sum($windDegs) / count($windDegs)) : null);

            $dateInTz = (new \DateTimeImmutable($date . ' noon', $tz));
            $maxPop = ! empty($pops) ? (int) round(max($pops) * 100) : null;
            $dailyForecasts[] = [
                'date' => $date,
                'day_name' => $dateInTz->format('D'),
                'date_display' => $dateInTz->format('F j, Y'),
                'temp_min' => (int) round(min($tempsMin)),
                'temp_max' => (int) round(max($tempsMax)),
                'pop' => $maxPop,
                'rain_mm' => round($rainMm, 1),
                'wind_speed' => $windSpeed,
                'wind_deg' => $windDeg,
                'condition' => [
                    'id' => $midWeather['id'] ?? 800,
                    'main' => $midWeather['main'] ?? 'Clear',
                    'description' => $midWeather['description'] ?? '',
                    'icon' => $midWeather['icon'] ?? '01d',
                ],
            ];
            $count++;
        }
        return array_slice($dailyForecasts, 0, 5);
    }

    /**
     * Get normalized weather data for given coordinates (e.g. current GPS).
     * Used when fetching weather by coordinates (e.g. live device location).
     *
     * @return array Same structure as getNormalizedWeatherForUser
     */
    public function getNormalizedWeatherByCoordinates(float $lat, float $lon): array
    {
        $latRounded = round($lat, 3);
        $lonRounded = round($lon, 3);
        $cacheKey = self::CACHE_COORDS_WEATHER_PREFIX . $latRounded . '_' . $lonRounded;

        return Cache::remember($cacheKey, self::CACHE_TTL_MINUTES * 60, function () use ($lat, $lon) {
            return $this->fetchAndNormalizeByCoordinates($lat, $lon);
        });
    }

    /**
     * Fetch weather from API by coordinates and return normalized structure.
     */
    private function fetchAndNormalizeByCoordinates(float $lat, float $lon): array
    {
        $apiKey = config('services.openweathermap.key') ?: env('OPENWEATHERMAP_API_KEY');
        if (empty($apiKey)) {
            Log::warning('FarmWeatherService: OPENWEATHERMAP_API_KEY not set');
            return $this->emptyNormalizedPayloadForCoordinates($lat, $lon);
        }

        try {
            $baseParams = [
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $apiKey,
                'units' => 'metric',
            ];

            $currentRes = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', $baseParams);
            $forecastRes = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/forecast', array_merge($baseParams, ['cnt' => 40]));

            if (! $currentRes->successful()) {
                Log::warning('FarmWeatherService: current weather by coordinates failed');
                return $this->emptyNormalizedPayloadForCoordinates($lat, $lon);
            }

            $current = $currentRes->json();
            $forecastList = $forecastRes->successful() ? ($forecastRes->json()['list'] ?? []) : [];
            $locationName = $current['name'] ?? 'Current location';

            $dailyForecast = $this->buildDailyForecasts($forecastList);
            $hourlyForecast = $this->buildHourlyForecast($forecastList);

            $todayRainProbability = null;
            $todayExpectedRainfall = null;
            if (! empty($dailyForecast)) {
                $firstDay = $dailyForecast[0];
                $todayRainProbability = isset($firstDay['pop']) ? (int) $firstDay['pop'] : null;
                $todayExpectedRainfall = isset($firstDay['rain_mm']) && $firstDay['rain_mm'] > 0
                    ? (float) $firstDay['rain_mm'] : null;
            }

            $conditionId = isset($current['weather'][0]['id']) ? (int) $current['weather'][0]['id'] : null;
            $conditionText = isset($current['weather'][0]['description'])
                ? ucfirst((string) $current['weather'][0]['description']) : null;

            $sunrise = null;
            $sunset = null;
            if (isset($current['sys']['sunrise'])) {
                $sunrise = (new \DateTimeImmutable)->setTimestamp($current['sys']['sunrise'])
                    ->setTimezone(new \DateTimeZone(self::FORECAST_TIMEZONE))
                    ->format('g:i A');
            }
            if (isset($current['sys']['sunset'])) {
                $sunset = (new \DateTimeImmutable)->setTimestamp($current['sys']['sunset'])
                    ->setTimezone(new \DateTimeZone(self::FORECAST_TIMEZONE))
                    ->format('g:i A');
            }

            $currentTemp = isset($current['main']['temp']) ? round((float) $current['main']['temp'], 1) : null;
            $feelsLike = isset($current['main']['feels_like']) ? round((float) $current['main']['feels_like'], 1) : null;
            $humidity = isset($current['main']['humidity']) ? (int) $current['main']['humidity'] : null;
            $pressure = isset($current['main']['pressure']) ? (int) $current['main']['pressure'] : null;
            $visibilityKm = isset($current['visibility']) ? round(((float) $current['visibility']) / 1000, 1) : null;
            $windSpeed = isset($current['wind']['speed']) ? round((float) $current['wind']['speed'] * 3.6, 1) : null;
            $windDirection = isset($current['wind']['deg']) ? (float) $current['wind']['deg'] : null;
            $uvIndex = $this->fetchUvIndex($lat, $lon, (string) $apiKey);
            $lastUpdated = now()->format('Y-m-d H:i:s');

            return [
                'lat' => $lat,
                'lon' => $lon,
                'location_name' => $locationName,
                'current_temperature' => $currentTemp,
                'condition' => $conditionText,
                'condition_id' => $conditionId,
                'feels_like' => $feelsLike,
                'humidity' => $humidity,
                'wind_speed' => $windSpeed,
                'wind_direction' => $windDirection,
                'pressure' => $pressure,
                'visibility_km' => $visibilityKm,
                'uv_index' => $uvIndex,
                'today_rain_probability' => $todayRainProbability,
                'today_expected_rainfall' => $todayExpectedRainfall,
                'sunrise' => $sunrise,
                'sunset' => $sunset,
                'hourly_forecast' => $hourlyForecast,
                'daily_forecast' => $dailyForecast,
                'last_updated' => $lastUpdated,
                'raw_current' => [
                    'temp' => $currentTemp,
                    'feels_like' => $feelsLike,
                    'humidity' => $humidity,
                    'pressure' => $pressure,
                    'visibility_km' => $visibilityKm,
                    'uv_index' => $uvIndex,
                    'wind_speed' => $windSpeed,
                    'wind_direction' => $windDirection,
                    'sunrise_ts' => $current['sys']['sunrise'] ?? null,
                    'sunset_ts' => $current['sys']['sunset'] ?? null,
                    'condition' => [
                        'id' => $conditionId ?? 800,
                        'main' => $current['weather'][0]['main'] ?? 'Clear',
                        'description' => $current['weather'][0]['description'] ?? '',
                        'icon' => $current['weather'][0]['icon'] ?? '01d',
                    ],
                ],
                'raw_daily_for_advisory' => $dailyForecast,
            ];
        } catch (\Throwable $e) {
            Log::error('FarmWeatherService: API error by coordinates', ['message' => $e->getMessage()]);
            return $this->emptyNormalizedPayloadForCoordinates($lat, $lon);
        }
    }

    private function emptyNormalizedPayloadForCoordinates(float $lat, float $lon): array
    {
        return [
            'lat' => $lat,
            'lon' => $lon,
            'location_name' => 'Current location',
            'current_temperature' => null,
            'condition' => null,
            'condition_id' => null,
            'feels_like' => null,
            'humidity' => null,
            'wind_speed' => null,
            'wind_direction' => null,
            'pressure' => null,
            'visibility_km' => null,
            'uv_index' => null,
            'today_rain_probability' => null,
            'today_expected_rainfall' => null,
            'sunrise' => null,
            'sunset' => null,
            'hourly_forecast' => [],
            'daily_forecast' => [],
            'last_updated' => null,
            'raw_current' => null,
            'raw_daily_for_advisory' => [],
        ];
    }

    /**
     * Max daily rainfall (mm) in the next 5 days for advisory logic.
     */
    public function getMaxForecastRainfallMm(User $user): ?float
    {
        $data = $this->getNormalizedWeatherForUser($user);
        $daily = $data['daily_forecast'] ?? [];
        if (empty($daily)) {
            return null;
        }
        $maxMm = 0.0;
        foreach ($daily as $day) {
            $mm = (float) ($day['rain_mm'] ?? 0);
            if ($mm > $maxMm) {
                $maxMm = $mm;
            }
        }
        return $maxMm > 0 ? round($maxMm, 1) : null;
    }

    /**
     * Fetch UV index from One Call API.
     * Supports both current.uvi and current.uv response shapes.
     */
    private function fetchUvIndex(float $lat, float $lon, string $apiKey): ?float
    {
        $endpoints = [
            ['url' => 'https://api.openweathermap.org/data/3.0/onecall', 'params' => ['lat' => $lat, 'lon' => $lon, 'appid' => $apiKey, 'units' => 'metric', 'exclude' => 'minutely,hourly,daily,alerts']],
            ['url' => 'https://api.openweathermap.org/data/2.5/onecall', 'params' => ['lat' => $lat, 'lon' => $lon, 'appid' => $apiKey, 'units' => 'metric', 'exclude' => 'minutely,hourly,daily,alerts']],
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $res = Http::timeout(10)->get($endpoint['url'], $endpoint['params']);
                if (! $res->successful()) {
                    continue;
                }

                $json = $res->json();
                $uv = $json['current']['uvi'] ?? $json['current']['uv'] ?? null;
                if (is_numeric($uv)) {
                    return round((float) $uv, 1);
                }
            } catch (\Throwable $e) {
                Log::warning('FarmWeatherService: UV request failed', ['message' => $e->getMessage()]);
            }
        }

        return null;
    }
}
