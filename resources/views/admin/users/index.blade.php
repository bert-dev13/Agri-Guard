@extends('layouts.admin')

@section('title', 'User Management - AGRIGUARD')
@section('body-class', 'admin-users-page')

@section('content')
    <div
        id="admin-users-root"
        class="admin-page"
        data-users-base="{{ url('/admin/users') }}"
        data-users-print-url="{{ route('admin.users.print-data') }}"
        data-open-add="{{ $errors->any() ? '1' : '0' }}"
        data-old-add-barangay="{{ old('farm_barangay_code', '') }}"
    >
        <section class="admin-users__header">
            <div class="admin-dash-header admin-users-header-shell">
                <div class="admin-dash-header__text">
                    <h1 class="admin-dash-header__title">
                        <span class="admin-dash-header__title-icon admin-users-header-shell__icon" aria-hidden="true"><i data-lucide="users"></i></span>
                        <span>User Management</span>
                    </h1>
                    <p class="admin-dash-header__subtitle">Manage farmer and admin accounts, filter records, and export data.</p>
                </div>
                <div class="admin-users__header-actions">
                    <div class="admin-users-export">
                        <button id="admin-users-export-toggle" class="admin-users-export__btn" type="button" aria-haspopup="menu" aria-expanded="false">
                            <i data-lucide="download"></i>
                            Export
                            <i data-lucide="chevron-down" class="admin-users-export__chev"></i>
                        </button>
                        <ul id="admin-users-export-menu" class="admin-users-export__menu" role="menu" hidden>
                            <li><a href="{{ route('admin.users.export.pdf').$exportSuffix }}" class="admin-users-export__item"><i data-lucide="file-text"></i> Export PDF</a></li>
                            <li><a href="{{ route('admin.users.export.xlsx').$exportSuffix }}" class="admin-users-export__item"><i data-lucide="sheet"></i> Export Excel</a></li>
                            <li>
                                <button id="admin-users-print-btn" type="button" class="admin-users-export__item">
                                    <i data-lucide="printer"></i>
                                    Print Report
                                </button>
                            </li>
                        </ul>
                    </div>
                    <button id="admin-users-open-add" type="button" class="admin-dash-header__btn admin-users__add-btn">
                        <i data-lucide="user-plus" aria-hidden="true"></i>
                        <span>Add User</span>
                    </button>
                </div>
            </div>
        </section>

        @if (session('success'))
            <div id="admin-users-toast" class="admin-users-toast" role="status" aria-live="polite" data-visible="true">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="admin-users-flash admin-users-flash--error">
                <ul class="admin-users-flash__list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="admin-users-card">
            <form method="get" action="{{ route('admin.users.index') }}" class="admin-users-filters">
                <div class="admin-users-filters__field admin-users-filters__field--grow">
                    <label class="admin-users-filters__label" for="admin-users-q">Search</label>
                    <input id="admin-users-q" class="admin-users-filters__input" type="text" name="q" value="{{ $filters['q'] }}" placeholder="Name or email">
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-users-filter-barangay">Barangay</label>
                    <select id="admin-users-filter-barangay" class="admin-users-filters__select" name="barangay">
                        <option value="">All</option>
                        @foreach ($filterOptions['barangays'] as $barangay)
                            <option
                                value="{{ $barangay->id }}"
                                data-municipality="{{ $barangay->municipality }}"
                                @selected((string) $filters['barangay'] === (string) $barangay->id)
                            >
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-users-filter-crop">Crop Type</label>
                    <select id="admin-users-filter-crop" class="admin-users-filters__select" name="crop_type">
                        <option value="">All</option>
                        @foreach ($filterOptions['crop_types'] as $cropType)
                            <option value="{{ $cropType }}" @selected($filters['crop_type'] === $cropType)>{{ $cropType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-users-filter-role">Role</label>
                    <select id="admin-users-filter-role" class="admin-users-filters__select" name="role">
                        <option value="">All</option>
                        <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                        <option value="farmer" @selected($filters['role'] === 'farmer')>Farmer</option>
                    </select>
                </div>
                <div class="admin-users-filters__field admin-users-filters__field--status">
                    <label class="admin-users-filters__label" for="admin-users-filter-status">Status</label>
                    <select id="admin-users-filter-status" class="admin-users-filters__select" name="status">
                        <option value="">All</option>
                        <option value="verified" @selected($filters['status'] === 'verified')>Verified</option>
                        <option value="pending" @selected($filters['status'] === 'pending')>Pending</option>
                        <option value="unverified" @selected($filters['status'] === 'unverified')>Unverified</option>
                        <option value="issue" @selected($filters['status'] === 'issue')>Issue</option>
                        <option value="locked" @selected($filters['status'] === 'locked')>Locked</option>
                    </select>
                </div>
                <div class="admin-users-filters__actions">
                    <button class="admin-users-filters__submit" type="submit">Apply</button>
                    <a class="admin-users-filters__reset" href="{{ route('admin.users.index') }}">Reset</a>
                </div>
            </form>
        </section>

        <section class="admin-users-card admin-users-card--table admin-users-table-section">
            <div class="admin-users-print-header">
                <h2 class="admin-users-print-header__title">User Management Report</h2>
                <p id="admin-users-print-generated-at" class="admin-users-print-header__meta">Generated on {{ now()->format('F d, Y h:i A') }}</p>
                <p id="admin-users-print-total" class="admin-users-print-header__meta">Total records: {{ $users->total() }}</p>
            </div>
            <div class="admin-users-print-only" aria-hidden="true">
                <div class="admin-users-table-wrap admin-users-table-wrap--print">
                    <table class="admin-users-table admin-users-print-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Location</th>
                                <th>Role</th>
                                <th>Crop</th>
                                <th>Stage</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="admin-users-print-tbody">
                            <tr>
                                <td colspan="7" class="admin-users-table__empty">Preparing full dataset for print…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="admin-users-table-wrap admin-users-table-wrap--sticky">
                    <table class="admin-users-table">
                        <thead>
                            <tr>
                                <th class="admin-users-table__col-name"><a class="admin-users-table__sort" href="{{ $sortUrls['name'] }}">Name</a></th>
                                <th class="admin-users-table__col-email"><a class="admin-users-table__sort" href="{{ $sortUrls['email'] }}">Email</a></th>
                                <th class="admin-users-table__col-location"><a class="admin-users-table__sort" href="{{ $sortUrls['farm_municipality'] }}">Location</a></th>
                                <th class="admin-users-table__col-role"><a class="admin-users-table__sort" href="{{ $sortUrls['role'] }}">Role</a></th>
                                <th class="admin-users-table__col-crop"><a class="admin-users-table__sort" href="{{ $sortUrls['crop_type'] }}">Crop</a></th>
                                <th class="admin-users-table__col-stage"><a class="admin-users-table__sort" href="{{ $sortUrls['farming_stage'] }}">Stage</a></th>
                                <th class="admin-users-table__col-status">Status</th>
                                <th class="admin-users-table__actions-col admin-users-print-hide">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($users as $row)
                                <tr>
                                    <td>
                                        <span class="admin-users-table__name">{{ $row['name'] }}</span>
                                    </td>
                                    <td><span class="admin-users-table__email">{{ $row['email'] }}</span></td>
                                    <td>
                                        @if ($row['location_na'])
                                            <span class="admin-users-cell-muted">N/A</span>
                                        @else
                                            <div class="admin-users-location">
                                                <span class="admin-users-location__line">{{ $row['location_municipality'] ?? '—' }}</span>
                                                <span class="admin-users-location__line admin-users-location__line--secondary">{{ $row['location_barangay'] ?? '—' }}</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td><span class="admin-user-role admin-user-role--{{ $row['role_key'] }}">{{ $row['role_label'] }}</span></td>
                                    <td>{{ $row['crop_type'] ?? '—' }}</td>
                                    <td>{{ $row['farming_stage'] ?? '—' }}</td>
                                    <td><span class="admin-user-badge admin-user-badge--{{ $row['status']['key'] }}">{{ $row['status']['label'] }}</span></td>
                                    <td class="admin-users-table__actions-cell admin-users-print-hide">
                                        <div class="admin-users-actions">
                                            <button type="button" class="admin-users-icon-btn admin-users-icon-btn--view" data-action="view" data-user-id="{{ $row['id'] }}" title="View details" aria-label="View details">
                                                <i data-lucide="eye" aria-hidden="true"></i>
                                            </button>
                                            <button type="button" class="admin-users-icon-btn admin-users-icon-btn--edit" data-action="edit" data-user-id="{{ $row['id'] }}" title="Edit user" aria-label="Edit user">
                                                <i data-lucide="pencil" aria-hidden="true"></i>
                                            </button>
                                            @if ($row['show_verify'])
                                                <form method="post" action="{{ route('admin.users.verify', $row['id']) }}" class="admin-users-inline-form">
                                                    @csrf
                                                    <button type="submit" class="admin-users-icon-btn admin-users-icon-btn--success" title="Verify email" aria-label="Verify email">
                                                        <i data-lucide="badge-check" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($row['can_delete'])
                                                <form method="post" action="{{ route('admin.users.destroy', $row['id']) }}" class="admin-users-inline-form js-admin-users-delete-form">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="admin-users-icon-btn admin-users-icon-btn--danger" title="Delete user" aria-label="Delete user">
                                                        <i data-lucide="trash-2" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="admin-users-table__empty">No users found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>

            <div class="admin-users-cards">
                @forelse ($users as $row)
                    <article class="admin-users-mobile-card">
                        <div class="admin-users-mobile-card__head">
                            <div class="admin-users-mobile-card__identity">
                                <h3 class="admin-users-mobile-card__name">{{ $row['name'] }}</h3>
                                <p class="admin-users-mobile-card__email">{{ $row['email'] }}</p>
                            </div>
                            <div class="admin-users-mobile-card__badges">
                                <span class="admin-user-role admin-user-role--{{ $row['role_key'] }}">{{ $row['role_label'] }}</span>
                                <span class="admin-user-badge admin-user-badge--{{ $row['status']['key'] }}">{{ $row['status']['label'] }}</span>
                            </div>
                        </div>
                        <dl class="admin-users-mobile-card__meta">
                            <div class="admin-users-mobile-card__row"><dt>Location</dt><dd>{{ $row['location_na'] ? 'N/A' : (($row['location_municipality'] ?? '—').', '.($row['location_barangay'] ?? '—')) }}</dd></div>
                            <div class="admin-users-mobile-card__row"><dt>Crop</dt><dd>{{ $row['crop_type'] ?? '—' }}</dd></div>
                            <div class="admin-users-mobile-card__row"><dt>Stage</dt><dd>{{ $row['farming_stage'] ?? '—' }}</dd></div>
                        </dl>
                        <div class="admin-users-mobile-card__footer">
                            <div class="admin-users-actions">
                                <button type="button" class="admin-users-icon-btn admin-users-icon-btn--view" data-action="view" data-user-id="{{ $row['id'] }}" title="View details" aria-label="View details">
                                    <i data-lucide="eye" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="admin-users-icon-btn admin-users-icon-btn--edit" data-action="edit" data-user-id="{{ $row['id'] }}" title="Edit user" aria-label="Edit user">
                                    <i data-lucide="pencil" aria-hidden="true"></i>
                                </button>
                                @if ($row['show_verify'])
                                    <form method="post" action="{{ route('admin.users.verify', $row['id']) }}" class="admin-users-inline-form">
                                        @csrf
                                        <button type="submit" class="admin-users-icon-btn admin-users-icon-btn--success" title="Verify email" aria-label="Verify email">
                                            <i data-lucide="badge-check" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif
                                @if ($row['can_delete'])
                                    <form method="post" action="{{ route('admin.users.destroy', $row['id']) }}" class="admin-users-inline-form js-admin-users-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-users-icon-btn admin-users-icon-btn--danger" title="Delete user" aria-label="Delete user">
                                            <i data-lucide="trash-2" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="admin-users-cards__empty">No users found.</div>
                @endforelse
            </div>

            <div class="admin-users-pagination">
                <p class="admin-users-pagination__meta">
                    Showing {{ $users->firstItem() ?? 0 }} to {{ $users->lastItem() ?? 0 }} of {{ $users->total() }} users
                </p>
                @if ($users->hasPages())
                    <div class="mt-4 admin-users-pagination__controls">
                        <a
                            class="admin-users-page-btn @if($users->onFirstPage()) is-disabled @endif"
                            href="{{ $users->onFirstPage() ? '#' : $users->previousPageUrl() }}"
                            @if($users->onFirstPage()) aria-disabled="true" tabindex="-1" @endif
                        >
                            Previous
                        </a>
                        <div class="admin-users-page-numbers">
                            @foreach ($users->getUrlRange(1, $users->lastPage()) as $page => $url)
                                <a
                                    class="admin-users-page-btn @if($users->currentPage() === $page) is-active @endif"
                                    href="{{ $url }}"
                                    @if($users->currentPage() === $page) aria-current="page" @endif
                                >
                                    {{ $page }}
                                </a>
                            @endforeach
                        </div>
                        <a
                            class="admin-users-page-btn @if(!$users->hasMorePages()) is-disabled @endif"
                            href="{{ $users->hasMorePages() ? $users->nextPageUrl() : '#' }}"
                            @if(!$users->hasMorePages()) aria-disabled="true" tabindex="-1" @endif
                        >
                            Next
                        </a>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <script id="admin-barangays-catalog" type="application/json">@json($filterOptions['barangays']->map(fn($b) => ['id' => (string) $b->id, 'name' => $b->name, 'municipality' => $b->municipality])->values())</script>

    <div id="admin-users-modal-view-backdrop" class="admin-users-modal-backdrop" hidden></div>
    <div id="admin-users-modal-view" class="admin-users-modal" hidden>
        <div class="admin-users-modal__panel">
            <div class="admin-users-modal__head">
                <h2 class="admin-users-modal__title">User Details</h2>
                <button type="button" class="admin-users-modal__close" data-close-modal="view"><i data-lucide="x"></i></button>
            </div>
            <dl id="admin-users-view-body" class="admin-users-detail"></dl>
        </div>
    </div>

    <div id="admin-users-modal-edit-backdrop" class="admin-users-modal-backdrop" hidden></div>
    <div id="admin-users-modal-edit" class="admin-users-modal" hidden>
        <div class="admin-users-modal__panel admin-users-modal__panel--wide">
            <div class="admin-users-modal__head">
                <h2 class="admin-users-modal__title">
                    <span class="admin-users-modal__title-icon" aria-hidden="true"><i data-lucide="user-cog"></i></span>
                    <span>Edit User</span>
                </h2>
                <button type="button" class="admin-users-modal__close" data-close-modal="edit"><i data-lucide="x"></i></button>
            </div>
            <form id="admin-users-edit-form" method="post" class="admin-users-edit-form">
                @csrf
                @method('PUT')
                <div class="admin-users-edit-grid">
                    <label class="admin-users-edit-field">Name<input id="edit-name" class="admin-users-filters__input" type="text" name="name" required></label>
                    <label class="admin-users-edit-field">Email<input id="edit-email" class="admin-users-filters__input" type="email" name="email" required></label>
                    <label class="admin-users-edit-field">Role
                        <select id="edit-role" class="admin-users-filters__select" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="farmer">Farmer</option>
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-edit-field--farmer-only">Municipality
                        <select id="edit-farm_municipality" class="admin-users-filters__select" name="farm_municipality">
                            <option value="">—</option>
                            @foreach ($filterOptions['municipalities'] as $municipality)
                                <option value="{{ $municipality }}">{{ $municipality }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-edit-field--farmer-only">Barangay<select id="edit-farm_barangay_code" class="admin-users-filters__select" name="farm_barangay_code"><option value="">—</option></select></label>
                    <label class="admin-users-edit-field admin-users-edit-field--farmer-only" for="edit-crop_type">Crop Type
                        <select id="edit-crop_type" class="admin-users-filters__select" name="crop_type">
                            <option value="">Select crop…</option>
                            <option value="Rice">Rice</option>
                            <option value="Corn">Corn</option>
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-edit-field--farmer-only" for="edit-farming_stage">Farming Stage
                        <select id="edit-farming_stage" class="admin-users-filters__select" name="farming_stage">
                            <option value="">Select stage</option>
                            <option value="planting">Planting</option>
                            <option value="early_growth">Early growth</option>
                            <option value="vegetative">Vegetative</option>
                            <option value="flowering">Flowering</option>
                            <option value="harvest">Harvest</option>
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-edit-field--farmer-only">Planting Date<input id="edit-planting_date" class="admin-users-filters__input" type="date" name="planting_date"></label>
                    <label class="admin-users-edit-field admin-users-edit-field--farmer-only admin-users-edit-field--full">Farm Area (ha)<input id="edit-farm_area" class="admin-users-filters__input" type="number" name="farm_area" min="0" step="0.01"></label>
                </div>
                <div class="admin-users-modal__footer">
                    <button type="button" class="admin-users-filters__reset" data-close-modal="edit">Cancel</button>
                    <button type="submit" class="admin-users-filters__submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="admin-users-modal-add-backdrop" class="admin-users-modal-backdrop" hidden></div>
    <div id="admin-users-modal-add" class="admin-users-modal" hidden>
        <div class="admin-users-modal__panel admin-users-modal__panel--wide">
            <div class="admin-users-modal__head">
                <h2 class="admin-users-modal__title">
                    <span class="admin-users-modal__title-icon" aria-hidden="true"><i data-lucide="user-plus"></i></span>
                    <span>Add User</span>
                </h2>
                <button type="button" class="admin-users-modal__close" data-close-modal="add"><i data-lucide="x"></i></button>
            </div>
            <form method="post" action="{{ route('admin.users.store') }}" class="admin-users-edit-form">
                @csrf
                <div class="admin-users-edit-grid">
                    <label class="admin-users-edit-field">Name<input class="admin-users-filters__input" type="text" name="name" value="{{ old('name') }}" required></label>
                    <label class="admin-users-edit-field">Email<input class="admin-users-filters__input" type="email" name="email" value="{{ old('email') }}" required></label>
                    <label class="admin-users-edit-field">Password<input class="admin-users-filters__input" type="password" name="password" required></label>
                    <label class="admin-users-edit-field">Confirm Password<input class="admin-users-filters__input" type="password" name="password_confirmation" required></label>
                    <label class="admin-users-edit-field">Role
                        <select id="add-role" class="admin-users-filters__select" name="role" required>
                            <option value="farmer" @selected(old('role', 'farmer') === 'farmer')>Farmer</option>
                            <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-add-field--farmer-only">Municipality
                        <select id="add-farm_municipality" class="admin-users-filters__select" name="farm_municipality">
                            <option value="">—</option>
                            @foreach ($filterOptions['municipalities'] as $municipality)
                                <option value="{{ $municipality }}" @selected(old('farm_municipality') === $municipality)>{{ $municipality }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-add-field--farmer-only">Barangay<select id="add-farm_barangay_code" class="admin-users-filters__select" name="farm_barangay_code"><option value="">—</option></select></label>
                    <label class="admin-users-edit-field admin-users-add-field--farmer-only" for="add-crop_type">Crop Type
                        <select id="add-crop_type" class="admin-users-filters__select" name="crop_type">
                            <option value="" @selected(old('crop_type', '') === '')>Select crop…</option>
                            <option value="Rice" @selected(old('crop_type') === 'Rice')>Rice</option>
                            <option value="Corn" @selected(old('crop_type') === 'Corn')>Corn</option>
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-add-field--farmer-only" for="add-farming_stage">Farming Stage
                        <select id="add-farming_stage" class="admin-users-filters__select" name="farming_stage">
                            <option value="" @selected(old('farming_stage', '') === '')>Select stage</option>
                            <option value="planting" @selected(old('farming_stage') === 'planting')>Planting</option>
                            <option value="early_growth" @selected(old('farming_stage') === 'early_growth')>Early growth</option>
                            <option value="vegetative" @selected(old('farming_stage') === 'vegetative')>Vegetative</option>
                            <option value="flowering" @selected(old('farming_stage') === 'flowering')>Flowering</option>
                            <option value="harvest" @selected(old('farming_stage') === 'harvest')>Harvest</option>
                        </select>
                    </label>
                    <label class="admin-users-edit-field admin-users-add-field--farmer-only">Planting Date<input class="admin-users-filters__input" type="date" name="planting_date" value="{{ old('planting_date') }}"></label>
                    <label class="admin-users-edit-field admin-users-add-field--farmer-only admin-users-edit-field--full">Farm Area (ha)<input class="admin-users-filters__input" type="number" name="farm_area" value="{{ old('farm_area') }}" min="0" step="0.01"></label>
                </div>
                <div class="admin-users-modal__footer">
                    <button type="button" class="admin-users-filters__reset" data-close-modal="add">Cancel</button>
                    <button type="submit" class="admin-users-filters__submit">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <div id="admin-users-delete-backdrop" class="admin-users-delete-backdrop" hidden></div>
    <div id="admin-users-delete-modal" class="admin-users-delete-modal" hidden role="dialog" aria-modal="true" aria-labelledby="admin-users-delete-title">
        <div class="admin-users-delete-modal__panel">
            <h2 id="admin-users-delete-title" class="admin-users-delete-modal__title">Delete User</h2>
            <p class="admin-users-delete-modal__message">Are you sure you want to delete this user?</p>
            <div class="admin-users-delete-modal__actions">
                <button id="admin-users-delete-cancel" type="button" class="admin-users-delete-modal__btn admin-users-delete-modal__btn--neutral">Cancel</button>
                <button id="admin-users-delete-confirm" type="button" class="admin-users-delete-modal__btn admin-users-delete-modal__btn--danger">Delete</button>
            </div>
        </div>
    </div>

    <style>
        .admin-users-delete-backdrop {
            position: fixed;
            inset: 0;
            z-index: 70;
            background: rgba(17, 24, 39, 0.6);
            opacity: 0;
            transition: opacity 180ms ease;
        }

        .admin-users-delete-modal {
            position: fixed;
            inset: 0;
            z-index: 71;
            display: grid;
            place-items: center;
            opacity: 0;
            transform: scale(0.96);
            transition: opacity 180ms ease, transform 200ms ease;
            pointer-events: none;
            padding: 1.25rem;
        }

        .admin-users-delete-backdrop[data-open="true"],
        .admin-users-delete-modal[data-open="true"] {
            opacity: 1;
        }

        .admin-users-delete-modal[data-open="true"] {
            transform: scale(1);
            pointer-events: auto;
        }

        .admin-users-delete-modal__panel {
            width: min(100%, 26rem);
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.28);
            padding: 1.2rem;
        }

        .admin-users-delete-modal__title {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 700;
            color: #111827;
        }

        .admin-users-delete-modal__message {
            margin: 0.65rem 0 1.15rem;
            color: #374151;
        }

        .admin-users-delete-modal__actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.625rem;
        }

        .admin-users-delete-modal__btn {
            border: 0;
            border-radius: 10px;
            padding: 0.55rem 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: filter 140ms ease;
        }

        .admin-users-delete-modal__btn:hover {
            filter: brightness(0.95);
        }

        .admin-users-delete-modal__btn--neutral {
            background: #e5e7eb;
            color: #111827;
        }

        .admin-users-delete-modal__btn--danger {
            background: #dc2626;
            color: #fff;
        }

        .admin-users-toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 80;
            max-width: min(26rem, calc(100vw - 2rem));
            background: #166534;
            color: #fff;
            border-radius: 12px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
            padding: 0.7rem 1rem;
            font-weight: 600;
            opacity: 0;
            transform: translateY(-8px);
            transition: opacity 200ms ease, transform 200ms ease;
        }

        .admin-users-toast[data-visible="true"] {
            opacity: 1;
            transform: translateY(0);
        }
    </style>

    <script>
        (() => {
            const deleteForms = Array.from(document.querySelectorAll('.js-admin-users-delete-form'));
            const deleteBackdrop = document.getElementById('admin-users-delete-backdrop');
            const deleteModal = document.getElementById('admin-users-delete-modal');
            const deleteCancelBtn = document.getElementById('admin-users-delete-cancel');
            const deleteConfirmBtn = document.getElementById('admin-users-delete-confirm');
            const toast = document.getElementById('admin-users-toast');

            let pendingDeleteForm = null;

            const openDeleteModal = (form) => {
                pendingDeleteForm = form;
                deleteBackdrop.hidden = false;
                deleteModal.hidden = false;
                requestAnimationFrame(() => {
                    deleteBackdrop.dataset.open = 'true';
                    deleteModal.dataset.open = 'true';
                });
            };

            const closeDeleteModal = () => {
                deleteBackdrop.dataset.open = 'false';
                deleteModal.dataset.open = 'false';
                pendingDeleteForm = null;
                setTimeout(() => {
                    deleteBackdrop.hidden = true;
                    deleteModal.hidden = true;
                }, 200);
            };

            deleteForms.forEach((form) => {
                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    openDeleteModal(form);
                });
            });

            deleteCancelBtn?.addEventListener('click', closeDeleteModal);
            deleteBackdrop?.addEventListener('click', closeDeleteModal);
            deleteConfirmBtn?.addEventListener('click', () => {
                if (pendingDeleteForm) {
                    pendingDeleteForm.submit();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !deleteModal.hidden) {
                    closeDeleteModal();
                }
            });

            if (toast) {
                setTimeout(() => {
                    toast.dataset.visible = 'false';
                    setTimeout(() => {
                        toast.remove();
                    }, 220);
                }, 3200);
            }
        })();
    </script>
@endsection
