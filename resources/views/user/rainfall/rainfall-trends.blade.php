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

    $snapshotTrendValClass = match ($rainTrendWord) {
        'More rain expected' => 'text-emerald-700',
        'Less rain expected' => 'text-violet-700',
        default => 'text-slate-900',
    };

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
    <section class="dashboard-shell dashboard-shell--dashboard-home py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            <header class="dashboard-hero weather-page-hero rainfall-page-hero ag-card" aria-labelledby="rainfall-page-hero-heading">
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
                            <span class="dashboard-hero__greet-badge weather-page-hero__badge" aria-hidden="true">
                                <span class="dashboard-hero__greet-badge-glow"></span>
                                <i data-lucide="cloud-rain" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="rainfall-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">Rainfall Trends</span>
                                </h1>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill weather-page-hero__pill rainfall-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
                            </span>
                            <span class="dashboard-hero__pill weather-page-hero__pill rainfall-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-range" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $cropLabel }} · {{ $dataPeriod }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card weather-page-hero__status-card rainfall-page-hero__rain-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow"></span>
                            <span class="dashboard-hero__weather-ring">
                                <img src="{{ $rImg('rain') }}" alt="" class="rainfall-clay-ic rainfall-clay-ic--hero" width="36" height="36" decoding="async">
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="cloud-rain" class="dashboard-hero__lucide dashboard-hero__lucide--sm" aria-hidden="true"></i>
                                    Rainfall today
                                </span>
                                <span class="dashboard-hero__weather-temp">{{ is_numeric($todayRainfall) ? number_format((float) $todayRainfall, 1) : '—' }}<span class="dashboard-hero__weather-unit">mm</span></span>
                                <span class="dashboard-hero__weather-desc">{{ $rainStatusLabel }}</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            {{-- Weather snapshot footprint; rainfall-page__snap adds polish via rainfall-trends.css --}}
            <section class="ag-card weather-snapshot weather-page__snap-layout rainfall-page__snap overflow-hidden rounded-3xl border border-teal-200/70 bg-gradient-to-br from-teal-50/95 via-sky-50/88 to-indigo-50/50 p-3.5 shadow-sm sm:p-4" aria-label="Rainfall snapshot">
                <div class="relative z-[1] flex items-start gap-3">
                    <div class="rainfall-page__snap-icon inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-white to-teal-50 ring-1 ring-teal-100 shadow-sm sm:h-11 sm:w-11" aria-hidden="true">
                        <img src="{{ $rImg('rain') }}" alt="" class="rainfall-clay-ic h-7 w-7 object-contain sm:h-8 sm:w-8" width="32" height="32" decoding="async">
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <span class="inline-flex items-center gap-1 rounded-full border border-teal-200/80 bg-white/80 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-[0.14em] text-teal-900/90 shadow-[0_1px_8px_rgb(45_212_191_/_0.18)] backdrop-blur-[2px]">
                            <i data-lucide="cloud-rain" class="h-3 w-3 shrink-0 text-teal-600"></i>
                            Rainfall snapshot
                        </span>
                        <div class="mt-1.5 flex flex-wrap items-end gap-x-2.5 gap-y-1">
                            <p class="rainfall-page__snap-hero-val text-2xl font-extrabold leading-none tracking-tight text-slate-900 drop-shadow-[0_1px_0_rgb(255_255_255_/_0.65)] sm:text-3xl">{{ is_numeric($todayRainfall) ? number_format((float) $todayRainfall, 1) . ' mm' : '—' }}</p>
                            <span class="inline-flex max-w-[14rem] items-center rounded-full border border-teal-200/70 bg-white/75 px-2 py-px text-[11px] font-semibold leading-tight text-teal-900/90 shadow-sm ring-1 ring-white/60 backdrop-blur-[2px] sm:text-xs">{{ $rainStatusLabel }}</span>
                        </div>
                    </div>
                </div>
                <div class="rainfall-page__snap-strip relative z-[1] mt-2.5 flex divide-x divide-slate-200/80 overflow-hidden rounded-2xl border border-slate-200/80 bg-white/55 shadow-[inset_0_1px_0_rgb(255_255_255_/_0.85)] ring-1 ring-white/60 backdrop-blur-[2px]" role="list">
                    <article class="rainfall-page__snap-cell rainfall-page__snap-cell--sky min-w-0 flex-1 px-1 py-1.5 text-center sm:px-1.5" role="listitem">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Past 7 days</p>
                        <p class="mt-0.5 text-xs font-bold tabular-nums text-slate-900 sm:text-sm">{{ is_numeric($weekRainfall) ? number_format((float) $weekRainfall, 1) . ' mm' : '—' }}</p>
                    </article>
                    <article class="rainfall-page__snap-cell rainfall-page__snap-cell--mint min-w-0 flex-1 px-1 py-1.5 text-center sm:px-1.5" role="listitem">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Average monthly rain</p>
                        <p class="mt-0.5 text-xs font-bold tabular-nums text-slate-900 sm:text-sm">{{ is_numeric($monthRainfall) ? number_format((float) $monthRainfall, 1) . ' mm' : '—' }}</p>
                    </article>
                    <article class="rainfall-page__snap-cell rainfall-page__snap-cell--violet min-w-0 flex-1 px-1 py-1.5 text-center sm:px-1.5" role="listitem">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-600">Rain trend</p>
                        <p class="rainfall-page__snap-trend-val mt-0.5 text-xs font-bold tabular-nums sm:text-sm {{ $snapshotTrendValClass }}">{{ $rainTrendWord }}</p>
                    </article>
                </div>
            </section>

            {{-- Matches weather-details AI smart advisory (no extra badges) --}}
            <div class="ag-advisory-toggle-row">
                <button
                    type="button"
                    class="ag-advisory-toggle-btn"
                    data-ai-advisory-toggle
                    data-target="advisory-rainfall-section"
                    data-storage-key="advisory_visibility_rainfall"
                    aria-pressed="true"
                >
                    Hide AI Smart Advisory
                </button>
            </div>
            <section id="advisory-rainfall-section" data-ai-smart-advisory-section>
            <article class="ag-card dash-smart weather-page__smart rainfall-page__smart rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5" aria-label="AI smart advisory">
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

                <div class="dash-smart__head">
                    <div class="dash-smart__title-wrap">
                        <span class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-extrabold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900" aria-hidden="true">
                            <i data-lucide="sparkles" class="h-3.5 w-3.5 text-emerald-600"></i>
                            Smart action
                        </span>
                    </div>
                </div>

                <div class="dash-smart__body">
                    <p class="dash-smart__action">{{ $rAiAdvisoryReady ? trim((string) ($rainReco['main_rainfall_advice'] ?? '')) : $rBlockedMsg }}</p>
                </div>
            </article>

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5 shadow-sm rainfall-page__timeline" aria-label="Field day plan">
                <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                    <i data-lucide="calendar-check-2" class="h-4 w-4 text-amber-600"></i>
                    Field Day Plan
                </h2>
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
                        <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <img src="{{ $rImg('droplet') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Water & Drainage
                        </div>
                        <p class="dash-split__body">{{ $rAiAdvisoryReady ? trim((string) ($rainReco['drainage_irrigation_advice'] ?? '')) : $rBlockedMsg }}</p>
                    </div>
                </div>
                <div class="ag-card rounded-3xl border border-rose-100 bg-rose-50/70 p-4">
                    <div class="dash-split__card dash-split__card--avoid rainfall-page__split-avoid">
                        <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <img src="{{ $rImg('alert') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Avoid Today
                        </div>
                        <p class="dash-split__body">{{ $rAiAdvisoryReady ? trim((string) ($rainReco['what_to_avoid_today'] ?? '')) : $rBlockedMsg }}</p>
                    </div>
                </div>
            </section>
            <div class="flex items-start gap-2.5 rounded-2xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-indigo-600" aria-hidden="true">
                    <i data-lucide="info" class="h-3.5 w-3.5"></i>
                </span>
                <p class="pt-0.5 text-xs leading-relaxed text-slate-600">
                    AI-generated recommendations only. Based on system data including weather, rainfall, crop, and field conditions. For farm decision support.
                </p>
            </div>
            </section>

            <section class="ag-card rainfall-page__chart-card" aria-label="Rainfall chart">
                <div class="rainfall-page__chart-head">
                    <div class="min-w-0 flex-1">
                        <h2 class="farm-dash__title weather-page__section-kicker inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <img src="{{ $rImg('chart') }}" alt="" class="rainfall-clay-ic rainfall-clay-ic--title h-[18px] w-[18px] object-contain" width="18" height="18" decoding="async">
                            Rainfall chart
                        </h2>
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
            </section>

            <section class="ag-card rainfall-page__history-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5 shadow-sm" aria-label="Yearly rainfall history">
                <div class="rainfall-page__history-head">
                    <h2 class="farm-dash__title weather-page__section-kicker rainfall-page__history-title inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                        <img src="{{ $rImg('list') }}" alt="" width="18" height="18" decoding="async" class="rainfall-clay-ic--title">
                        Yearly totals
                    </h2>
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
