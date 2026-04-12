<?php

namespace App\Services;

use App\Models\User;

/**
 * Builds the shared farm/weather JSON payload for AI advisory (see AiAdvisoryService).
 */
class FarmRecommendationService
{
    /**
     * Build the common payload requested by the AI prompt.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function buildPayload(User $user, array $context): array
    {
        $weather = $context['weather'] ?? [];
        $hourly = $context['hourly_summary'] ?? [];
        $shortForecast = $context['short_forecast'] ?? [];
        $rainfall = $context['rainfall_summary'] ?? [];
        $forecastNextDays = $context['forecast_next_days'] ?? [];

        return [
            'farm_name' => trim((string) ($user->name ?? 'Farmer')).' Farm',
            'location' => (string) ($user->farm_location_display ?? 'Amulung, Cagayan'),
            'barangay' => trim((string) ($context['barangay'] ?? $user->farm_barangay_name ?? '')),
            'municipality' => trim((string) ($user->farm_municipality ?? 'Amulung')),
            'crop_type' => trim((string) ($user->crop_type ?? '')),
            'growth_stage' => trim((string) ($user->farming_stage ?? '')),
            'farming_stage_label' => trim((string) ($context['farming_stage_label'] ?? '')),
            'weather' => [
                'temperature_c' => $this->numericOrNull($weather['temperature'] ?? null),
                'humidity_pct' => $this->numericOrNull($weather['humidity'] ?? null),
                'wind_speed_kmh' => $this->numericOrNull($weather['wind_speed'] ?? null),
                'condition' => (string) ($weather['condition'] ?? 'Unknown'),
                'rain_chance_pct' => $this->numericOrNull($weather['rain_chance'] ?? null),
                'today_expected_rainfall_mm' => $this->numericOrNull($weather['today_expected_rainfall_mm'] ?? null),
                'hourly_rain_chances_pct' => [
                    'morning' => $this->numericOrNull($hourly['morning_rain_chance'] ?? null),
                    'afternoon' => $this->numericOrNull($hourly['afternoon_rain_chance'] ?? null),
                    'evening' => $this->numericOrNull($hourly['evening_rain_chance'] ?? null),
                ],
                'short_forecast' => $this->normalizeShortForecast($shortForecast),
            ],
            'rainfall' => [
                'today_mm' => $this->numericOrNull($rainfall['today_mm'] ?? null),
                'week_mm' => $this->numericOrNull($rainfall['week_mm'] ?? null),
                'month_mm' => $this->numericOrNull($rainfall['month_mm'] ?? null),
                'trend' => (string) ($rainfall['trend'] ?? 'unknown'),
                'max_rain_chance_next_5_days_pct' => $this->numericOrNull($rainfall['max_rain_chance_next_5_days_pct'] ?? null),
            ],
            'forecast_next_days' => is_array($forecastNextDays) ? array_values($forecastNextDays) : [],
            'hourly_next_slots' => is_array($context['hourly_next_slots'] ?? null) ? array_values($context['hourly_next_slots']) : [],
        ];
    }

    private function numericOrNull(mixed $value): float|int|null
    {
        return is_numeric($value) ? $value + 0 : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
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
