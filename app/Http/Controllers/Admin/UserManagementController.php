<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\User;
use App\Services\CropTimelineService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserManagementController extends Controller
{
    private const EXPORT_LIMIT = 5000;

    private const SORTABLE = [
        'name',
        'email',
        'role',
        'farm_municipality',
        'crop_type',
        'farming_stage',
        'email_verified_at',
        'created_at',
    ];

    public function index(Request $request): View
    {
        $sort = (string) $request->query('sort', 'created_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $query = $this->filteredUserQuery($request, $sort, $dir);
        $paginator = $query->clone()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->tableRow($user, $request->user()));

        $exportQuery = array_merge(
            array_filter([
                'q' => $request->query('q'),
                'municipality' => $request->query('municipality'),
                'barangay' => $request->query('barangay'),
                'crop_type' => $request->query('crop_type'),
                'role' => $request->query('role'),
                'status' => $request->query('status'),
            ], static fn ($v) => $v !== null && $v !== ''),
            ['sort' => $sort, 'dir' => $dir]
        );

        $baseIndexQuery = array_filter([
            'q' => $request->query('q'),
            'municipality' => $request->query('municipality'),
            'barangay' => $request->query('barangay'),
            'crop_type' => $request->query('crop_type'),
            'role' => $request->query('role'),
            'status' => $request->query('status'),
        ], static fn ($v) => $v !== null && $v !== '');

        $sortUrls = [];
        foreach (self::SORTABLE as $column) {
            $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
            $sortUrls[$column] = route('admin.users.index', array_merge(
                $baseIndexQuery,
                ['sort' => $column, 'dir' => $nextDir, 'page' => 1]
            ));
        }

        $exportQueryString = http_build_query($exportQuery);

        return view('admin.users.index', [
            'users' => $paginator,
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'q' => (string) $request->query('q', ''),
                'municipality' => (string) $request->query('municipality', ''),
                'barangay' => (string) $request->query('barangay', ''),
                'crop_type' => (string) $request->query('crop_type', ''),
                'role' => (string) $request->query('role', ''),
                'status' => (string) $request->query('status', ''),
            ],
            'sort' => $sort,
            'dir' => $dir,
            'sortUrls' => $sortUrls,
            'exportSuffix' => $exportQueryString !== '' ? '?'.$exportQueryString : '',
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($this->userDetailsPayload($user));
    }

    public function printData(Request $request): JsonResponse
    {
        $sort = (string) $request->query('sort', 'created_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $rows = $this->filteredUserQuery($request, $sort, $dir)
            ->clone()
            ->get()
            ->map(fn (User $user): array => $this->tableRow($user, $request->user()))
            ->values();

        return response()->json([
            'generated_at' => now()->format('F d, Y h:i A'),
            'total' => $rows->count(),
            'rows' => $rows,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('farm_barangay_code') === '') {
            $request->merge(['farm_barangay_code' => null]);
        }

        $barangayExists = Rule::exists('barangays', 'id');
        $m = $request->input('farm_municipality');
        if (is_string($m) && trim($m) !== '') {
            $barangayExists = $barangayExists->where('municipality', trim($m));
        }

        $role = (string) $request->input('role');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', 'in:admin,farmer'],
            'farm_municipality' => ['nullable', 'string', 'max:255'],
            'farm_barangay_code' => ['nullable', 'string', 'max:20', $barangayExists],
            'crop_type' => $role === 'farmer'
                ? ['required', 'string', 'in:Rice,Corn']
                : ['nullable', 'string', 'max:100'],
            'farming_stage' => $this->farmingStageValidationRules($role),
        ], [
            'crop_type.required' => 'Crop type is required for farmers.',
            'crop_type.in' => 'Crop type must be Rice or Corn.',
            'farming_stage.required' => 'Farming stage is required for farmers.',
            'farming_stage.in' => 'Please select a valid farming stage.',
        ]);

        $barangayName = null;
        if (! empty($validated['farm_barangay_code'])) {
            $barangayName = Barangay::nameForId($validated['farm_barangay_code']);
        }

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'email_verified_at' => now(),
            'farm_municipality' => $validated['farm_municipality'] ?? null,
            'farm_barangay_code' => $validated['farm_barangay_code'] ?? null,
            'farm_barangay' => $barangayName ?? '',
            'crop_type' => ($validated['role'] ?? '') === 'farmer' ? ($validated['crop_type'] ?? null) : null,
            'farming_stage' => ($validated['role'] ?? '') === 'farmer' ? ($validated['farming_stage'] ?? null) : null,
        ]);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id && $request->input('role') === 'farmer' && $user->isAdmin()) {
            return back()->withErrors(['role' => 'You cannot remove your own administrator role.']);
        }

        if ($this->isLastAdmin($user) && $request->input('role') === 'farmer') {
            return back()->withErrors(['role' => 'The only administrator cannot be demoted to farmer.']);
        }

        $codeRaw = $request->input('farm_barangay_code');
        $code = is_string($codeRaw) ? trim($codeRaw) : $codeRaw;
        $request->merge([
            'farm_barangay_code' => ($code === '') ? null : $code,
        ]);

        $barangayExists = Rule::exists('barangays', 'id');
        $m = $request->input('farm_municipality');
        if (is_string($m) && trim($m) !== '') {
            $barangayExists = $barangayExists->where('municipality', trim($m));
        }

        $role = (string) $request->input('role');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'string', 'in:admin,farmer'],
            'farm_municipality' => ['nullable', 'string', 'max:255'],
            'farm_barangay_code' => ['nullable', 'string', 'max:20', $barangayExists],
            'crop_type' => $role === 'farmer'
                ? ['required', 'string', 'in:Rice,Corn']
                : ['nullable', 'string', 'max:100'],
            'farming_stage' => $this->farmingStageValidationRules($role),
            'planting_date' => ['nullable', 'date'],
            'farm_area' => ['nullable', 'numeric', 'min:0'],
        ], [
            'crop_type.required' => 'Crop type is required for farmers.',
            'crop_type.in' => 'Crop type must be Rice or Corn.',
            'farming_stage.required' => 'Farming stage is required for farmers.',
            'farming_stage.in' => 'Please select a valid farming stage.',
        ]);

        $barangayName = null;
        if (! empty($validated['farm_barangay_code'])) {
            $barangayName = Barangay::nameForId($validated['farm_barangay_code']);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'farm_municipality' => $validated['farm_municipality'] ?? null,
            'farm_barangay_code' => $validated['farm_barangay_code'] ?? null,
            'farm_barangay' => $barangayName ?? '',
            'crop_type' => ($validated['role'] ?? '') === 'farmer' ? ($validated['crop_type'] ?? null) : null,
            'farming_stage' => ($validated['role'] ?? '') === 'farmer' ? ($validated['farming_stage'] ?? null) : null,
            'planting_date' => $validated['planting_date'] ?? null,
            'farm_area' => isset($validated['farm_area']) ? (float) $validated['farm_area'] : null,
        ]);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        if ($this->isLastAdmin($user)) {
            return back()->withErrors(['delete' => 'The only administrator cannot be deleted.']);
        }

        $user->delete();

        return back()->with('success', 'User removed.');
    }

    public function verify(Request $request, User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            return back()->with('success', 'User was already verified.');
        }

        User::query()->whereKey($user->id)->update([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
            'verification_attempts' => 0,
            'verification_locked_until' => null,
        ]);

        return back()->with('success', 'Email marked as verified.');
    }

    public function exportPdf(Request $request)
    {
        $sort = (string) $request->query('sort', 'created_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $users = $this->filteredUserQuery($request, $sort, $dir)
            ->clone()
            ->limit(self::EXPORT_LIMIT)
            ->get();

        $pdf = Pdf::loadView('admin.users.export-pdf', [
            'users' => $users,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('agriguard-users-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportExcel(Request $request): BinaryFileResponse
    {
        $sort = (string) $request->query('sort', 'created_at');
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'created_at';
        }

        $query = $this->filteredUserQuery($request, $sort, $dir)->clone()->limit(self::EXPORT_LIMIT);

        return Excel::download(
            new UsersExport($query),
            'agriguard-users-'.now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * @return Builder<User>
     */
    private function filteredUserQuery(Request $request, string $sort, string $dir): Builder
    {
        $q = User::query()->select([
            'id',
            'name',
            'email',
            'role',
            'farm_municipality',
            'farm_barangay',
            'farm_barangay_code',
            'crop_type',
            'farming_stage',
            'planting_date',
            'farm_area',
            'farm_lat',
            'farm_lng',
            'field_condition',
            'email_verified_at',
            'email_verification_code',
            'email_verification_expires_at',
            'verification_locked_until',
            'reality_check_status',
            'created_at',
            'updated_at',
        ]);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $q->where(function (Builder $w) use ($search): void {
                $w->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $municipality = trim((string) $request->query('municipality', ''));
        if ($municipality !== '') {
            $q->where('farm_municipality', $municipality);
        }

        $barangayId = trim((string) $request->query('barangay', ''));
        if ($barangayId !== '') {
            $q->where('farm_barangay_code', $barangayId);
        }

        $crop = trim((string) $request->query('crop_type', ''));
        if ($crop !== '') {
            $q->where('crop_type', $crop);
        }

        $role = trim((string) $request->query('role', ''));
        if ($role === 'admin') {
            $q->where('role', 'admin');
        } elseif ($role === 'farmer') {
            $q->farmers();
        }

        $accountStatus = trim((string) $request->query('status', ''));
        if ($accountStatus === 'locked') {
            $q->where('verification_locked_until', '>', now());
        } elseif ($accountStatus === 'issue') {
            $q->whereNotNull('email_verified_at')
                ->where('reality_check_status', 'delayed')
                ->where(function (Builder $w): void {
                    $w->whereNull('verification_locked_until')
                        ->orWhere('verification_locked_until', '<=', now());
                });
        } elseif ($accountStatus === 'verified') {
            $q->whereNotNull('email_verified_at')
                ->where(function (Builder $w): void {
                    $w->whereNull('reality_check_status')
                        ->orWhere('reality_check_status', '!=', 'delayed');
                })
                ->where(function (Builder $w): void {
                    $w->whereNull('verification_locked_until')
                        ->orWhere('verification_locked_until', '<=', now());
                });
        } elseif ($accountStatus === 'pending') {
            $q->whereNotNull('email_verification_code')
                ->whereNull('email_verified_at')
                ->where(function (Builder $w): void {
                    $w->whereNull('verification_locked_until')
                        ->orWhere('verification_locked_until', '<=', now());
                });
        } elseif ($accountStatus === 'unverified') {
            $q->whereNull('email_verified_at')
                ->where(function (Builder $w): void {
                    $w->whereNull('email_verification_code')
                        ->orWhere('email_verification_code', '');
                })
                ->where(function (Builder $w): void {
                    $w->whereNull('verification_locked_until')
                        ->orWhere('verification_locked_until', '<=', now());
                });
        }

        $q->orderBy($sort, $dir)->orderBy('id', $dir);

        return $q;
    }

    /**
     * @return array{municipalities: list<string>, barangays: \Illuminate\Support\Collection<int, \App\Models\Barangay>, crop_types: list<string>}
     */
    private function filterOptions(): array
    {
        return [
            'municipalities' => Barangay::municipalities(),
            'barangays' => Barangay::query()->orderedByName()->get(['id', 'name', 'municipality']),
            'crop_types' => User::query()
                ->whereNotNull('crop_type')
                ->where('crop_type', '!=', '')
                ->distinct()
                ->orderBy('crop_type')
                ->pluck('crop_type')
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tableRow(User $user, ?User $actor): array
    {
        $status = $this->statusForUser($user);
        $loc = $this->farmLocationParts($user);

        $cropDisplay = null;
        $cropIsRice = false;
        if (! $user->isAdmin() && filled($user->crop_type)) {
            $cropDisplay = (string) $user->crop_type;
            $cropIsRice = stripos($cropDisplay, 'rice') !== false;
        }

        $stageKey = null;
        $stageLabel = null;
        if (! $user->isAdmin() && filled($user->farming_stage)) {
            $stageKey = (string) $user->farming_stage;
            $stageLabel = app(CropTimelineService::class)->displayLabel($stageKey);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role_label' => $user->isAdmin() ? 'Admin' : 'Farmer',
            'role_key' => $user->isAdmin() ? 'admin' : 'farmer',
            'location_na' => $loc['na'],
            'location_municipality' => $loc['municipality'],
            'location_barangay' => $loc['barangay'],
            'crop_type' => $cropDisplay,
            'crop_is_rice' => $cropIsRice,
            'farming_stage' => $stageLabel,
            'farming_stage_key' => $stageKey,
            'status' => $status,
            'show_verify' => $user->email_verified_at === null,
            'can_delete' => $actor !== null
                && $user->id !== $actor->id
                && ! $this->isLastAdmin($user),
        ];
    }

    /**
     * @return array{na: bool, municipality: string|null, barangay: string|null}
     */
    private function farmLocationParts(User $user): array
    {
        if ($user->isAdmin()) {
            return ['na' => true, 'municipality' => null, 'barangay' => null];
        }

        $mun = trim((string) ($user->farm_municipality ?? ''));
        $bar = '';
        $code = trim((string) ($user->farm_barangay_code ?? ''));
        if ($code !== '' && ctype_digit($code)) {
            $bar = Barangay::nameForId($code) ?? '';
        }
        if ($bar === '') {
            $bar = trim((string) ($user->farm_barangay ?? ''));
        }

        return [
            'na' => false,
            'municipality' => $mun !== '' ? $mun : null,
            'barangay' => $bar !== '' ? $bar : null,
        ];
    }

    /**
     * @return array{key: string, label: string}
     */
    private function statusForUser(User $user): array
    {
        if ($user->isVerificationLocked()) {
            return ['key' => 'locked', 'label' => 'Locked'];
        }

        if ($user->email_verified_at !== null) {
            if ($user->reality_check_status === 'delayed') {
                return ['key' => 'issue', 'label' => 'Issue'];
            }

            return ['key' => 'verified', 'label' => 'Verified'];
        }

        if ($user->hasPendingEmailVerification()) {
            return ['key' => 'pending', 'label' => 'Pending'];
        }

        return ['key' => 'pending', 'label' => 'Unverified'];
    }

    /**
     * @return array<string, mixed>
     */
    private function userDetailsPayload(User $user): array
    {
        $status = $this->statusForUser($user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->isAdmin() ? 'admin' : 'farmer',
            'role_label' => $user->isAdmin() ? 'Admin' : 'Farmer',
            'farm_municipality' => $user->farm_municipality ?? '',
            'farm_barangay' => $user->farm_barangay_name,
            'farm_barangay_code' => $user->farm_barangay_code ?? '',
            'crop_type' => $user->crop_type ?? '',
            'farming_stage' => $user->farming_stage ?? '',
            'farming_stage_label' => app(CropTimelineService::class)->displayLabel($user->farming_stage),
            'planting_date' => $user->planting_date?->format('Y-m-d') ?? '',
            'farm_area' => $user->farm_area !== null ? (string) $user->farm_area : '',
            'status' => $status,
        ];
    }

    private function isLastAdmin(User $user): bool
    {
        return $user->isAdmin() && User::query()->admins()->count() === 1;
    }

    /**
     * Same enum as registration / settings farm profile (snake_case values).
     *
     * @return array<int, string|\Illuminate\Contracts\Validation\ValidationRule>
     */
    private function farmingStageValidationRules(string $role): array
    {
        return $role === 'farmer'
            ? ['required', 'string', Rule::in(CropTimelineService::STAGE_ORDER)]
            : ['nullable', 'string', 'max:100'];
    }
}
