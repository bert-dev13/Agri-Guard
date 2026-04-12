<?php

namespace App\Http\Controllers\Admin;

use App\Exports\FarmsExport;
use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\User;
use App\Services\CropTimelineService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FarmMonitoringController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    private const SORTABLE = [
        'name',
        'farm_barangay_code',
        'crop_type',
        'farming_stage',
        'planting_date',
        'farm_area',
        'updated_at',
        'created_at',
    ];

    public function index(Request $request): View
    {
        $sort = (string) $request->query('sort', 'updated_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'updated_at';
        }

        $query = $this->filteredFarmQuery($request, $sort, $dir);
        $farms = $query->clone()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->tableRow($user));

        $exportQuery = array_filter([
            'q' => $request->query('q'),
            'barangay' => $request->query('barangay'),
            'crop_type' => $request->query('crop_type'),
            'farming_stage' => $request->query('farming_stage'),
            'sort' => $sort,
            'dir' => $dir,
        ], static fn ($v) => $v !== null && $v !== '');

        $exportQueryString = http_build_query($exportQuery);

        return view('admin.farms.index', [
            'farms' => $farms,
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'barangay' => (string) $request->query('barangay', ''),
                'crop_type' => (string) $request->query('crop_type', ''),
                'farming_stage' => (string) $request->query('farming_stage', ''),
            ],
            'exportSuffix' => $exportQueryString !== '' ? '?'.$exportQueryString : '',
        ]);
    }

    public function show(User $user): JsonResponse
    {
        abort_if($this->isNotFarmRecord($user), 404);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'location' => $user->farm_barangay_name ?: '—',
            'barangay_code' => (string) ($user->farm_barangay_code ?? ''),
            'crop_type' => $user->crop_type ?? '',
            'farming_stage' => $user->farming_stage ?? '',
            'farming_stage_label' => app(CropTimelineService::class)->displayLabel($user->farming_stage),
            'planting_date' => $user->planting_date?->format('Y-m-d') ?? '',
            'farm_area' => $user->farm_area !== null ? (string) $user->farm_area : '',
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($this->isNotFarmRecord($user), 404);

        $validated = $request->validate([
            'crop_type' => ['nullable', 'string', 'max:100'],
            'farming_stage' => ['nullable', 'string', Rule::in(CropTimelineService::STAGE_ORDER)],
            'planting_date' => ['nullable', 'date'],
            'farm_area' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user->update([
            'crop_type' => $validated['crop_type'] ?? null,
            'farming_stage' => $validated['farming_stage'] ?? null,
            'planting_date' => $validated['planting_date'] ?? null,
            'farm_area' => isset($validated['farm_area']) ? (float) $validated['farm_area'] : null,
        ]);

        // Never pass $request->only('crop_type', 'farming_stage', …) here: on POST those keys are the
        // edited farm fields, not the index filter query params — they were incorrectly applied as filters
        // and shrank the list. Match User Management: return to the prior listing URL (filters, page, sort).
        return back()->with('success', 'Farm record updated successfully.');
    }

    public function printData(Request $request): JsonResponse
    {
        $sort = (string) $request->query('sort', 'updated_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'updated_at';
        }

        $rows = $this->filteredFarmQuery($request, $sort, $dir)
            ->clone()
            ->get()
            ->map(fn (User $user): array => $this->tableRow($user))
            ->values();

        return response()->json([
            'generated_at' => now()->format('F d, Y h:i A'),
            'total' => $rows->count(),
            'rows' => $rows,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $sort = (string) $request->query('sort', 'updated_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'updated_at';
        }

        $farms = $this->filteredFarmQuery($request, $sort, $dir)
            ->clone()
            ->limit(self::EXPORT_LIMIT)
            ->get();

        $pdf = Pdf::loadView('admin.farms.export-pdf', [
            'farms' => $farms,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('agriguard-farm-monitoring-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $sort = (string) $request->query('sort', 'updated_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'updated_at';
        }

        $query = $this->filteredFarmQuery($request, $sort, $dir)->clone()->limit(self::EXPORT_LIMIT);

        return Excel::download(
            new FarmsExport($query),
            'agriguard-farm-monitoring-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * @return Builder<User>
     */
    private function filteredFarmQuery(Request $request, string $sort, string $dir): Builder
    {
        $q = User::query()
            ->select([
                'id',
                'name',
                'role',
                'farm_barangay',
                'farm_barangay_code',
                'crop_type',
                'farming_stage',
                'planting_date',
                'farm_area',
                'updated_at',
                'created_at',
            ])
            ->where(function (Builder $w): void {
                $w->where('role', 'farmer')
                    ->orWhere(function (Builder $farm): void {
                        $farm->where(function (Builder $f): void {
                            $f->whereNull('role')
                                ->orWhere('role', '!=', 'admin');
                        })
                            ->where(function (Builder $hasFarmData): void {
                                $hasFarmData
                                    ->whereNotNull('farm_barangay_code')
                                    ->orWhereNotNull('farm_barangay')
                                    ->orWhereNotNull('crop_type')
                                    ->orWhereNotNull('farming_stage')
                                    ->orWhereNotNull('planting_date')
                                    ->orWhereNotNull('farm_area');
                            });
                    });
            });

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $q->where('name', 'like', '%'.$search.'%');
        }

        $barangay = trim((string) $request->query('barangay', ''));
        if ($barangay !== '') {
            $q->where('farm_barangay_code', $barangay);
        }

        $cropType = trim((string) $request->query('crop_type', ''));
        if ($cropType !== '') {
            $q->where('crop_type', $cropType);
        }

        $stage = trim((string) $request->query('farming_stage', ''));
        if ($stage !== '') {
            $q->where('farming_stage', $stage);
        }

        $q->orderBy($sort, $dir)->orderBy('id', $dir);

        return $q;
    }

    /**
     * @return array{barangays: \Illuminate\Support\Collection<int, \App\Models\Barangay>, crop_types: list<string>, farming_stages: list<string>}
     */
    private function filterOptions(): array
    {
        $farmers = User::query()->farmers();

        return [
            'barangays' => Barangay::query()->orderedByName()->get(['id', 'name', 'municipality']),
            'crop_types' => $farmers->clone()
                ->whereNotNull('crop_type')
                ->where('crop_type', '!=', '')
                ->distinct()
                ->orderBy('crop_type')
                ->pluck('crop_type')
                ->all(),
            'farming_stages' => $farmers->clone()
                ->whereNotNull('farming_stage')
                ->where('farming_stage', '!=', '')
                ->distinct()
                ->orderBy('farming_stage')
                ->pluck('farming_stage')
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tableRow(User $user): array
    {
        $stageKey = $user->farming_stage ?: null;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'location' => $user->farm_barangay_name ?: null,
            'crop_type' => $user->crop_type ?: null,
            'farming_stage' => $stageKey ? app(CropTimelineService::class)->displayLabel($stageKey) : null,
            'farming_stage_key' => $stageKey,
            'planting_date' => $user->planting_date?->format('M d, Y'),
            'farm_area' => $user->farm_area !== null ? number_format((float) $user->farm_area, 2).' ha' : null,
        ];
    }

    private function isNotFarmRecord(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return ! $user->isFarmer()
            && blank($user->farm_barangay_code)
            && blank($user->farm_barangay)
            && blank($user->crop_type)
            && blank($user->farming_stage)
            && $user->planting_date === null
            && $user->farm_area === null;
    }
}
