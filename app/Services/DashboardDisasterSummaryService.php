<?php

namespace App\Services;

use App\Models\User;

class DashboardDisasterSummaryService
{
    public function __construct(
        private readonly FarmRiskSnapshotService $riskSnapshotService,
        private readonly RiskLabelMapper $riskLabelMapper
    ) {}

    /**
     * @param  array<string, mixed>  $advisoryData
     * @return array<string, mixed>
     */
    public function build(User $user, array $advisoryData): array
    {
        $weather = is_array($advisoryData['weather'] ?? null) ? $advisoryData['weather'] : [];
        $forecast = is_array($advisoryData['forecast'] ?? null) ? $advisoryData['forecast'] : [];
        $snapshot = $this->riskSnapshotService->buildFromWeather($user, $weather, $forecast);

        $impactLevel = (string) ($snapshot['crop_impact_level'] ?? '');
        $outlook = (string) ($snapshot['three_day_outlook'] ?? ($snapshot['three_day_effect'] ?? ''));

        return [
            'estimated_crop_loss' => (string) ($snapshot['possible_loss_range'] ?? ($snapshot['estimated_crop_loss'] ?? '—')),
            'estimated_crop_loss_value' => null,
            'estimated_crop_loss_tone' => $this->riskLabelMapper->cropImpactTone($impactLevel),
            'estimated_crop_loss_helper' => 'Possible loss range is advisory only—not a guaranteed outcome',

            'three_day_effect' => $outlook,
            'three_day_effect_tone' => $this->riskLabelMapper->outlookTone($outlook),
            'three_day_effect_helper' => 'Short summary of likely field conditions over about 72 hours',

            'flood_risk_level' => (string) ($snapshot['flood_risk_display'] ?? $snapshot['flood_risk_level'] ?? 'Unknown'),
            'flood_risk_tone' => (string) ($snapshot['flood_risk_tone'] ?? 'unknown'),
            'flood_risk_helper' => 'Estimated flood concern from rainfall signals and your field context',

            'crop_impact_label' => (string) ($snapshot['crop_impact_label'] ?? ''),
            'possible_loss_range' => (string) ($snapshot['possible_loss_range'] ?? ''),
            'recommended_action' => (string) ($snapshot['recommended_action'] ?? ''),
            'advisory_disclaimer' => (string) ($snapshot['advisory_disclaimer'] ?? ''),

            'view_details_url' => route('weather-details'),
            'assistant_details_url' => route('assistant.index'),
            'weather_details_url' => route('weather-details'),
            'map_details_url' => route('map.index'),
        ];
    }
}
