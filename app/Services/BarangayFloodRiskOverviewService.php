<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\HistoricalWeather;
use Illuminate\Support\Facades\Cache;

class BarangayFloodRiskOverviewService
{
    private const HISTORY_FROM_YEAR = 2014;

    private const HISTORY_TO_YEAR = 2026;

    public function __construct(
        protected WeatherPredictionService $weatherPrediction
    ) {}

    /**
     * Canonical key for matching barangay names to risk tiers (same rule as Farm Map frontend).
     */
    public static function canonicalName(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $name));
    }

    /**
     * @param  non-empty-string  $municipality
     * @return array{
     *     municipality: string,
     *     high: list<string>,
     *     moderate: list<string>,
     *     low: list<string>
     * }
     */
    public function overviewForMunicipality(string $municipality = 'Amulung'): array
    {
        $municipality = trim($municipality) !== '' ? trim($municipality) : 'Amulung';

        $ttlMinutes = (int) config('barangay_flood_risk.cache_ttl_minutes', 30);
        $cfgPath = config_path('barangay_flood_risk.php');
        $cfgVer = is_file($cfgPath) ? (string) filemtime($cfgPath) : '0';
        $cacheKey = 'barangay_flood_overview:v2:'.md5($municipality.'|'.$cfgVer);

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(max(5, $ttlMinutes)),
            fn (): array => $this->buildOverviewForMunicipality($municipality)
        );
    }

    /**
     * @return array{
     *     municipality: string,
     *     high: list<string>,
     *     moderate: list<string>,
     *     low: list<string>
     * }
     */
    private function buildOverviewForMunicipality(string $municipality): array
    {
        $stress = $this->computeRegionalStressIndex();
        $curatedLookup = $this->curatedCanonicalLookup();

        $barangays = Barangay::query()
            ->where('municipality', $municipality)
            ->orderedByName()
            ->pluck('name')
            ->map(static fn ($n) => trim((string) $n))
            ->filter(static fn ($n) => $n !== '')
            ->values();

        /** @var list<string> $high */
        $high = [];
        /** @var list<string> $moderate */
        $moderate = [];
        /** @var list<string> $low */
        $low = [];

        foreach ($barangays as $name) {
            $tier = $this->resolveTier($name, $curatedLookup, $stress);
            match ($tier) {
                'high' => $high[] = $name,
                'moderate' => $moderate[] = $name,
                default => $low[] = $name,
            };
        }

        sort($high, SORT_STRING);
        sort($moderate, SORT_STRING);
        sort($low, SORT_STRING);

        return [
            'municipality' => $municipality,
            'high' => $high,
            'moderate' => $moderate,
            'low' => $low,
        ];
    }

    /**
     * @return array<string, 'high'|'moderate'|'low'>
     */
    private function curatedCanonicalLookup(): array
    {
        $cfg = config('barangay_flood_risk', []);
        if (! is_array($cfg)) {
            return [];
        }
        /** @var array<string, 'high'|'moderate'|'low'> $out */
        $out = [];

        foreach (['high', 'moderate', 'low'] as $tier) {
            $names = $cfg[$tier] ?? [];
            if (! is_array($names)) {
                continue;
            }
            foreach ($names as $name) {
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }
                $out[self::canonicalName(trim($name))] = $tier;
            }
        }

        $aliases = $cfg['aliases'] ?? [];
        if (is_array($aliases)) {
            foreach ($aliases as $key => $tier) {
                if (! is_string($key) || $key === '' || ! in_array($tier, ['high', 'moderate', 'low'], true)) {
                    continue;
                }
                $out[self::canonicalName(trim($key))] = $tier;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, 'high'|'moderate'|'low'>  $curatedLookup
     */
    private function resolveTier(string $displayName, array $curatedLookup, float $stress): string
    {
        $canon = self::canonicalName($displayName);
        $base = $curatedLookup[$canon] ?? $this->fallbackTierFromStress($stress);

        return $this->applyRegionalModifiers($base, $stress);
    }

    private function fallbackTierFromStress(float $stress): string
    {
        if ($stress >= 0.55) {
            return 'high';
        }
        if ($stress >= 0.28) {
            return 'moderate';
        }

        return 'low';
    }

    /**
     * Escalate curated tiers only when regional historical + short-horizon model signals are clearly elevated.
     */
    private function applyRegionalModifiers(string $base, float $stress): string
    {
        if ($stress >= 0.88) {
            return 'high';
        }
        if ($stress >= 0.68) {
            return match ($base) {
                'high' => 'high',
                'moderate' => 'high',
                default => 'moderate',
            };
        }

        return $base;
    }

    /**
     * 0–1 index: heavy-rain frequency in window + near-term ML rainfall signal.
     */
    private function computeRegionalStressIndex(): float
    {
        $historicalPart = $this->historicalHeavyRainRatio();

        $modelPart = 0.0;
        try {
            $pred = $this->weatherPrediction->predict();
            if (($pred['status'] ?? '') === 'success') {
                $today = is_array($pred['forecast'][0] ?? null) ? $pred['forecast'][0] : null;
                $rain = is_array($today) && is_numeric($today['rainfall'] ?? null)
                    ? (float) $today['rainfall']
                    : (is_numeric($pred['rainfall'] ?? null) ? (float) $pred['rainfall'] : null);
                if ($rain !== null && $rain > 0) {
                    $modelPart = min(1.0, max(0.0, ($rain - 2.0) / 55.0));
                }
            }
        } catch (\Throwable) {
            $modelPart = 0.0;
        }

        return min(1.0, $historicalPart * 0.62 + $modelPart * 0.38);
    }

    private function historicalHeavyRainRatio(): float
    {
        return $this->cachedHistoricalRainStats()['heavy_ratio_scaled'];
    }

    /**
     * Single aggregate pass over historical rows plus cached multiplier (shared with overview TTL layer).
     *
     * @return array{heavy_ratio_scaled: float}
     */
    private function cachedHistoricalRainStats(): array
    {
        $cacheMinutes = max(5, (int) config('barangay_flood_risk.cache_ttl_minutes', 30));

        return Cache::remember(
            'barangay_flood_hist_agg:v1',
            now()->addMinutes($cacheMinutes),
            function (): array {
                $maxRainfall = (float) (HistoricalWeather::query()
                    ->validCalendarRows()
                    ->whereBetween('year', [self::HISTORY_FROM_YEAR, self::HISTORY_TO_YEAR])
                    ->max('rainfall') ?? 0.0);

                $multiplier = $maxRainfall > 0 && $maxRainfall <= 2.0 ? 1000.0 : 1.0;
                $thresholdRaw = 50.0 / max($multiplier, 0.0001);

                $row = HistoricalWeather::query()
                    ->validCalendarRows()
                    ->whereBetween('year', [self::HISTORY_FROM_YEAR, self::HISTORY_TO_YEAR])
                    ->selectRaw(
                        'COUNT(*) as total_cnt, SUM(CASE WHEN rainfall >= ? THEN 1 ELSE 0 END) as heavy_cnt',
                        [$thresholdRaw]
                    )
                    ->first();

                $total = (int) ($row->total_cnt ?? 0);
                $heavy = (int) ($row->heavy_cnt ?? 0);

                if ($total === 0) {
                    return ['heavy_ratio_scaled' => 0.0];
                }

                $rate = $heavy / $total;

                return [
                    'heavy_ratio_scaled' => min(1.0, $rate / 0.065),
                ];
            }
        );
    }
}
