@extends('layouts.admin')

@section('title', 'Analytics - AGRIGUARD')
@section('body-class', 'admin-analytics-page')

@section('content')
    <div id="admin-analytics-root" class="admin-page">
        <section class="admin-users__header">
            <div class="admin-dash-header admin-users-header-shell">
                <div class="admin-dash-header__text">
                    <h1 class="admin-dash-header__title">
                        <span class="admin-dash-header__title-icon admin-users-header-shell__icon" aria-hidden="true"><i data-lucide="chart-column"></i></span>
                        <span>Analytics</span>
                    </h1>
                    <p class="admin-dash-header__subtitle">Visual insights on farmers, farms, crop types, and rainfall</p>
                </div>
            </div>
        </section>

        <section class="admin-users-card">
            <form method="get" action="{{ route('admin.analytics.index') }}" class="admin-users-filters">
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-analytics-barangay">Barangay</label>
                    <select id="admin-analytics-barangay" class="admin-users-filters__select" name="barangay">
                        <option value="">All</option>
                        @foreach ($filterOptions['barangays'] as $barangay)
                            <option value="{{ $barangay->id }}" @selected((string) $filters['barangay'] === (string) $barangay->id)>
                                {{ $barangay->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-analytics-crop-type">Crop Type</label>
                    <select id="admin-analytics-crop-type" class="admin-users-filters__select" name="crop_type">
                        <option value="">All</option>
                        @foreach ($filterOptions['crop_types'] as $cropType)
                            <option value="{{ $cropType }}" @selected($filters['crop_type'] === $cropType)>{{ $cropType }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-analytics-farming-stage">Farming Stage</label>
                    <select id="admin-analytics-farming-stage" class="admin-users-filters__select" name="farming_stage">
                        <option value="">All</option>
                        @foreach ($filterOptions['farming_stages'] as $stage)
                            <option value="{{ $stage }}" @selected($filters['farming_stage'] === $stage)>{{ app(\App\Services\CropTimelineService::class)->displayLabel($stage) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-analytics-start-date">Start Date</label>
                    <input id="admin-analytics-start-date" class="admin-users-filters__input" type="date" name="start_date" value="{{ $filters['start_date'] }}">
                </div>
                <div class="admin-users-filters__field">
                    <label class="admin-users-filters__label" for="admin-analytics-end-date">End Date</label>
                    <input id="admin-analytics-end-date" class="admin-users-filters__input" type="date" name="end_date" value="{{ $filters['end_date'] }}">
                </div>
                <div class="admin-users-filters__actions">
                    <button class="admin-users-filters__submit" type="submit">Apply</button>
                    <a class="admin-users-filters__reset" href="{{ route('admin.analytics.index') }}">Reset</a>
                </div>
            </form>
        </section>

        <section class="admin-dash-section">
            <div class="admin-stat-grid admin-stat-grid--four">
                @foreach ($summaryCards as $card)
                    <article class="admin-stat-card admin-stat-card--{{ $card['style'] }}">
                        <div class="admin-stat-card__icon"><i data-lucide="{{ $card['icon'] }}"></i></div>
                        <div class="admin-stat-card__value">{{ $card['value'] }}</div>
                        <div class="admin-stat-card__label">{{ $card['label'] }}</div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="admin-analytics-charts">
            <article class="admin-dash-panel admin-dash-panel--chart admin-analytics-chart-card">
                <div class="admin-dash-chart__header">
                    <h2 class="admin-dash-panel__title">Farmers per Barangay</h2>
                </div>
                <div class="admin-dash-chart__canvas-wrap">
                    <canvas id="analytics-farmers-barangay-chart" role="img" aria-label="Farmers per barangay bar chart"></canvas>
                    <p id="analytics-farmers-barangay-empty" class="admin-analytics-empty" hidden></p>
                </div>
            </article>

            <article class="admin-dash-panel admin-dash-panel--chart admin-analytics-chart-card">
                <div class="admin-dash-chart__header">
                    <h2 class="admin-dash-panel__title">Crop Distribution</h2>
                </div>
                <div class="admin-dash-chart__canvas-wrap">
                    <canvas id="analytics-crop-distribution-chart" role="img" aria-label="Crop distribution donut chart"></canvas>
                    <p id="analytics-crop-distribution-empty" class="admin-analytics-empty" hidden></p>
                </div>
            </article>

            <article class="admin-dash-panel admin-dash-panel--chart admin-analytics-chart-card admin-analytics-chart-card--wide">
                <div class="admin-dash-chart__header">
                    <h2 class="admin-dash-panel__title">Farming Stage Distribution</h2>
                </div>
                <div class="admin-dash-chart__canvas-wrap">
                    <canvas id="analytics-stage-distribution-chart" role="img" aria-label="Farming stage distribution chart"></canvas>
                    <p id="analytics-stage-distribution-empty" class="admin-analytics-empty" hidden></p>
                </div>
            </article>

            <article class="admin-dash-panel admin-dash-panel--chart admin-analytics-chart-card admin-analytics-chart-card--wide">
                <div class="admin-dash-chart__header">
                    <h2 class="admin-dash-panel__title">Rainfall Trend Overview</h2>
                </div>
                <div class="admin-dash-chart__canvas-wrap">
                    <canvas id="analytics-rainfall-trend-chart" role="img" aria-label="Rainfall trend line chart"></canvas>
                    <p id="analytics-rainfall-trend-empty" class="admin-analytics-empty" hidden></p>
                </div>
            </article>
        </section>

        <section class="admin-users-card admin-analytics-insights-card">
            <h2 class="admin-dash-panel__title">Quick Insights</h2>
            <ul class="admin-analytics-insights-list">
                <li><span>Barangay with most farmers</span><strong>{{ $insights['top_barangay'] ?? 'No data' }}</strong></li>
                <li><span>Most common crop type</span><strong>{{ $insights['top_crop'] ?? 'No data' }}</strong></li>
                <li><span>Most common farming stage</span><strong>{{ $insights['top_stage'] ?? 'No data' }}</strong></li>
                <li><span>Month with highest rainfall</span><strong>{{ $insights['peak_rainfall_month'] ?? 'No data' }}</strong></li>
            </ul>
        </section>
    </div>

    <script id="admin-analytics-chart-data" type="application/json">@json($charts)</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
@endsection

