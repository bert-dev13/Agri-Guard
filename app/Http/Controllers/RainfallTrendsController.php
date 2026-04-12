<?php

namespace App\Http\Controllers;

use App\Models\HistoricalWeather;
use App\Services\AiAdvisory\AiAdvisoryService;
use App\Services\WeatherAdvisoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RainfallTrendsController extends Controller
{
    private const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    private const MONTH_FULL = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /**
     * Show the Historical Rainfall Trends page with charts from historical_weather (2014–2024).
     */
    public function show(WeatherAdvisoryService $weatherAdvisoryService, AiAdvisoryService $aiAdvisory): View
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
        $forecast = $weatherData['forecast'] ?? [];
        $todayRainChance = $weather['today_rain_probability'] ?? null;
        $todayRainMm = $weather['today_expected_rainfall'] ?? null;
        $weekMm = is_numeric($todayRainMm) ? ((float) $todayRainMm * 7) : null;
        $monthMm = $avgMonthlyRainfall;
        $trend = $this->deriveRainTrend($monthlyTrend);

        $rainInput = $aiAdvisory->buildRainfallInput(
            $user,
            $farmLocationDisplay,
            is_array($weather) ? $weather : [],
            is_array($forecast) ? $forecast : [],
            $monthlyTrend,
            $yearlyTotals,
            $heavyRainfallByYear,
            $trend,
            $todayRainChance,
            $todayRainMm,
            $weekMm,
            $monthMm,
            $heavyRainfallTotal
        );
        $modelName = (string) (config('togetherai.model') ?? config('services.togetherai.model', ''));
        $rainRun = $aiAdvisory->run(AiAdvisoryService::PAGE_RAINFALL, $user, $rainInput);
        $rainfallRecommendation = [
            'recommendation' => $aiAdvisory->formatRainfallRecommendation($rainRun, $modelName),
            'failed' => (($rainRun['_meta']['ai_status'] ?? 'failed') !== 'success'),
        ];

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
            'today_rainfall_mm' => $todayRainMm,
        ]);
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
            ? $wetMonths[0].' to '.end($wetMonths)
            : implode(', ', $wetMonths);

        return 'Based on historical records, heavy rainfall is more frequent from '.$range.'. Strengthen drainage and monitor flood-prone fields during these months.';
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

        return min($years).'–'.max($years);
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
            ? implode(', ', array_slice($monthNames, 0, -1)).' and '.end($monthNames)
            : ($monthNames[0] ?? 'wet season');
        $heavyTotal = array_sum(array_column($heavyRainfallByYear, 'heavy_rain_days'));
        $rangeStr = count($monthNames) >= 3 ? $monthNames[0].' to '.end($monthNames) : $peakStr;
        $sentence = 'Rainfall in this area usually increases during '.$rangeStr.'. ';
        $sentence .= $peakStr.' show the highest average rainfall based on historical records. ';
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
            return $base.' For rice farms: focus on water control, paddy bunds, and paddy drainage to avoid flooding during peak rain.';
        }
        if (str_contains($cropLower, 'corn')) {
            return $base.' For corn: manage runoff and avoid excess standing water to protect roots.';
        }
        if (str_contains($cropLower, 'vegetable') || str_contains($cropLower, 'vegetables')) {
            return $base.' For vegetables: use raised beds where possible and protect sensitive crops; prepare early for prolonged rain.';
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
            'planting' => 'You are in the planting stage. Plan seed protection before peak rainfall months.',
            'early_growth' => 'You are in early growth. Monitor water accumulation during high-rain periods.',
            'vegetative' => 'You are in the vegetative stage. Monitor water accumulation during high-rain periods.',
            'flowering' => 'You are in flowering. Protect crops from waterlogging during wet months.',
            'harvest' => 'You are at harvest. Plan early harvest if rainfall is historically high this season.',
        ];

        $k = app(\App\Services\CropTimelineService::class)->normalizeStageKey((string) $farmingStage);

        return $stageMessages[$k] ?? null;
    }

    private function farmLocationDisplay($user): string
    {
        return $user->farm_location_display;
    }
}
