<?php

namespace App\Services;

use App\Models\User;

class DashboardDisasterSummaryService
{
    public function __construct(
        private readonly FarmRiskSnapshotService $riskSnapshotService
    ) {}

    /**
     * @param  array<string, mixed>  $advisoryData
     * @return array{
     *     estimated_crop_loss: string,
     *     estimated_crop_loss_value: int|null,
     *     three_day_effect: string,
     *     flood_risk_level: string,
     *     flood_risk_tone: string,
     *     view_details_url: string
     * }
     */
    public function build(User $user, array $advisoryData): array
    {
        $weather = is_array($advisoryData['weather'] ?? null) ? $advisoryData['weather'] : [];
        $forecast = is_array($advisoryData['forecast'] ?? null) ? $advisoryData['forecast'] : [];
        $snapshot = $this->riskSnapshotService->buildFromWeather($user, $weather, $forecast);
        $cropLossValue = $snapshot['estimated_crop_loss_value'] ?? null;
        $floodTone = (string) ($snapshot['flood_risk_tone'] ?? 'unknown');

        return [
            'estimated_crop_loss' => (string) ($snapshot['estimated_crop_loss'] ?? 'N/A'),
            'estimated_crop_loss_value' => $cropLossValue,
            'estimated_crop_loss_tone' => $this->cropLossTone($cropLossValue),
            'estimated_crop_loss_helper' => 'Potential crop damage from current weather risk',
            'three_day_effect' => (string) ($snapshot['three_day_effect'] ?? 'No forecast impact available'),
            'three_day_effect_tone' => $this->effectTone((string) ($snapshot['three_day_effect'] ?? '')),
            'three_day_effect_helper' => 'Likely field condition over the next 72 hours',
            'flood_risk_level' => (string) ($snapshot['flood_risk_level'] ?? 'Unknown'),
            'flood_risk_tone' => $floodTone,
            'flood_risk_helper' => 'Current flood status for your farm location',
            'view_details_url' => route('weather-details'),
            'assistant_details_url' => route('assistant.index'),
            'weather_details_url' => route('weather-details'),
            'map_details_url' => route('map.index'),
        ];
    }

    private function cropLossTone(?int $value): string
    {
        if ($value === null) {
            return 'unknown';
        }
        if ($value >= 50) {
            return 'high';
        }
        if ($value >= 25) {
            return 'moderate';
        }

        return 'low';
    }

    private function effectTone(string $effect): string
    {
        $text = strtolower($effect);
        if (str_contains($text, 'severe') || str_contains($text, 'waterlogging') || str_contains($text, 'flood')) {
            return 'high';
        }
        if (str_contains($text, 'stress') || str_contains($text, 'wet') || str_contains($text, 'low flood')) {
            return 'moderate';
        }
        if ($text === '' || $text === 'no forecast impact available' || $text === 'unable to determine impact') {
            return 'unknown';
        }

        return 'low';
    }
}
