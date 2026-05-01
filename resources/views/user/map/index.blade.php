@php
    $u = Auth::user();
    $mapFarmName = trim((string) ($u->name ?? '')) !== '' ? trim((string) $u->name) . ' Farm' : 'My Farm';
    $mapHeaderLocation = trim((string) ($u->farm_location_display ?? ''));
    if ($mapHeaderLocation === '') {
        $mapHeaderLocation = trim(implode(', ', array_filter([
            $u->farm_barangay_name ?? null,
            $u->farm_municipality ?? null,
        ])));
    }
    if ($mapHeaderLocation === '') {
        $mapHeaderLocation = ($u->farm_municipality ?? 'Amulung') . ', Cagayan';
    }

    $mapAdvClayDataUri = static function (string $svg): string {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    };
    $mapAdvClay = [
        'brain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="bg" cx="30%" cy="30%" r="65%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="100%" stop-color="#A78BFA"/></radialGradient><filter id="dsb"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M18 14c-4 0-7 3-7 7 0 2 1 4 2 5-1 1-2 3-2 5 0 5 4 9 9 9h10c5 0 9-4 9-9 0-2-1-4-2-5 1-1 2-3 2-5 0-4-3-7-7-7-1-3-4-5-8-5s-7 2-8 5z" fill="url(#bg)" filter="url(#dsb)"/></svg>',
        'bulb' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="blb" cx="35%" cy="28%" r="65%"><stop offset="0%" stop-color="#FEF9C3"/><stop offset="100%" stop-color="#FACC15"/></radialGradient><filter id="dsbl"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="20" rx="12" ry="14" fill="url(#blb)" filter="url(#dsbl)"/><rect x="18" y="32" width="12" height="8" rx="2" fill="#94A3B8" filter="url(#dsbl)"/><ellipse cx="20" cy="16" rx="4" ry="3" fill="#fff" opacity=".4"/></svg>',
        'pin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="pg" cx="35%" cy="20%" r="70%"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#D97706"/></radialGradient><filter id="dsp"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><path d="M24 8c-6 0-10 4.5-10 10 0 8 10 22 10 22s10-14 10-22c0-5.5-4-10-10-10z" fill="url(#pg)" filter="url(#dsp)"/><circle cx="24" cy="18" r="4" fill="#fff" opacity=".5"/></svg>',
        'sprout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="sg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#86EFAC"/><stop offset="100%" stop-color="#22C55E"/></linearGradient><filter id="dss2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M24 38v-8" stroke="#A3E635" stroke-width="4" stroke-linecap="round" filter="url(#dss2)"/><path d="M24 30 Q14 22 18 12 Q24 18 24 30" fill="url(#sg)" filter="url(#dss2)"/><path d="M24 30 Q34 22 30 12 Q24 18 24 30" fill="url(#sg)" opacity=".9" filter="url(#dss2)"/></svg>',
        'eye' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="eg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E0E7FF"/><stop offset="100%" stop-color="#A5B4FC"/></linearGradient><filter id="dse"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="24" rx="18" ry="11" fill="url(#eg)" filter="url(#dse)"/><circle cx="24" cy="24" r="7" fill="#F8FAFC"/><circle cx="24" cy="24" r="4" fill="#6366F1"/></svg>',
        'alert' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsa"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 8 L40 38H8L24 8z" fill="url(#ag)" filter="url(#dsa)"/><path d="M24 16v12M24 32v2" stroke="#92400E" stroke-width="2.5" stroke-linecap="round"/></svg>',
    ];
    $mapAdvClayImg = static function (string $key) use ($mapAdvClay, $mapAdvClayDataUri): string {
        $svg = $mapAdvClay[$key] ?? $mapAdvClay['sprout'];

        return $mapAdvClayDataUri($svg);
    };
    $mapAdvClayUrls = [
        'brain' => $mapAdvClayImg('brain'),
        'bulb' => $mapAdvClayImg('bulb'),
        'pin' => $mapAdvClayImg('pin'),
        'sprout' => $mapAdvClayImg('sprout'),
        'eye' => $mapAdvClayImg('eye'),
        'alert' => $mapAdvClayImg('alert'),
    ];
@endphp

@extends('layouts.user')

@section('title', 'Map – AGRIGUARD')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('body-class', 'dashboard-page farm-map-page min-h-screen bg-[#EEF1F6]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell farm-map-dashboard-shell py-4 sm:py-6 pb-20">
        <div
            id="farm-map-page"
            class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5"
            data-farm-map-auto-height="false"
            data-csrf-token="{{ csrf_token() }}"
            data-context-url="{{ route('map.farm-context') }}"
            data-save-url="{{ route('map.save-gps') }}"
            data-geofence-url="{{ asset('amulung.json') }}"
            data-initial-has-gps="{{ $initialHasDeviceGps ? '1' : '0' }}"
            data-adv-clay-b64="{{ base64_encode(json_encode($mapAdvClayUrls, JSON_UNESCAPED_SLASHES)) }}"
        >
            <header class="dashboard-hero farm-map-page-hero ag-card" aria-labelledby="farm-map-page-hero-heading">
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
                            <span class="dashboard-hero__greet-badge farm-map-page-hero__badge" aria-hidden="true">
                                <span class="dashboard-hero__greet-badge-glow"></span>
                                <i data-lucide="map" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="farm-map-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">Map</span>
                                    <span class="dashboard-hero__title-emoji" aria-hidden="true">🗺️</span>
                                </h1>
                                <p class="dashboard-hero__subtitle">
                                    <span class="dashboard-hero__subtitle-ic" aria-hidden="true">
                                        <i data-lucide="navigation" class="dashboard-hero__lucide dashboard-hero__lucide--xs"></i>
                                    </span>
                                    <span id="farm-map-hero-farm">{{ $mapFarmName }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill farm-map-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="map-pin" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $mapHeaderLocation }}</span>
                            </span>
                            <span class="dashboard-hero__pill farm-map-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card farm-map-page-hero__map-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow"></span>
                            <span class="dashboard-hero__weather-ring">
                                <i data-lucide="compass" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="route" class="dashboard-hero__lucide dashboard-hero__lucide--sm"></i>
                                    Location
                                </span>
                                <span class="dashboard-hero__weather-temp">GPS</span>
                                <span class="dashboard-hero__weather-desc">Farm map active</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            {{-- Location status + last updated + map actions (single card) --}}
            <section class="ag-card farm-map-control-strip" aria-label="Map location and controls">
                <div class="farm-map-control-strip__top">
                    <div class="farm-map-control-strip__status" aria-live="polite">
                        <div
                            id="farm-map-status-gps-wrap"
                            class="farm-map-status-chip farm-map-status-chip--muted"
                        >
                            <span class="farm-map-status-chip__ic" aria-hidden="true">📍</span>
                            <span class="farm-map-status-chip__text">
                                <span class="farm-map-status-chip__label">GPS</span><span class="farm-map-status-chip__colon">:</span>
                                <span id="farm-map-status-gps" class="farm-map-status-chip__value">Not connected</span>
                            </span>
                        </div>
                        <div class="farm-map-status-chip farm-map-status-chip--muted">
                            <span class="farm-map-status-chip__ic" aria-hidden="true">🌧</span>
                            <span class="farm-map-status-chip__text">
                                <span class="farm-map-status-chip__label">Rain chance</span><span class="farm-map-status-chip__colon">:</span>
                                <span id="farm-map-status-rain" class="farm-map-status-chip__value">—</span>
                            </span>
                        </div>
                    </div>
                    <p id="farm-map-gps-last" class="farm-map-control-strip__updated">Last updated —</p>
                </div>
                <div class="farm-map-control-strip__actions">
                    <button type="button" id="farm-map-btn-use-gps" class="farm-map-control-strip__btn farm-map-control-strip__btn--primary">
                        Use GPS
                    </button>
                    <button
                        type="button"
                        id="farm-map-btn-refresh-gps"
                        class="farm-map-control-strip__btn farm-map-control-strip__btn--secondary"
                        title="Reload map data and advisory"
                    >
                        Refresh
                    </button>
                    <button
                        type="button"
                        id="farm-map-gps-recenter"
                        class="farm-map-control-strip__btn farm-map-control-strip__btn--secondary"
                        title="Recenter map on your farm"
                    >
                        Recenter
                    </button>
                </div>
                <div id="farm-map-gps-error" class="farm-map-gps-error hidden" role="alert"></div>
                <button type="button" id="farm-map-gps-retry" class="farm-map-gps-retry hidden">Retry</button>
                <div id="farm-map-boundary-slot" class="farm-map-boundary-slot hidden" aria-hidden="true"></div>
            </section>

            {{-- AI Smart Advisory (Rainfall-trends style, map-specific implementation) --}}
            <div class="farm-map-map-advisory-row">
                <div>
                    <div class="ag-advisory-toggle-row">
                        <button
                            type="button"
                            class="ag-advisory-toggle-btn"
                            data-ai-advisory-toggle
                            data-target="advisory-map-section"
                            data-storage-key="advisory_visibility_map"
                            aria-pressed="true"
                        >
                            Hide AI Smart Advisory
                        </button>
                    </div>
                    <aside id="advisory-map-section" class="farm-map-advisory-column space-y-3 sm:space-y-4" aria-label="Map smart advisory" data-ai-smart-advisory-section>
                    <article
                        id="farm-map-smart-advisory"
                        class="ag-card dash-smart weather-page__smart farm-map-page__smart rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5"
                        aria-label="AI smart advisory"
                    >
                        <div class="dash-smart__debug">
                            <p class="text-xs font-semibold text-slate-700">
                                <span id="farm-map-advisory-status-line">AI Smart Advisory: Syncing</span>
                            </p>
                        </div>
                        <div class="dash-smart__head">
                            <div class="dash-smart__title-wrap">
                                <span class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-extrabold uppercase tracking-[0.1em] text-slate-700">
                                    <i data-lucide="sparkles" class="h-3.5 w-3.5 text-emerald-600"></i>
                                    Smart action
                                </span>
                            </div>
                        </div>
                        <div class="dash-smart__body">
                            <p id="farm-map-advisory-main-action" class="dash-smart__action">Loading advisory…</p>
                        </div>
                    </article>

                    <section
                        id="farm-map-field-day-plan"
                        class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5 shadow-sm farm-map-page__timeline"
                        aria-label="Field day plan"
                    >
                        <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800">
                            <i data-lucide="calendar-check-2" class="h-4 w-4 text-amber-600"></i>
                            Field Day Plan
                        </h2>
                        <div class="farm-map-page__timeline-list mt-3">
                            <article class="farm-map-page__timeline-item farm-map-page__timeline-item--morning">
                                <span class="farm-map-page__timeline-dot">☀️</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Early day</p>
                                    <p id="farm-map-plan-early" class="text-sm text-slate-600">Loading…</p>
                                </div>
                            </article>
                            <article class="farm-map-page__timeline-item farm-map-page__timeline-item--midday">
                                <span class="farm-map-page__timeline-dot">⛅</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Midday</p>
                                    <p id="farm-map-plan-midday" class="text-sm text-slate-600">Loading…</p>
                                </div>
                            </article>
                            <article class="farm-map-page__timeline-item farm-map-page__timeline-item--late">
                                <span class="farm-map-page__timeline-dot">🌙</span>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">Late day</p>
                                    <p id="farm-map-plan-late" class="text-sm text-slate-600">Loading…</p>
                                </div>
                            </article>
                        </div>
                    </section>

                    <section class="grid gap-3 sm:grid-cols-2" aria-label="Water, drainage, and avoid">
                        <div class="ag-card rounded-3xl border border-cyan-100 bg-cyan-50/70 p-4">
                            <div class="dash-split__card dash-split__card--water farm-map-page__split-water">
                                <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700">
                                    <i data-lucide="droplets" class="h-3.5 w-3.5 text-cyan-600"></i>
                                    Water & Drainage
                                </div>
                                <p id="farm-map-plan-water" class="dash-split__body">Loading…</p>
                            </div>
                        </div>
                        <div class="ag-card rounded-3xl border border-rose-100 bg-rose-50/70 p-4">
                            <div class="dash-split__card dash-split__card--avoid farm-map-page__split-avoid">
                                <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700">
                                    <i data-lucide="triangle-alert" class="h-3.5 w-3.5 text-rose-600"></i>
                                    Avoid Today
                                </div>
                                <p id="farm-map-plan-avoid" class="dash-split__body">Loading…</p>
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
                    </aside>
                </div>

                <div class="farm-map-map-column">
                    <section class="ag-card farm-map-stack farm-map-stack--focus" aria-label="Farm map">
                        <div class="farm-map-map-head">
                            <h2 class="farm-map-map-head__title">Farm Map</h2>
                        </div>
                        <div class="farm-map-stack__frame">
                            <div class="farm-map-stack__map-inner farm-map-stack__map-inner--tall farm-map-main-canvas">
                                <div class="farm-map-map-overlays-top" aria-label="Geofence status and flood risk legend">
                                    <div id="farm-map-geofence-badge" class="farm-map-geofence-badge farm-map-geofence-badge--outside" aria-live="polite">Outside Geofence</div>
                                    <div class="farm-map-flood-legend" role="note">
                                        <p class="farm-map-flood-legend__title">Flood risk legend</p>
                                        <ul class="farm-map-flood-legend__list">
                                            <li>
                                                <span class="farm-map-flood-legend__swatch farm-map-flood-legend__swatch--high" aria-hidden="true"></span>
                                                <span>Red — high risk</span>
                                            </li>
                                            <li>
                                                <span class="farm-map-flood-legend__swatch farm-map-flood-legend__swatch--moderate" aria-hidden="true"></span>
                                                <span>Yellow — moderate risk</span>
                                            </li>
                                            <li>
                                                <span class="farm-map-flood-legend__swatch farm-map-flood-legend__swatch--low" aria-hidden="true"></span>
                                                <span>Green — low risk</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <div id="farm-map-container" class="farm-map-leaflet w-full h-full min-h-0"></div>

                                <div id="farm-map-empty-overlay" class="farm-map-empty-overlay {{ $initialHasDeviceGps ? 'hidden' : '' }}" aria-hidden="{{ $initialHasDeviceGps ? 'true' : 'false' }}">
                                    <div class="farm-map-empty-overlay__card">
                                        <p class="farm-map-empty-overlay__title">No GPS pin yet</p>
                                        <p class="farm-map-empty-overlay__text">Tap <strong>Use GPS</strong> to place your farm.</p>
                                    </div>
                                </div>

                                <div id="farm-map-weather-float" class="farm-map-weather-float hidden" aria-hidden="true">
                                    <div class="farm-map-weather-float-inner">
                                        <span id="farm-map-weather-float-text">—</span>
                                    </div>
                                </div>

                                <div class="farm-map-float-controls" aria-label="Map controls">
                                    <div class="farm-map-float-controls__card">
                                        <button type="button" id="farm-map-zoom-in" class="farm-map-zoom-btn" title="Zoom in" aria-label="Zoom in">+</button>
                                        <button type="button" id="farm-map-zoom-out" class="farm-map-zoom-btn" title="Zoom out" aria-label="Zoom out">−</button>
                                        <button type="button" id="farm-map-recenter" class="farm-map-zoom-btn farm-map-zoom-btn--pin" title="Recenter on your farm" aria-label="Recenter on your farm">⌖</button>
                                        <button type="button" id="farm-map-fullscreen" class="farm-map-zoom-btn farm-map-zoom-btn--wide" title="Fullscreen map" aria-label="Fullscreen map">⛶</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p id="farm-map-layer-legend" class="farm-map-layer-legend" aria-live="polite"></p>
                    </section>
                </div>
            </div>

            {{-- Section 3: Info cards below map — compact snapshot UI --}}
            <div class="farm-map-below space-y-3 sm:space-y-4">
                <section
                    id="farm-map-today-one"
                    class="ag-card farm-map-today-strip overflow-hidden rounded-2xl border border-teal-200/70 bg-gradient-to-br from-teal-50/95 via-white to-emerald-50/60 p-3 shadow-md shadow-teal-500/5 ring-1 ring-teal-100/90 sm:p-3.5"
                    aria-labelledby="farm-map-today-one-label"
                >
                    <div class="farm-map-today-strip__sheen" aria-hidden="true"></div>
                    <div class="farm-map-today-strip__inner relative z-[1]">
                        <span class="farm-map-today-strip__glyph" aria-hidden="true">
                            <i data-lucide="sun-medium" class="farm-map-today-strip__lucide"></i>
                        </span>
                        <div class="farm-map-today-strip__copy">
                            <p id="farm-map-today-one-label" class="farm-map-today-strip__eyebrow">Today</p>
                            <p id="farm-map-today-summary" class="farm-map-today-strip__text">Loading…</p>
                        </div>
                    </div>
                </section>

                <section
                    class="ag-card farm-map-info-panel farm-map-snapshot-shell overflow-hidden rounded-2xl border border-sky-200/65 bg-gradient-to-br from-sky-50/90 via-white to-indigo-50/45 p-3 shadow-md shadow-sky-500/10 ring-1 ring-sky-100/80 sm:p-3.5"
                    aria-labelledby="farm-map-snapshot-heading"
                >
                    <div class="farm-map-snapshot-shell__sheen" aria-hidden="true"></div>
                    <header class="farm-map-snapshot-shell__head relative z-[1]">
                        <span class="farm-map-snapshot-shell__glyph" aria-hidden="true">
                            <i data-lucide="layout-dashboard" class="farm-map-snapshot-shell__lucide"></i>
                        </span>
                        <h2 id="farm-map-snapshot-heading" class="farm-map-snapshot-shell__title">Field snapshot</h2>
                    </header>
                    <div id="farm-map-summary-grid" class="farm-map-snapshot-grid relative z-[1]"></div>
                </section>

                <div id="farm-map-no-gps-hint" class="farm-map-no-gps-inline text-center text-xs text-slate-500 {{ $initialHasDeviceGps ? 'hidden' : '' }}">Save GPS to load map details.</div>
            </div>
        </div>
    </section>
@endsection
