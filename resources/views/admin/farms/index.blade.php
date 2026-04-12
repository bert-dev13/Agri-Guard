@extends('layouts.admin')

@section('title', 'Farm & Crop Monitoring - AGRIGUARD')
@section('body-class', 'admin-farms-page')

@section('content')
    <div
        id="admin-farms-root"
        class="admin-page"
        data-farms-base="{{ url('/admin/farms') }}"
        data-farms-print-url="{{ route('admin.farms.print-data') }}"
    >
        <section class="admin-users__header">
            <div class="admin-dash-header admin-users-header-shell">
                <div class="admin-dash-header__text">
                    <h1 class="admin-dash-header__title">
                        <span class="admin-dash-header__title-icon admin-users-header-shell__icon" aria-hidden="true"><i data-lucide="tractor"></i></span>
                        <span>Farm &amp; Crop Monitoring</span>
                    </h1>
                    <p class="admin-dash-header__subtitle">View and monitor registered farms and crop details</p>
                </div>
                <div class="admin-users__header-actions">
                    <div class="admin-users-export">
                        <button id="admin-farms-export-toggle" class="admin-users-export__btn" type="button" aria-haspopup="menu" aria-expanded="false">
                            <i data-lucide="download"></i>
                            Export
                            <i data-lucide="chevron-down" class="admin-users-export__chev"></i>
                        </button>
                        <ul id="admin-farms-export-menu" class="admin-users-export__menu" role="menu" hidden>
                            <li><a href="{{ route('admin.farms.export.pdf').$exportSuffix }}" class="admin-users-export__item"><i data-lucide="file-text"></i> Export PDF</a></li>
                            <li><a href="{{ route('admin.farms.export.xlsx').$exportSuffix }}" class="admin-users-export__item"><i data-lucide="sheet"></i> Export Excel</a></li>
                            <li>
                                <button id="admin-farms-print-btn" type="button" class="admin-users-export__item">
                                    <i data-lucide="printer"></i>
                                    Print Report
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        @if (session('success'))
            <div id="admin-farms-toast" class="admin-farms-toast" role="status" aria-live="polite" data-visible="true">
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
            <form method="get" action="{{ route('admin.farms.index') }}" class="admin-users-filters">
                <div class="admin-users-filters__field admin-users-filters__field--grow">
                    <label class="admin-users-filters__label" for="admin-farms-q">Search</label>
                    <input id="admin-farms-q" class="admin-users-filters__input" type="text" name="q" value="{{ $filters['q'] }}" placeholder="Farmer name">
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-farms-barangay">Barangay</label>
                    <select id="admin-farms-barangay" class="admin-users-filters__select" name="barangay">
                        <option value="">All</option>
                        @foreach ($filterOptions['barangays'] as $barangay)
                            <option value="{{ $barangay->id }}" @selected((string) $filters['barangay'] === (string) $barangay->id)>
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-farms-crop">Crop Type</label>
                    <select id="admin-farms-crop" class="admin-users-filters__select" name="crop_type">
                        <option value="">All</option>
                        @foreach ($filterOptions['crop_types'] as $cropType)
                            <option value="{{ $cropType }}" @selected($filters['crop_type'] === $cropType)>{{ $cropType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-farms-stage">Farming Stage</label>
                    <select id="admin-farms-stage" class="admin-users-filters__select" name="farming_stage">
                        <option value="">All</option>
                        @foreach ($filterOptions['farming_stages'] as $stage)
                            <option value="{{ $stage }}" @selected($filters['farming_stage'] === $stage)>{{ app(\App\Services\CropTimelineService::class)->displayLabel($stage) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__actions">
                    <button class="admin-users-filters__submit" type="submit">Apply</button>
                    <a class="admin-users-filters__reset" href="{{ route('admin.farms.index') }}">Reset</a>
                </div>
            </form>
        </section>

        <section class="admin-users-card admin-users-card--table admin-users-table-section">
            <div class="admin-users-print-header">
                <h2 class="admin-users-print-header__title">Farm &amp; Crop Monitoring Report</h2>
                <p id="admin-farms-print-generated-at" class="admin-users-print-header__meta">Generated on {{ now()->format('F d, Y h:i A') }}</p>
                <p id="admin-farms-print-total" class="admin-users-print-header__meta">Total records: {{ $farms->total() }}</p>
            </div>
            <div class="admin-users-print-only" aria-hidden="true">
                <div class="admin-users-table-wrap admin-users-table-wrap--print">
                    <table class="admin-users-table admin-users-print-table">
                        <thead>
                            <tr>
                                <th>Farmer Name</th>
                                <th>Barangay</th>
                                <th>Crop Type</th>
                                <th>Farming Stage</th>
                                <th>Planting Date</th>
                                <th>Farm Size (ha)</th>
                            </tr>
                        </thead>
                        <tbody id="admin-farms-print-tbody">
                            <tr>
                                <td colspan="6" class="admin-users-table__empty">Preparing full dataset for print...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="admin-users-table-wrap admin-users-table-wrap--sticky">
                <table class="admin-users-table admin-farms-table">
                    <thead>
                        <tr>
                            <th>Farmer Name</th>
                            <th>Location (Barangay)</th>
                            <th>Crop Type</th>
                            <th>Farming Stage</th>
                            <th>Planting Date</th>
                            <th>Farm Size (ha)</th>
                            <th class="admin-users-table__actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($farms as $row)
                            <tr>
                                <td><span class="admin-users-table__name">{{ $row['name'] }}</span></td>
                                <td>{{ $row['location'] ?? '—' }}</td>
                                <td>
                                    @if ($row['crop_type'])
                                        <span class="admin-users-stage-tag admin-farms-crop-tag">{{ $row['crop_type'] }}</span>
                                    @else
                                        <span class="admin-users-cell-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['farming_stage'])
                                        <span class="admin-users-stage-tag admin-users-stage-tag--{{ str_replace('_', '-', $row['farming_stage_key']) }}">{{ $row['farming_stage'] }}</span>
                                    @else
                                        <span class="admin-users-cell-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $row['planting_date'] ?? '—' }}</td>
                                <td>{{ $row['farm_area'] ?? '—' }}</td>
                                <td class="admin-users-table__actions-cell">
                                    <div class="admin-users-actions">
                                        <button type="button" class="admin-users-icon-btn admin-users-icon-btn--view" data-action="view" data-user-id="{{ $row['id'] }}" title="View farm details" aria-label="View farm details">
                                            <i data-lucide="eye" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="admin-users-icon-btn admin-users-icon-btn--edit" data-action="edit" data-user-id="{{ $row['id'] }}" title="Edit farm details" aria-label="Edit farm details">
                                            <i data-lucide="pencil" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="admin-users-table__empty">No farm records found for the selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-users-cards">
                @forelse ($farms as $row)
                    <article class="admin-users-mobile-card">
                        <div class="admin-users-mobile-card__head">
                            <div class="admin-users-mobile-card__identity">
                                <h3 class="admin-users-mobile-card__name">{{ $row['name'] }}</h3>
                            </div>
                        </div>
                        <dl class="admin-users-mobile-card__meta">
                            <div class="admin-users-mobile-card__row"><dt>Location</dt><dd>{{ $row['location'] ?? '—' }}</dd></div>
                            <div class="admin-users-mobile-card__row"><dt>Crop Type</dt><dd>{{ $row['crop_type'] ?? '—' }}</dd></div>
                            <div class="admin-users-mobile-card__row"><dt>Stage</dt><dd>{{ $row['farming_stage'] ?? '—' }}</dd></div>
                            <div class="admin-users-mobile-card__row"><dt>Planting Date</dt><dd>{{ $row['planting_date'] ?? '—' }}</dd></div>
                            <div class="admin-users-mobile-card__row"><dt>Farm Size</dt><dd>{{ $row['farm_area'] ?? '—' }}</dd></div>
                        </dl>
                        <div class="admin-users-mobile-card__footer">
                            <div class="admin-users-actions">
                                <button type="button" class="admin-users-icon-btn admin-users-icon-btn--view" data-action="view" data-user-id="{{ $row['id'] }}" title="View farm details" aria-label="View farm details">
                                    <i data-lucide="eye" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="admin-users-icon-btn admin-users-icon-btn--edit" data-action="edit" data-user-id="{{ $row['id'] }}" title="Edit farm details" aria-label="Edit farm details">
                                    <i data-lucide="pencil" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="admin-users-cards__empty">No farm records found.</div>
                @endforelse
            </div>

            <div class="admin-users-pagination">
                <p class="admin-users-pagination__meta">
                    Showing {{ $farms->firstItem() ?? 0 }} to {{ $farms->lastItem() ?? 0 }} of {{ $farms->total() }} records
                </p>
                @if ($farms->hasPages())
                    <div class="mt-4 admin-users-pagination__controls">
                        <a
                            class="admin-users-page-btn @if($farms->onFirstPage()) is-disabled @endif"
                            href="{{ $farms->onFirstPage() ? '#' : $farms->previousPageUrl() }}"
                            @if($farms->onFirstPage()) aria-disabled="true" tabindex="-1" @endif
                        >
                            Previous
                        </a>
                        <div class="admin-users-page-numbers">
                            @foreach ($farms->getUrlRange(1, $farms->lastPage()) as $page => $url)
                                <a
                                    class="admin-users-page-btn @if($farms->currentPage() === $page) is-active @endif"
                                    href="{{ $url }}"
                                    @if($farms->currentPage() === $page) aria-current="page" @endif
                                >
                                    {{ $page }}
                                </a>
                            @endforeach
                        </div>
                        <a
                            class="admin-users-page-btn @if(!$farms->hasMorePages()) is-disabled @endif"
                            href="{{ $farms->hasMorePages() ? $farms->nextPageUrl() : '#' }}"
                            @if(!$farms->hasMorePages()) aria-disabled="true" tabindex="-1" @endif
                        >
                            Next
                        </a>
                    </div>
                @endif
            </div>
        </section>
    </div>

    <div id="admin-farms-modal-view-backdrop" class="admin-users-modal-backdrop" hidden></div>
    <div id="admin-farms-modal-view" class="admin-users-modal" hidden>
        <div class="admin-users-modal__panel">
            <div class="admin-users-modal__head">
                <h2 class="admin-users-modal__title">Farm Details</h2>
                <button type="button" class="admin-users-modal__close" data-close-modal="view"><i data-lucide="x"></i></button>
            </div>
            <dl id="admin-farms-view-body" class="admin-users-detail"></dl>
        </div>
    </div>

    <div id="admin-farms-modal-edit-backdrop" class="admin-users-modal-backdrop" hidden></div>
    <div id="admin-farms-modal-edit" class="admin-users-modal" hidden>
        <div class="admin-users-modal__panel admin-users-modal__panel--wide">
            <div class="admin-users-modal__head">
                <h2 class="admin-users-modal__title">
                    <span class="admin-users-modal__title-icon" aria-hidden="true"><i data-lucide="leaf"></i></span>
                    <span>Edit Farm Record</span>
                </h2>
                <button type="button" class="admin-users-modal__close" data-close-modal="edit"><i data-lucide="x"></i></button>
            </div>
            <form id="admin-farms-edit-form" method="post" class="admin-users-edit-form">
                @csrf
                @method('PUT')
                <div class="admin-users-edit-grid">
                    <label class="admin-users-edit-field">Farmer Name
                        <input id="edit-farm-name" class="admin-users-filters__input" type="text" readonly>
                    </label>
                    <label class="admin-users-edit-field">Barangay
                        <input id="edit-farm-barangay" class="admin-users-filters__input" type="text" readonly>
                    </label>
                    <label class="admin-users-edit-field" for="edit-farm-crop-type">Crop Type
                        <select id="edit-farm-crop-type" class="admin-users-filters__select" name="crop_type">
                            <option value="">Select crop…</option>
                            <option value="Rice">Rice</option>
                            <option value="Corn">Corn</option>
                            @foreach ($filterOptions['crop_types'] as $cropType)
                                @if (! in_array($cropType, ['Rice', 'Corn'], true))
                                    <option value="{{ $cropType }}">{{ $cropType }}</option>
                                @endif
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-users-edit-field">Farming Stage
                        <select id="edit-farm-stage" class="admin-users-filters__select" name="farming_stage">
                            <option value="">Select stage</option>
                            @foreach (\App\Services\CropTimelineService::STAGE_ORDER as $stage)
                                <option value="{{ $stage }}">{{ app(\App\Services\CropTimelineService::class)->displayLabel($stage) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-users-edit-field">Planting Date
                        <input id="edit-farm-planting-date" class="admin-users-filters__input" type="date" name="planting_date">
                    </label>
                    <label class="admin-users-edit-field">Farm Area (ha)
                        <input id="edit-farm-area" class="admin-users-filters__input" type="number" name="farm_area" min="0" step="0.01">
                    </label>
                </div>
                <div class="admin-users-modal__footer">
                    <button type="button" class="admin-users-filters__reset" data-close-modal="edit">Cancel</button>
                    <button type="submit" class="admin-users-filters__submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection

