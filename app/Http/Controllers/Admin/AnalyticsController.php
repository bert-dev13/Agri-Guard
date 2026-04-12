<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\HistoricalWeather;
use App\Models\User;
use App\Services\CropTimelineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filtersFromRequest($request);

        $farmersQuery = $this->filteredFarmersQuery($filters);
        $rainfallRows = $this->rainfallTrendRows($filters);

        $farmersPerBarangay = $farmersQuery->clone()
            ->selectRaw('farm_barangay_code as barangay_code, COUNT(*) as total')
            ->whereNotNull('farm_barangay_code')
            ->where('farm_barangay_code', '!=', '')
            ->groupBy('farm_barangay_code')
            ->orderByDesc('total')
            ->get();

        $cropDistribution = $farmersQuery->clone()
            ->selectRaw('crop_type, COUNT(*) as total')
            ->whereNotNull('crop_type')
            ->where('crop_type', '!=', '')
            ->groupBy('crop_type')
            ->orderByDesc('total')
            ->get();

        $stageDistribution = $farmersQuery->clone()
            ->selectRaw('farming_stage, COUNT(*) as total')
            ->whereNotNull('farming_stage')
            ->where('farming_stage', '!=', '')
            ->groupBy('farming_stage')
            ->orderByDesc('total')
            ->get();

        $totalFarmers = (int) $farmersQuery->clone()->count();
        $totalFarms = (int) $farmersQuery->clone()
            ->where(function (Builder $q): void {
                $q->whereNotNull('farm_barangay_code')
                    ->orWhereNotNull('crop_type')
                    ->orWhereNotNull('farming_stage')
                    ->orWhereNotNull('planting_date')
                    ->orWhereNotNull('farm_area');
            })
            ->count();

        $averageMonthlyRainfall = collect($rainfallRows)->avg('total_rainfall');
        $distinctCropTypes = (int) $cropDistribution->count();

        $farmersBarangayLabels = [];
        $farmersBarangayValues = [];
        foreach ($farmersPerBarangay as $row) {
            $farmersBarangayLabels[] = Barangay::nameForId((string) $row->barangay_code) ?? 'Unknown';
            $farmersBarangayValues[] = (int) $row->total;
        }

        $cropLabels = [];
        $cropValues = [];
        foreach ($cropDistribution as $row) {
            $cropLabels[] = (string) $row->crop_type;
            $cropValues[] = (int) $row->total;
        }

        $stageLabels = [];
        $stageValues = [];
        foreach ($stageDistribution as $row) {
            $stageLabels[] = app(CropTimelineService::class)->displayLabel((string) $row->farming_stage);
            $stageValues[] = (int) $row->total;
        }

        $rainfallLabels = [];
        $rainfallValues = [];
        $peakRainfall = null;
        foreach ($rainfallRows as $row) {
            $label = Carbon::createFromDate((int) $row->year, (int) $row->month, 1)->format('M Y');
            $value = round((float) $row->total_rainfall, 2);
            $rainfallLabels[] = $label;
            $rainfallValues[] = $value;

            if ($peakRainfall === null || $value > $peakRainfall['value']) {
                $peakRainfall = ['label' => $label, 'value' => $value];
            }
        }

        $insights = [
            'top_barangay' => $farmersPerBarangay->first()
                ? (Barangay::nameForId((string) $farmersPerBarangay->first()->barangay_code) ?? 'Unknown')
                : null,
            'top_crop' => $cropDistribution->first()?->crop_type,
            'top_stage' => $stageDistribution->first()
                ? app(CropTimelineService::class)->displayLabel((string) $stageDistribution->first()->farming_stage)
                : null,
            'peak_rainfall_month' => $peakRainfall['label'] ?? null,
        ];

        return view('admin.analytics.index', [
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'summaryCards' => [
                [
                    'label' => 'Total Farmers',
                    'value' => number_format($totalFarmers),
                    'icon' => 'users',
                    'style' => 'users',
                ],
                [
                    'label' => 'Total Farms',
                    'value' => number_format($totalFarms),
                    'icon' => 'tractor',
                    'style' => 'farms',
                ],
                [
                    'label' => 'Distinct Crop Types',
                    'value' => $distinctCropTypes > 0
                        ? number_format($distinctCropTypes)
                        : '—',
                    'icon' => 'sprout',
                    'style' => 'crop_types',
                ],
                [
                    'label' => 'Average Monthly Rainfall',
                    'value' => $averageMonthlyRainfall !== null ? number_format((float) $averageMonthlyRainfall, 2).' mm' : '—',
                    'icon' => 'cloud-rain',
                    'style' => 'rainfall',
                ],
            ],
            'charts' => [
                'farmers_barangay' => [
                    'labels' => $farmersBarangayLabels,
                    'values' => $farmersBarangayValues,
                    'empty' => 'No farmer records found.',
                ],
                'crop_distribution' => [
                    'labels' => $cropLabels,
                    'values' => $cropValues,
                    'empty' => 'No farm records found.',
                ],
                'stage_distribution' => [
                    'labels' => $stageLabels,
                    'values' => $stageValues,
                    'empty' => 'No farming stage data available.',
                ],
                'rainfall_trend' => [
                    'labels' => $rainfallLabels,
                    'values' => $rainfallValues,
                    'empty' => 'No rainfall data available.',
                ],
            ],
            'insights' => $insights,
        ]);
    }

    /**
     * @return array{barangay: string, crop_type: string, farming_stage: string, start_date: string, end_date: string}
     */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'barangay' => trim((string) $request->query('barangay', '')),
            'crop_type' => trim((string) $request->query('crop_type', '')),
            'farming_stage' => trim((string) $request->query('farming_stage', '')),
            'start_date' => trim((string) $request->query('start_date', '')),
            'end_date' => trim((string) $request->query('end_date', '')),
        ];
    }

    /**
     * @param  array{barangay: string, crop_type: string, farming_stage: string, start_date: string, end_date: string}  $filters
     * @return Builder<User>
     */
    private function filteredFarmersQuery(array $filters): Builder
    {
        $query = User::query()->farmers();

        if ($filters['barangay'] !== '') {
            $query->where('farm_barangay_code', $filters['barangay']);
        }
        if ($filters['crop_type'] !== '') {
            $query->where('crop_type', $filters['crop_type']);
        }
        if ($filters['farming_stage'] !== '') {
            $query->where('farming_stage', $filters['farming_stage']);
        }
        if ($filters['start_date'] !== '') {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }
        if ($filters['end_date'] !== '') {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query;
    }

    /**
     * @param  array{barangay: string, crop_type: string, farming_stage: string, start_date: string, end_date: string}  $filters
     */
    private function rainfallTrendRows(array $filters): array
    {
        $query = HistoricalWeather::query()
            ->validCalendarRows()
            ->selectRaw('year, month, SUM(GREATEST(COALESCE(rainfall, 0), 0)) as total_rainfall')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month');

        if ($filters['start_date'] !== '') {
            $startYm = (int) str_replace('-', '', substr($filters['start_date'], 0, 7));
            $query->havingRaw('(year * 100 + month) >= ?', [$startYm]);
        }

        if ($filters['end_date'] !== '') {
            $endYm = (int) str_replace('-', '', substr($filters['end_date'], 0, 7));
            $query->havingRaw('(year * 100 + month) <= ?', [$endYm]);
        }

        return $query->get()->all();
    }

    /**
     * @return array{
     *   barangays: \Illuminate\Support\Collection<int, \App\Models\Barangay>,
     *   crop_types: array<int, string>,
     *   farming_stages: array<int, string>
     * }
     */
    private function filterOptions(): array
    {
        $farmers = User::query()->farmers();

        return [
            'barangays' => Barangay::query()->orderedByName()->get(['id', 'name']),
            'crop_types' => $farmers->clone()
                ->whereNotNull('crop_type')
                ->where('crop_type', '!=', '')
                ->distinct()
                ->orderBy('crop_type')
                ->pluck('crop_type')
                ->values()
                ->all(),
            'farming_stages' => $farmers->clone()
                ->whereNotNull('farming_stage')
                ->where('farming_stage', '!=', '')
                ->distinct()
                ->orderBy('farming_stage')
                ->pluck('farming_stage')
                ->values()
                ->all(),
        ];
    }
}
