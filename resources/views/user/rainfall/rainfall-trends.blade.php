@php
    $dataPeriod = $data_period ?? '2014–2024';
    $cropLabel = $crop_type ? ucfirst((string) $crop_type) : 'General crops';
    $todayRainfall = isset($today_rainfall_mm) && is_numeric($today_rainfall_mm)
        ? round((float) $today_rainfall_mm, 1)
        : (is_numeric($avg_monthly_rainfall ?? null) ? round(((float) $avg_monthly_rainfall) / 30, 1) : null);
    $weekRainfall = is_numeric($todayRainfall) ? round($todayRainfall * 7, 1) : null;
    $monthRainfall = is_numeric($avg_monthly_rainfall ?? null) ? round((float) $avg_monthly_rainfall, 1) : null;

    $rainStatusLabel = 'No historical data';
    if (is_numeric($monthRainfall)) {
        $rainStatusLabel = $monthRainfall >= 220
            ? 'Heavy Rain'
            : ($monthRainfall >= 120 ? 'Moderate Rain' : 'Light Rain');
    }

    $monthlyTrendData = $monthly_trend ?? [];
    $dailyTrendData = array_map(function ($row) {
        return [
            'label' => $row['month'] ?? '',
            'value' => isset($row['avg_rainfall']) ? round(((float) $row['avg_rainfall']) / 30, 2) : 0,
        ];
    }, $monthlyTrendData);

    $weeklyTrendData = array_map(function ($row) {
        return [
            'label' => $row['month'] ?? '',
            'value' => isset($row['avg_rainfall']) ? round(((float) $row['avg_rainfall']) / 4.3, 2) : 0,
        ];
    }, $monthlyTrendData);

    $monthlyChartData = array_map(function ($row) {
        return [
            'label' => $row['month'] ?? '',
            'value' => isset($row['avg_rainfall']) ? round((float) $row['avg_rainfall'], 2) : 0,
        ];
    }, $monthlyTrendData);

    $yearlyChartData = array_map(function ($row) {
        return [
            'label' => (string) ($row['year'] ?? ''),
            'value' => isset($row['total_rainfall']) ? round((float) $row['total_rainfall'], 2) : 0,
        ];
    }, $yearly_totals ?? []);

    $historyRows = array_map(function ($row) {
        $total = (float) ($row['total_rainfall'] ?? 0);
        $status = $total >= 2200 ? 'Heavy Rain' : ($total >= 1400 ? 'Moderate Rain' : 'Light Rain');
        return [
            'date' => (string) ($row['year'] ?? '—'),
            'amount' => round($total, 1) . ' mm',
            'status' => $status,
        ];
    }, $yearly_totals ?? []);

    $insightItems = array_values(array_filter([
        $rainfall_insight ?? null,
        $seasonal_insight ?? null,
        $farming_stage_note ?? null,
    ]));

    $rainReco = is_array($recommendation ?? null) ? $recommendation : [];
    $rainPlan = is_array($rainReco['field_action_plan'] ?? null) ? $rainReco['field_action_plan'] : [];
    $aiStatus = strtolower((string) ($rainReco['ai_status'] ?? 'failed'));
    $aiModel = trim((string) ($rainReco['ai_model'] ?? ''));
    $aiError = trim((string) ($rainReco['ai_error'] ?? ''));
    $showAiDebug = app()->environment('local') || (bool) config('app.debug');
    $riskLevel = strtolower((string) ($rainReco['rainfall_risk_level'] ?? 'moderate'));
    $riskLabel = $riskLevel === 'high' ? 'High' : ($riskLevel === 'low' ? 'Low' : 'Moderate');
    $riskMap = [
        'low' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
        'moderate' => 'bg-amber-100 text-amber-700 border border-amber-200',
        'high' => 'bg-rose-100 text-rose-700 border border-rose-200',
    ];
    $riskBadge = $riskMap[$riskLevel] ?? $riskMap['moderate'];
@endphp
@extends('layouts.user')

@section('title', 'Historical Rainfall Trends – AGRIGUARD')

@section('body-class', 'rainfall-page min-h-screen bg-[#F4F6F5]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            <header class="ag-card ag-welcome-gradient overflow-hidden">
                <div class="relative px-5 py-5 sm:px-6 sm:py-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-white/90 mb-2">
                            <a href="{{ route('weather-details') }}" class="inline-flex items-center gap-1 rounded-full bg-white/20 hover:bg-white/30 px-2.5 py-1 transition-colors">
                                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                                Back to Weather
                            </a>
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5 opacity-80"></i>
                                Weather / Rainfall
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">Historical Rainfall Trends</h1>
                        <p class="mt-1.5 text-white/90 text-sm">Rainfall history to support easier farm planning and field preparation.</p>
                        <p class="mt-2 text-white/95 text-sm flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-4 h-4 shrink-0 opacity-90"></i>
                            {{ $farm_location_display ?? 'Amulung, Cagayan' }}
                        </p>
                        <p class="mt-1 text-white/85 text-sm">{{ $cropLabel }} • Historical records</p>
                    </div>
                    <div class="rainfall-header-meta">
                        <span class="rainfall-date-chip">
                            <i data-lucide="calendar-range" class="w-4 h-4"></i>
                            {{ $dataPeriod }}
                        </span>
                        <span class="rainfall-date-chip rainfall-date-chip--muted">
                            <i data-lucide="cloud-rain" class="w-4 h-4"></i>
                            Rainfall Archive
                        </span>
                    </div>
                </div>
            </header>

            <section class="rainfall-summary-grid" aria-label="Rainfall summary cards">
                <article class="farm-summary-card farm-summary-card--green">
                    <span class="farm-summary-icon"><i data-lucide="cloud-drizzle" class="text-[#2E7D32]"></i></span>
                    <div class="farm-summary-content">
                        <p class="farm-summary-label">Today&rsquo;s Rainfall</p>
                        <p class="farm-summary-value">{{ is_numeric($todayRainfall) ? number_format((float) $todayRainfall, 1) . ' mm' : 'No data' }}</p>
                    </div>
                </article>
                <article class="farm-summary-card farm-summary-card--blue">
                    <span class="farm-summary-icon"><i data-lucide="calendar-days" class="text-blue-600"></i></span>
                    <div class="farm-summary-content">
                        <p class="farm-summary-label">This Week&rsquo;s Rainfall</p>
                        <p class="farm-summary-value">{{ is_numeric($weekRainfall) ? number_format((float) $weekRainfall, 1) . ' mm' : 'No data' }}</p>
                    </div>
                </article>
                <article class="farm-summary-card farm-summary-card--amber">
                    <span class="farm-summary-icon"><i data-lucide="calendar" class="text-amber-600"></i></span>
                    <div class="farm-summary-content">
                        <p class="farm-summary-label">This Month&rsquo;s Rainfall</p>
                        <p class="farm-summary-value">{{ is_numeric($monthRainfall) ? number_format((float) $monthRainfall, 1) . ' mm' : 'No data' }}</p>
                    </div>
                </article>
                <article class="farm-summary-card farm-summary-card--violet">
                    <span class="farm-summary-icon"><i data-lucide="shield-check" class="text-violet-600"></i></span>
                    <div class="farm-summary-content">
                        <p class="farm-summary-label">Rain Status</p>
                        <p class="farm-summary-value">{{ $rainStatusLabel }}</p>
                    </div>
                </article>
            </section>

            <article class="ag-card border border-slate-200 bg-white p-4 sm:p-5 rounded-2xl shadow-sm" aria-label="Rainfall-based AI recommendation">
                <div class="mx-auto w-full max-w-4xl space-y-4">
                    @if ($showAiDebug)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-xs text-slate-700 font-semibold">
                                AI API Status:
                                <span class="{{ $aiStatus === 'success' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $aiStatus === 'success' ? 'Success' : 'Failed' }}</span>
                            </p>
                            @if ($aiModel !== '')
                                <p class="text-xs text-slate-600 mt-1">Model: {{ $aiModel }}</p>
                            @endif
                            @if ($aiStatus !== 'success' && $aiError !== '')
                                <p class="text-xs text-slate-600 mt-1">Error: {{ $aiError }}</p>
                            @endif
                        </div>
                    @endif

                    @if (!empty($recommendation_failed))
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                            <p class="text-sm text-amber-900 font-semibold">Live rainfall AI advice is temporarily unavailable. Showing a rainfall-aware fallback recommendation.</p>
                        </div>
                    @endif

                    <header class="space-y-3 rounded-xl border border-blue-200 bg-blue-50/60 p-4 sm:p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600 flex items-center gap-1.5">
                            <i data-lucide="cloud-rain" class="w-4 h-4 text-blue-700"></i>
                            RAINFALL-BASED FARM ACTION
                        </p>
                        <p class="text-xl sm:text-2xl font-extrabold text-slate-900 leading-tight">{{ $rainReco['main_rainfall_advice'] ?? 'Plan field work around rain timing and water movement today.' }}</p>
                        <div class="flex flex-wrap items-center gap-2.5 text-xs">
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 text-blue-700 px-3 py-1.5 border border-blue-200">
                                <i data-lucide="gauge" class="w-3.5 h-3.5"></i>
                                Rainfall Risk Score: {{ $rainReco['rainfall_risk_score'] ?? 5 }}/10
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1.5 border border-emerald-200">
                                <i data-lucide="brain" class="w-3.5 h-3.5"></i>
                                AI Confidence: {{ $rainReco['ai_confidence'] ?? 'Medium' }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 font-semibold {{ $riskBadge }}">
                                ⚠️ Rainfall Risk Level: {{ $riskLabel }}
                            </span>
                        </div>
                    </header>

                    <section class="rounded-lg border border-blue-200 bg-blue-50/50 p-3.5 shadow-sm">
                        <p class="text-sm font-semibold text-blue-900 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="waves" class="w-4 h-4"></i>
                            Rainfall Insight
                        </p>
                        <p class="text-sm text-blue-900 leading-relaxed">{{ $rainReco['rainfall_insight'] ?? ($trend_insight ?? 'Use rainfall patterns to guide field timing and water control.') }}</p>
                    </section>

                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                            <i data-lucide="list-checks" class="w-4 h-4 text-blue-700"></i>
                            Field Action Plan
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-900 mb-1">🌅 Early Day</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $rainPlan['early_day'] ?? 'Inspect drainage and field moisture first.' }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-900 mb-1">☀️ Midday</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $rainPlan['midday'] ?? 'Adjust field activity based on rain updates.' }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-900 mb-1">🌙 Late Day</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $rainPlan['late_day'] ?? 'Prepare drainage and tools for overnight rain.' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <div class="rounded-xl border border-cyan-200 bg-cyan-50 p-3.5 shadow-sm">
                            <p class="text-sm font-semibold text-cyan-800 mb-1.5 flex items-center gap-1.5">
                                <i data-lucide="droplets" class="w-4 h-4"></i>
                                Drainage / Irrigation Advice
                            </p>
                            <p class="text-sm text-cyan-900 leading-relaxed">{{ $rainReco['drainage_irrigation_advice'] ?? ($soil_saturation_text ?? 'Balance irrigation with expected rainfall and clear drainage channels.') }}</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 shadow-sm">
                            <p class="text-sm font-semibold text-amber-800 mb-1.5 flex items-center gap-1.5">
                                <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                                What to Avoid Today
                            </p>
                            <p class="text-sm text-amber-900 leading-relaxed">{{ $rainReco['what_to_avoid_today'] ?? 'Avoid activities that trap water in low parts of the field.' }}</p>
                        </div>
                    </section>
                </div>
            </article>

            <section class="ag-card weather-dashboard-card p-4 sm:p-5" aria-label="Rainfall chart overview">
                <div class="weather-featured-card">
                    <div class="weather-featured-head">
                        <h2 class="weather-section-title">
                            <i data-lucide="bar-chart-3" class="w-4 h-4"></i>
                            Rainfall Trend Overview
                        </h2>
                        <span class="rainfall-trend-unit">millimeters (mm)</span>
                    </div>

                    <nav class="rainfall-filter-tabs" aria-label="Rainfall trend filters">
                        <button type="button" class="rainfall-filter-tab is-active" data-trend="daily">Daily</button>
                        <button type="button" class="rainfall-filter-tab" data-trend="weekly">Weekly</button>
                        <button type="button" class="rainfall-filter-tab" data-trend="monthly">Monthly</button>
                        <button type="button" class="rainfall-filter-tab" data-trend="yearly">Yearly</button>
                    </nav>

                    <div class="ag-chart-wrap mt-3">
                        <canvas id="rainfallTrendChart" aria-label="Rainfall trend chart"></canvas>
                        @if (empty($monthly_trend) && empty($yearly_totals))
                            <p class="text-slate-400 text-sm absolute inset-0 flex items-center justify-center">No historical rainfall data yet</p>
                        @endif
                    </div>
                    <p class="rainfall-chart-helper">Use the filters to compare short-term and long-term rainfall patterns before planning field work.</p>
                </div>
            </section>

            <section class="ag-card p-5 sm:p-6" aria-label="Rainfall history list">
                <h2 class="weather-section-title weather-section-title--sub">
                    <i data-lucide="list" class="w-4 h-4"></i>
                    Rainfall History
                </h2>
                <div class="forecast-vertical-list">
                    @forelse ($historyRows as $row)
                        <article class="forecast-vertical-item rainfall-history-item">
                            <p class="forecast-vertical-day">{{ $row['date'] }}</p>
                            <span class="forecast-vertical-icon"><i data-lucide="calendar"></i></span>
                            <div class="forecast-vertical-meta">
                                <p class="forecast-vertical-condition">Total Rainfall</p>
                                <p class="forecast-vertical-temp">{{ $row['amount'] }}</p>
                            </div>
                            <p class="forecast-vertical-rain">{{ $row['status'] }}</p>
                        </article>
                    @empty
                        <article class="forecast-vertical-item forecast-vertical-item--empty">
                            <p class="forecast-vertical-day">No records</p>
                            <span class="forecast-vertical-icon"><i data-lucide="cloud-off"></i></span>
                            <div class="forecast-vertical-meta">
                                <p class="forecast-vertical-condition">Rainfall History</p>
                                <p class="forecast-vertical-temp">Data unavailable</p>
                            </div>
                            <p class="forecast-vertical-rain">No data</p>
                        </article>
                    @endforelse
                </div>
            </section>
        </div>
    </section>

    <script type="application/json" id="rainfall-page-data">{!! json_encode([
        'daily' => $dailyTrendData,
        'weekly' => $weeklyTrendData,
        'monthly' => $monthlyChartData,
        'yearly' => $yearlyChartData,
    ]) !!}</script>
@endsection

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
@endpush
