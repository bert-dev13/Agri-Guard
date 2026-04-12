@php
    $dataPeriodRaw = $data_period ?? null;
    $dataPeriodFromDb = is_string($dataPeriodRaw) && trim($dataPeriodRaw) !== '' && trim($dataPeriodRaw) !== '—';
    $farmLocForHero = trim((string) ($farm_location_display ?? ''));
    $dataPeriod = $dataPeriodFromDb
        ? trim((string) $dataPeriodRaw)
        : ($farmLocForHero !== '' ? $farmLocForHero : 'Barangay Calamagui, Amulung, Cagayan');
    $cropLabel = $crop_type ? ucfirst((string) $crop_type) : 'General crops';
    $todayRainfall = isset($today_rainfall_mm) && is_numeric($today_rainfall_mm)
        ? round((float) $today_rainfall_mm, 1)
        : (is_numeric($avg_monthly_rainfall ?? null) ? round(((float) $avg_monthly_rainfall) / 30, 1) : null);
    $weekRainfall = is_numeric($todayRainfall) ? round($todayRainfall * 7, 1) : null;
    $monthRainfall = is_numeric($avg_monthly_rainfall ?? null) ? round((float) $avg_monthly_rainfall, 1) : null;

    $rainStatusLabel = 'No historical data';
    if (is_numeric($monthRainfall)) {
        $rainStatusLabel = $monthRainfall >= 220
            ? 'Heavy rain'
            : ($monthRainfall >= 120 ? 'Moderate rain' : 'Light rain');
    }

    $rainTrendWord = 'No major change';
    $monthlyForTrend = $monthly_trend ?? [];
    if (count($monthlyForTrend) >= 2) {
        $last = (float) ($monthlyForTrend[count($monthlyForTrend) - 1]['avg_rainfall'] ?? 0);
        $prev = (float) ($monthlyForTrend[count($monthlyForTrend) - 2]['avg_rainfall'] ?? 0);
        if ($prev > 0 && $last > $prev * 1.08) {
            $rainTrendWord = 'More rain expected';
        } elseif ($prev > 0 && $last < $prev * 0.92) {
            $rainTrendWord = 'Less rain expected';
        }
    }
    $rainTrendIcon = $rainTrendWord === 'More rain expected'
        ? 'trending-up'
        : ($rainTrendWord === 'Less rain expected' ? 'trending-down' : 'minus');

    $hasChartData = !empty($monthly_trend) || !empty($yearly_totals);

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
        $status = $total >= 2200 ? 'Heavy' : ($total >= 1400 ? 'Moderate' : 'Light');

        return [
            'date' => (string) ($row['year'] ?? '—'),
            'amount' => round($total, 1) . ' mm',
            'status' => $status,
        ];
    }, $yearly_totals ?? []);

    $rainReco = is_array($recommendation ?? null) ? $recommendation : [];
    $rainPlan = is_array($rainReco['field_action_plan'] ?? null) ? $rainReco['field_action_plan'] : [];
    $rAiStatus = strtolower((string) ($rainReco['ai_status'] ?? 'failed'));
    $rAiError = trim((string) ($rainReco['ai_error'] ?? ''));
    $rAiAdvisoryReady = $rAiStatus === 'success';
    $rAiUnavailableMsg = 'AI advisory temporarily unavailable.';
    $rBlockedMsg = $rAiStatus === 'missing_context' && $rAiError !== ''
        ? $rAiError
        : ($rAiStatus === 'missing_context' ? 'Please update your crop type and farming stage in Farm Settings to receive AI advisory from Together AI.' : $rAiUnavailableMsg);
    $rainRiskRaw = strtolower((string) ($rainReco['risk'] ?? $rainReco['rainfall_risk_level'] ?? ''));
    $rainRisk = $rAiAdvisoryReady && $rainRiskRaw !== '' ? $rainRiskRaw : '';
    $rainRiskBadgeClass = $rainRisk === 'low'
        ? 'rainfall-page__risk--low'
        : ($rainRisk === 'high' ? 'rainfall-page__risk--high' : 'rainfall-page__risk--mid');

    $clayDataUri = static function (string $svg): string {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    };

    $clay = [
        'rain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="rc" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F1F5F9"/><stop offset="100%" stop-color="#94A3B8"/></linearGradient><linearGradient id="rd" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#7DD3FC"/><stop offset="100%" stop-color="#38BDF8"/></linearGradient><filter id="dsr"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><ellipse cx="24" cy="22" rx="15" ry="10" fill="url(#rc)" filter="url(#dsr)"/><ellipse cx="17" cy="21" rx="9" ry="7" fill="#E2E8F0"/><path d="M16 34v8M24 32v9M32 34v7" stroke="url(#rd)" stroke-width="3.5" stroke-linecap="round" filter="url(#dsr)"/></svg>',
        'droplet' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="dg" cx="35%" cy="25%" r="65%"><stop offset="0%" stop-color="#A7F3D0"/><stop offset="100%" stop-color="#34D399"/></radialGradient><filter id="dsd"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 10 C16 22 12 28 12 32a12 12 0 0 0 24 0c0-4-4-10-12-22z" fill="url(#dg)" filter="url(#dsd)"/><ellipse cx="20" cy="30" rx="4" ry="5" fill="#fff" opacity=".35"/></svg>',
        'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="cal" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#FBBF24"/></linearGradient><filter id="dscal"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><rect x="10" y="14" width="28" height="26" rx="4" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="2" filter="url(#dscal)"/><rect x="10" y="14" width="28" height="9" rx="4" fill="url(#cal)"/><path d="M16 10v8M32 10v8" stroke="#D97706" stroke-width="3" stroke-linecap="round"/></svg>',
        'cal_range' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="cr" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#DDD6FE"/><stop offset="100%" stop-color="#A78BFA"/></linearGradient><filter id="dscr"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><rect x="8" y="16" width="14" height="22" rx="3" fill="#F8FAFC" stroke="#E9D5FF" stroke-width="1.5" filter="url(#dscr)"/><rect x="26" y="12" width="14" height="26" rx="3" fill="url(#cr)" filter="url(#dscr)"/><path d="M12 22h6M12 28h6" stroke="#94A3B8" stroke-width="2" stroke-linecap="round"/></svg>',
        'pin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="pg" cx="35%" cy="20%" r="70%"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#D97706"/></radialGradient><filter id="dsp"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><path d="M24 8c-6 0-10 4.5-10 10 0 8 10 22 10 22s10-14 10-22c0-5.5-4-10-10-10z" fill="url(#pg)" filter="url(#dsp)"/><circle cx="24" cy="18" r="4" fill="#fff" opacity=".5"/></svg>',
        'chart' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsch"><feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="28" width="8" height="12" rx="2" fill="#86EFAC" filter="url(#dsch)"/><rect x="20" y="18" width="8" height="22" rx="2" fill="#A78BFA" filter="url(#dsch)"/><rect x="32" y="22" width="8" height="18" rx="2" fill="#FCD34D" filter="url(#dsch)"/></svg>',
        'trend' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="tg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#6EE7B7"/><stop offset="100%" stop-color="#34D399"/></linearGradient><filter id="dst"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M8 36 L18 24 L28 30 L40 14" fill="none" stroke="url(#tg)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" filter="url(#dst)"/><circle cx="40" cy="14" r="4" fill="#FCD34D" filter="url(#dst)"/></svg>',
        'sprout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="sg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#86EFAC"/><stop offset="100%" stop-color="#22C55E"/></linearGradient><filter id="dss2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M24 38v-8" stroke="#A3E635" stroke-width="4" stroke-linecap="round" filter="url(#dss2)"/><path d="M24 30 Q14 22 18 12 Q24 18 24 30" fill="url(#sg)" filter="url(#dss2)"/><path d="M24 30 Q34 22 30 12 Q24 18 24 30" fill="url(#sg)" opacity=".9" filter="url(#dss2)"/></svg>',
        'gauge' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="gg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#FEF3C7"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsg"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M10 32 A16 16 0 0 1 38 32" fill="none" stroke="#E2E8F0" stroke-width="5" stroke-linecap="round"/><path d="M10 32 A16 16 0 0 1 28 18" fill="none" stroke="url(#gg)" stroke-width="5" stroke-linecap="round" filter="url(#dsg)"/><circle cx="24" cy="32" r="3" fill="#F59E0B" filter="url(#dsg)"/></svg>',
        'brain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="bg" cx="30%" cy="30%" r="65%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="100%" stop-color="#A78BFA"/></radialGradient><filter id="dsb"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M18 14c-4 0-7 3-7 7 0 2 1 4 2 5-1 1-2 3-2 5 0 5 4 9 9 9h10c5 0 9-4 9-9 0-2-1-4-2-5 1-1 2-3 2-5 0-4-3-7-7-7-1-3-4-5-8-5s-7 2-8 5z" fill="url(#bg)" filter="url(#dsb)"/></svg>',
        'alert' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsa"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 8 L40 38H8L24 8z" fill="url(#ag)" filter="url(#dsa)"/><path d="M24 16v12M24 32v2" stroke="#92400E" stroke-width="2.5" stroke-linecap="round"/></svg>',
        'sun' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="cs" cx="32%" cy="28%" r="70%"><stop offset="0%" stop-color="#FFFBEB"/><stop offset="45%" stop-color="#FDE047"/><stop offset="100%" stop-color="#EAB308"/></radialGradient><filter id="ds"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><circle cx="24" cy="26" r="13" fill="url(#cs)" filter="url(#ds)"/><ellipse cx="19" cy="21" rx="5" ry="3" fill="#fff" opacity=".45"/></svg>',
        'partly_cloudy' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="cs2" cx="30%" cy="25%" r="65%"><stop offset="0%" stop-color="#FEF9C3"/><stop offset="100%" stop-color="#FACC15"/></radialGradient><linearGradient id="cg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F8FAFC"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="ds2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><circle cx="14" cy="16" r="8" fill="url(#cs2)" filter="url(#ds2)"/><ellipse cx="26" cy="30" rx="14" ry="10" fill="url(#cg)" filter="url(#ds2)"/><ellipse cx="20" cy="28" rx="9" ry="7" fill="#E2E8F0"/></svg>',
        'moon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="mg" cx="28%" cy="22%" r="75%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="55%" stop-color="#A78BFA"/><stop offset="100%" stop-color="#6D28D9"/></radialGradient><filter id="dsm"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0f172a" flood-opacity=".22"/></filter></defs><circle cx="22" cy="24" r="14" fill="url(#mg)" filter="url(#dsm)"/><circle cx="33" cy="20" r="11" fill="#ffffff"/></svg>',
        'list' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsl"><feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="10" y="12" width="28" height="6" rx="2" fill="#E0E7FF" filter="url(#dsl)"/><rect x="10" y="22" width="20" height="6" rx="2" fill="#FEF3C7" filter="url(#dsl)"/><rect x="10" y="32" width="24" height="6" rx="2" fill="#D1FAE5" filter="url(#dsl)"/></svg>',
        'cloud_off' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="co" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F1F5F9"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="dsco"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><ellipse cx="24" cy="26" rx="14" ry="9" fill="url(#co)" filter="url(#dsco)"/><path d="M12 36 L36 14" stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/></svg>',
    ];

    $rImg = static function (string $key) use ($clay, $clayDataUri): string {
        return $clayDataUri($clay[$key] ?? $clay['rain']);
    };

    $heroMonthLine = is_numeric($monthRainfall) ? number_format((float) $monthRainfall, 1) . ' mm average monthly rain' : 'Records loading…';
    $peakLine = $wettest_month ?? null;
    $peakLine = $peakLine ? 'Wettest month: ' . $peakLine : null;
@endphp
@extends('layouts.user')

@section('title', 'Historical Rainfall Trends – AGRIGUARD')

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('body-class', 'dashboard-page rainfall-page min-h-screen bg-[#EEF1F6]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            <header class="dashboard-hero rainfall-page-hero ag-card" aria-labelledby="rainfall-page-hero-heading">
                <div class="dashboard-hero__accent" aria-hidden="true">
                    <span class="dashboard-hero__accent-shimmer"></span>
                </div>
                <div class="dashboard-hero__canvas" aria-hidden="true">
                    <span class="dashboard-hero__blob dashboard-hero__blob--a"></span>
                    <span class="dashboard-hero__blob dashboard-hero__blob--b"></span>
                    <span class="dashboard-hero__blob dashboard-hero__blob--c"></span>
                    <span class="dashboard-hero__grain"></span>
                </div>
                <div class="dashboard-hero__layout">
                    <div class="dashboard-hero__main">
                        <div class="dashboard-hero__title-row">
                            <div class="dashboard-hero__title-stack">
                                <h1 id="rainfall-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">Rainfall Trends</span>
                                </h1>
                                <p class="dashboard-hero__subtitle">
                                    <span class="dashboard-hero__subtitle-ic" aria-hidden="true">
                                        <i data-lucide="cloud-rain" class="dashboard-hero__lucide dashboard-hero__lucide--xs"></i>
                                    </span>
                                    <span>Weather</span>
                                </p>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill rainfall-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
                            </span>
                            <span class="dashboard-hero__pill rainfall-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-range" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $cropLabel }} · {{ $dataPeriod }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card rainfall-page-hero__rain-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow"></span>
                            <span class="dashboard-hero__weather-ring">
                                <img src="{{ $rImg('rain') }}" alt="" class="rainfall-clay-ic rainfall-clay-ic--hero" width="36" height="36" decoding="async">
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="cloud-rain" class="dashboard-hero__lucide dashboard-hero__lucide--sm" aria-hidden="true"></i>
                                    Rainfall today
                                </span>
                                <span class="dashboard-hero__weather-temp">{{ is_numeric($monthRainfall) ? number_format((float) $monthRainfall, 0) : '—' }}<span class="dashboard-hero__weather-unit">mm</span></span>
                                <span class="dashboard-hero__weather-desc">Rain trend: {{ $rainTrendWord }}</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            <article class="ag-card rainfall-page__rain-highlight" aria-label="Rainfall highlight">
                <div class="rainfall-page__rain-highlight-top">
                    <span class="rainfall-page__rain-icon-wrap" aria-hidden="true">
                        <img src="{{ $rImg('rain') }}" alt="" class="rainfall-clay-ic rainfall-clay-ic--hero" width="40" height="40" decoding="async">
                    </span>
                    <div class="rainfall-page__rain-copy">
                        <p class="rainfall-page__rain-label">Rainfall today</p>
                        <p class="rainfall-page__rain-value">{{ is_numeric($todayRainfall) ? number_format((float) $todayRainfall, 1) : '—' }}<span class="rainfall-page__rain-unit">mm</span></p>
                        <p class="rainfall-page__rain-status">{{ $rainStatusLabel }}</p>
                        <p class="rainfall-page__rain-sub">{{ $heroMonthLine }}</p>
                    </div>
                    <span class="rainfall-page__rain-trend-pill">
                        <i data-lucide="{{ $rainTrendIcon }}" class="dashboard-hero__lucide dashboard-hero__lucide--sm" aria-hidden="true"></i>
                        Rain trend: {{ $rainTrendWord }}
                    </span>
                </div>
                <div class="rainfall-page__rain-stats-grid">
                    <article class="rainfall-page__rain-stat rainfall-page__rain-stat--sky">
                        <p class="rainfall-page__rain-stat-label">Past 7 days</p>
                        <p class="rainfall-page__rain-stat-value">{{ is_numeric($weekRainfall) ? number_format((float) $weekRainfall, 1) . ' mm' : '—' }}</p>
                    </article>
                    <article class="rainfall-page__rain-stat rainfall-page__rain-stat--mint">
                        <p class="rainfall-page__rain-stat-label">Average monthly rain</p>
                        <p class="rainfall-page__rain-stat-value">{{ is_numeric($monthRainfall) ? number_format((float) $monthRainfall, 1) . ' mm' : '—' }}</p>
                    </article>
                    <article class="rainfall-page__rain-stat rainfall-page__rain-stat--violet">
                        <p class="rainfall-page__rain-stat-label">Rain trend</p>
                        <p class="rainfall-page__rain-stat-value">{{ $rainTrendWord }}</p>
                    </article>
                </div>
            </article>

            <article class="ag-card dash-smart rainfall-page__smart rounded-3xl border border-emerald-200 bg-emerald-50/70 p-4 sm:p-5" aria-label="Rainfall farm action">
                <div class="dash-smart__debug">
                    <p class="text-xs font-semibold text-slate-700">
                        @if ($rAiAdvisoryReady)
                            <span class="text-emerald-700">AI Smart Advisory: Active</span>
                        @elseif ($rAiStatus === 'missing_context')
                            <span class="text-amber-800">AI Smart Advisory: Profile incomplete</span>
                        @else
                            <span class="text-rose-700">AI Smart Advisory: Unavailable</span>
                        @endif
                    </p>
                </div>

                <div class="dash-smart__head rounded-2xl bg-emerald-100/80 px-3 py-2.5">
                    <span class="dash-smart__chip" aria-hidden="true">
                        <img src="{{ $rImg('rain') }}" alt="" width="18" height="18" decoding="async" class="rainfall-clay-ic--chip">
                        Rainfall Action
                    </span>
                    <div class="dash-smart__badges">
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $rAiAdvisoryReady ? $rainRiskBadgeClass : 'bg-slate-100 text-slate-600 border border-slate-200' }}">
                            Risk: {{ $rAiAdvisoryReady ? ucfirst($rainRisk) : '—' }}
                        </span>
                        @if ($rAiAdvisoryReady && trim((string) ($rainReco['ai_confidence'] ?? '')) !== '')
                            <span class="dash-smart__badge dash-smart__badge--conf-high">
                                <img src="{{ $rImg('brain') }}" class="dash-smart__badge-ic-img" width="12" height="12" alt="">
                                {{ $rainReco['ai_confidence'] }}
                            </span>
                        @endif
                    </div>
                </div>

                <p class="dash-smart__action">{{ $rAiAdvisoryReady ? trim((string) ($rainReco['main_rainfall_advice'] ?? '')) : $rBlockedMsg }}</p>
            </article>

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5 rainfall-page__timeline" aria-label="Field day plan">
                <h2 class="inline-flex rounded-xl bg-sky-100 px-3 py-1.5 text-sm font-extrabold uppercase tracking-[0.1em] text-sky-900">Field Day Plan</h2>
                <div class="rainfall-page__timeline-list mt-3">
                    <article class="rainfall-page__timeline-item rainfall-page__timeline-item--morning">
                        <span class="rainfall-page__timeline-dot"><img src="{{ $rImg('sun') }}" alt="" width="20" height="20" decoding="async"></span>
                        <div><p class="text-sm font-semibold text-slate-800">Early day</p><p class="text-sm text-slate-600">{{ $rAiAdvisoryReady ? trim((string) ($rainPlan['early_day'] ?? '')) : $rBlockedMsg }}</p></div>
                    </article>
                    <article class="rainfall-page__timeline-item rainfall-page__timeline-item--midday">
                        <span class="rainfall-page__timeline-dot"><img src="{{ $rImg('partly_cloudy') }}" alt="" width="20" height="20" decoding="async"></span>
                        <div><p class="text-sm font-semibold text-slate-800">Midday</p><p class="text-sm text-slate-600">{{ $rAiAdvisoryReady ? trim((string) ($rainPlan['midday'] ?? '')) : $rBlockedMsg }}</p></div>
                    </article>
                    <article class="rainfall-page__timeline-item rainfall-page__timeline-item--late">
                        <span class="rainfall-page__timeline-dot"><img src="{{ $rImg('moon') }}" alt="" width="20" height="20" decoding="async"></span>
                        <div><p class="text-sm font-semibold text-slate-800">Late day</p><p class="text-sm text-slate-600">{{ $rAiAdvisoryReady ? trim((string) ($rainPlan['late_day'] ?? '')) : $rBlockedMsg }}</p></div>
                    </article>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2" aria-label="Water, drainage, and avoid">
                <div class="ag-card rounded-3xl border border-cyan-100 bg-cyan-50/70 p-4">
                    <div class="dash-split__card dash-split__card--water rainfall-page__split-water">
                        <div class="dash-split__head">
                            <img src="{{ $rImg('droplet') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Water & Drainage
                        </div>
                        <p class="dash-split__body">{{ $rAiAdvisoryReady ? trim((string) ($rainReco['drainage_irrigation_advice'] ?? '')) : $rBlockedMsg }}</p>
                    </div>
                </div>
                <div class="ag-card rounded-3xl border border-rose-100 bg-rose-50/70 p-4">
                    <div class="dash-split__card dash-split__card--avoid rainfall-page__split-avoid">
                        <div class="dash-split__head">
                            <img src="{{ $rImg('alert') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Avoid Today
                        </div>
                        <p class="dash-split__body">{{ $rAiAdvisoryReady ? trim((string) ($rainReco['what_to_avoid_today'] ?? '')) : $rBlockedMsg }}</p>
                    </div>
                </div>
            </section>

            <section class="ag-card rainfall-page__chart-card" aria-label="Rainfall chart">
                <div class="rainfall-page__chart-head">
                    <img src="{{ $rImg('chart') }}" alt="" class="rainfall-clay-ic rainfall-clay-ic--head" width="26" height="26" decoding="async">
                    <div>
                        <h2 class="rainfall-page__chart-title">Rainfall chart</h2>
                        <p class="rainfall-page__chart-sub">Switch view to compare days, weeks, months, or years (mm).</p>
                    </div>
                    <span class="rainfall-page__unit-pill">mm</span>
                </div>
                <nav class="rainfall-page__tabs" aria-label="Chart range">
                    <button type="button" class="rainfall-page__tab is-active" data-trend="daily">Daily</button>
                    <button type="button" class="rainfall-page__tab" data-trend="weekly">Weekly</button>
                    <button type="button" class="rainfall-page__tab" data-trend="monthly">Monthly</button>
                    <button type="button" class="rainfall-page__tab" data-trend="yearly">Yearly</button>
                </nav>
                <div class="rainfall-page__chart-canvas">
                    <canvas id="rainfallTrendChart" aria-label="Rainfall trend chart"></canvas>
                    @if (!$hasChartData)
                        <div class="rainfall-page__chart-empty" role="status">
                            <img src="{{ $rImg('cloud_off') }}" alt="" width="48" height="48" decoding="async">
                            <p class="rainfall-page__chart-empty-title">No historical series yet</p>
                            <p class="rainfall-page__chart-empty-txt">When records are available, your rainfall curve will appear here.</p>
                        </div>
                    @endif
                </div>
                <p class="rainfall-page__chart-foot">Tip: use monthly or yearly view before big field jobs or drainage work.</p>
            </section>

            <article class="ag-card rainfall-page__summary-card" aria-label="Rainfall at a glance">
                <div class="rainfall-page__summary-top">
                    <div>
                        <h2 class="rainfall-page__summary-kicker">At a glance</h2>
                        <p class="rainfall-page__summary-status">{{ $rainStatusLabel }}</p>
                    </div>
                    <div class="rainfall-page__summary-metrics">
                        <p class="rainfall-page__summary-big">{{ is_numeric($monthRainfall) ? number_format((float) $monthRainfall, 0) : '—' }}<span class="rainfall-page__summary-unit">mm</span></p>
                        <p class="rainfall-page__summary-caption">Average monthly rain</p>
                    </div>
                </div>
                @if ($peakLine)
                    <p class="rainfall-page__summary-note">{{ $peakLine }}@if (is_numeric($avg_annual_rainfall ?? null)) · ~{{ number_format((float) $avg_annual_rainfall, 0) }} mm / year avg @endif</p>
                @elseif (is_numeric($avg_annual_rainfall ?? null))
                    <p class="rainfall-page__summary-note">~{{ number_format((float) $avg_annual_rainfall, 0) }} mm average yearly total</p>
                @endif
                <div class="rainfall-page__metrics-grid">
                    <article class="rainfall-page__metric rainfall-page__metric--mint">
                        <span class="rainfall-page__metric-ic"><img src="{{ $rImg('rain') }}" alt="" width="22" height="22" decoding="async"></span>
                        <p class="rainfall-page__metric-lbl">Today’s rain</p>
                        <p class="rainfall-page__metric-val">{{ is_numeric($todayRainfall) ? number_format((float) $todayRainfall, 1) . ' mm' : '—' }}</p>
                        <p class="rainfall-page__metric-hint">{{ $rainStatusLabel }}</p>
                    </article>
                    <article class="rainfall-page__metric rainfall-page__metric--slate">
                        <span class="rainfall-page__metric-ic"><img src="{{ $rImg('calendar') }}" alt="" width="22" height="22" decoding="async"></span>
                        <p class="rainfall-page__metric-lbl">Past 7 days</p>
                        <p class="rainfall-page__metric-val">{{ is_numeric($weekRainfall) ? number_format((float) $weekRainfall, 1) . ' mm' : '—' }}</p>
                        <p class="rainfall-page__metric-hint">Recent rain amount</p>
                    </article>
                    <article class="rainfall-page__metric rainfall-page__metric--amber">
                        <span class="rainfall-page__metric-ic"><img src="{{ $rImg('calendar') }}" alt="" width="22" height="22" decoding="async"></span>
                        <p class="rainfall-page__metric-lbl">Average monthly rain</p>
                        <p class="rainfall-page__metric-val">{{ is_numeric($monthRainfall) ? number_format((float) $monthRainfall, 1) . ' mm' : '—' }}</p>
                        <p class="rainfall-page__metric-hint">Monthly average</p>
                    </article>
                    <article class="rainfall-page__metric rainfall-page__metric--violet">
                        <span class="rainfall-page__metric-ic"><img src="{{ $rImg('trend') }}" alt="" width="22" height="22" decoding="async"></span>
                        <p class="rainfall-page__metric-lbl">Rain trend</p>
                        <p class="rainfall-page__metric-val">{{ $rainTrendWord }}</p>
                        <p class="rainfall-page__metric-hint">Based on recent months</p>
                    </article>
                </div>
            </article>

            @php
                $miInsight = $monthly_trend ?? [];
                $avgRainAll = count($miInsight) > 0
                    ? array_sum(array_map(static fn ($r) => (float) ($r['avg_rainfall'] ?? 0), $miInsight)) / count($miInsight)
                    : 0.0;
                $wetMonthsInsight = [];
                foreach ($miInsight as $row) {
                    if ($avgRainAll > 0 && (float) ($row['avg_rainfall'] ?? 0) >= $avgRainAll * 1.05) {
                        $wetMonthsInsight[] = (string) ($row['month'] ?? '');
                    }
                }
                $wetMonthsInsight = array_values(array_filter($wetMonthsInsight));

                $topRowsInsight = $miInsight;
                usort($topRowsInsight, static function ($a, $b): int {
                    return ((float) ($b['avg_rainfall'] ?? 0)) <=> ((float) ($a['avg_rainfall'] ?? 0));
                });
                $top3Insight = array_slice($topRowsInsight, 0, 3);
                $peakMonthTags = array_values(array_filter(array_map(static fn ($r) => (string) ($r['month'] ?? ''), $top3Insight)));

                $insightTrendText = trim((string) ($seasonal_insight ?? ''));
                $insightPeakText = trim((string) ($rainfall_insight ?? ''));
                $insightActionText = trim((string) ($preparation_note ?? ''));
                if ($insightActionText === '') {
                    $insightActionText = trim((string) ($farming_stage_note ?? ''));
                }

                $showInsightCard = $hasChartData
                    || $insightTrendText !== ''
                    || $insightPeakText !== ''
                    || $insightActionText !== ''
                    || count($wetMonthsInsight) > 0
                    || count($peakMonthTags) > 0;
            @endphp
            @if ($showInsightCard)
                <section class="ag-card rainfall-page__insight-card" aria-label="Rainfall insights">
                    <div class="rainfall-page__insight-head">
                        <span class="rainfall-page__insight-head-ic" aria-hidden="true">
                            <img src="{{ $rImg('rain') }}" alt="" width="28" height="28" decoding="async">
                        </span>
                        <div class="rainfall-page__insight-head-copy">
                            <h2 class="rainfall-page__insight-title">What this means</h2>
                            <p class="rainfall-page__insight-sub">Short takeaways from local records</p>
                        </div>
                    </div>

                    <div class="rainfall-page__insight-blocks">
                        {{-- 1. Trend --}}
                        <div class="rainfall-page__insight-block rainfall-page__insight-block--trend">
                            <div class="rainfall-page__insight-block-top">
                                <span class="rainfall-page__insight-block-ic" aria-hidden="true"><img src="{{ $rImg('trend') }}" alt="" width="20" height="20" decoding="async"></span>
                                <h3 class="rainfall-page__insight-block-title">Trend insight</h3>
                            </div>
                            @if ($insightTrendText !== '')
                                <p class="rainfall-page__insight-body">{{ \Illuminate\Support\Str::limit($insightTrendText, 260) }}</p>
                            @elseif (count($wetMonthsInsight) > 0)
                                @php $wetShow = array_slice($wetMonthsInsight, 0, 8); @endphp
                                <p class="rainfall-page__insight-body rainfall-page__insight-body--tight">
                                    <span class="rainfall-page__insight-lead">Heavier rainfall is more frequent in</span>
                                    @foreach ($wetShow as $m)
                                        <span class="rainfall-page__insight-tag">{{ $m }}</span>
                                    @endforeach
                                    @if (count($wetMonthsInsight) > count($wetShow))
                                        <span class="rainfall-page__insight-lead">+ more wet-leaning months in the data.</span>
                                    @else
                                        <span class="rainfall-page__insight-lead">compared with drier months in the records.</span>
                                    @endif
                                </p>
                            @else
                                <p class="rainfall-page__insight-body">
                                    Rain trend is <strong class="rainfall-page__insight-em">{{ $rainTrendWord }}</strong>. Check the chart to see monthly changes.
                                </p>
                            @endif
                        </div>

                        {{-- 2. Peak months --}}
                        <div class="rainfall-page__insight-block rainfall-page__insight-block--peak">
                            <div class="rainfall-page__insight-block-top">
                                <span class="rainfall-page__insight-block-ic" aria-hidden="true"><img src="{{ $rImg('rain') }}" alt="" width="20" height="20" decoding="async"></span>
                                <h3 class="rainfall-page__insight-block-title">Peak months</h3>
                            </div>
                            @if (count($peakMonthTags) >= 2)
                                <p class="rainfall-page__insight-body rainfall-page__insight-body--tight">
                                    <span class="rainfall-page__insight-lead">Highest average rainfall:</span>
                                    @foreach ($peakMonthTags as $m)
                                        <span class="rainfall-page__insight-tag rainfall-page__insight-tag--peak">{{ $m }}</span>
                                    @endforeach
                                </p>
                            @elseif (count($peakMonthTags) === 1)
                                <p class="rainfall-page__insight-body rainfall-page__insight-body--tight">
                                    <span class="rainfall-page__insight-tag rainfall-page__insight-tag--peak">{{ $peakMonthTags[0] }}</span>
                                    <span class="rainfall-page__insight-lead">usually leads the year in average rainfall.</span>
                                </p>
                            @elseif ($insightPeakText !== '')
                                <p class="rainfall-page__insight-body">{{ \Illuminate\Support\Str::limit($insightPeakText, 220) }}</p>
                            @else
                                <p class="rainfall-page__insight-body rainfall-page__insight-muted">Peak months will show here once enough monthly history is available.</p>
                            @endif
                        </div>

                        {{-- 3. What to do --}}
                        <div class="rainfall-page__insight-block rainfall-page__insight-block--action">
                            <div class="rainfall-page__insight-block-top">
                                <span class="rainfall-page__insight-block-ic" aria-hidden="true"><img src="{{ $rImg('sprout') }}" alt="" width="20" height="20" decoding="async"></span>
                                <h3 class="rainfall-page__insight-block-title">What to do</h3>
                            </div>
                            @if ($insightActionText !== '')
                                <p class="rainfall-page__insight-body">{{ \Illuminate\Support\Str::limit($insightActionText, 220) }}</p>
                            @else
                                <p class="rainfall-page__insight-body">
                                    <strong class="rainfall-page__insight-em">Strengthen drainage</strong> before wet months, <strong class="rainfall-page__insight-em">clear canals</strong>, and <strong class="rainfall-page__insight-em">watch low fields</strong> when rain is high.
                                </p>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            <article class="ag-card rainfall-page__insight-support rounded-3xl border border-slate-200 bg-slate-50/90 p-4" aria-label="Rainfall insight">
                <p class="rainfall-page__reco-insight-lbl">Rainfall Insight</p>
                <p class="rainfall-page__reco-insight-txt">{{ $rAiAdvisoryReady ? trim((string) ($rainReco['rainfall_insight'] ?? '')) : $rBlockedMsg }}</p>
            </article>

            <section class="ag-card rainfall-page__history-card" aria-label="Yearly rainfall history">
                <div class="rainfall-page__history-head">
                    <h2 class="farm-dash__title rainfall-page__history-title">
                        <img src="{{ $rImg('list') }}" alt="" width="18" height="18" decoding="async" class="rainfall-clay-ic--title">
                        Yearly totals
                    </h2>
                    <p class="rainfall-page__history-sub">Historical rainfall totals by year</p>
                </div>
                <div class="rainfall-page__history-list">
                    @forelse ($historyRows as $row)
                        <article class="rainfall-page__history-row">
                            <p class="rainfall-page__history-year">{{ $row['date'] }}</p>
                            <span class="rainfall-page__history-ic"><img src="{{ $rImg('calendar') }}" alt="" width="22" height="22" decoding="async"></span>
                            <div class="rainfall-page__history-meta">
                                <p class="rainfall-page__history-lbl">Total rainfall</p>
                                <p class="rainfall-page__history-amt">{{ $row['amount'] }}</p>
                            </div>
                            <p class="rainfall-page__history-badge">{{ $row['status'] }}</p>
                        </article>
                    @empty
                        <div class="rainfall-page__history-empty">
                            <img src="{{ $rImg('cloud_off') }}" alt="" width="44" height="44" decoding="async">
                            <p class="rainfall-page__history-empty-title">No yearly totals yet</p>
                            <p class="rainfall-page__history-empty-txt">Historical year rows will list here when data exists.</p>
                        </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
@endpush
