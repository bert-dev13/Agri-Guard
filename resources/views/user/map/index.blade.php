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
                        <div
                            id="farm-map-status-flood-wrap"
                            class="farm-map-status-chip farm-map-status-chip--muted"
                        >
                            <span class="farm-map-status-chip__ic" aria-hidden="true">⚠</span>
                            <span class="farm-map-status-chip__text">
                                <span class="farm-map-status-chip__label">Flood</span><span class="farm-map-status-chip__colon">:</span>
                                <span id="farm-map-status-flood" class="farm-map-status-chip__value">—</span>
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

            {{-- Smart Advisory: same cp-smart / dash-smart layout as Crop Progress (styles in crop-progress.css) --}}
            <div class="farm-map-map-advisory-row">
                <aside class="farm-map-advisory-column" aria-label="Map smart advisory">
                    <section
                        id="farm-map-smart-advisory"
                        class="ag-card dash-smart cp-smart-panel cp-smart-hero"
                        aria-label="Map smart advisory"
                    >
                        <div class="cp-smart-hero__status-row dash-smart__debug fm-map-cp-smart__status-row">
                            <div class="fm-map-cp-smart__status-text">
                                <p class="text-xs font-semibold text-slate-700">
                                    <span id="farm-map-advisory-status-line">Loading…</span>
                                </p>
                            </div>
                            <button
                                type="button"
                                id="farm-map-advisory-refresh"
                                class="fm-map-cp-smart__refresh"
                                title="Refresh advisory"
                                aria-label="Refresh advisory"
                            >
                                <i data-lucide="refresh-cw" class="fm-map-cp-smart__refresh-ic" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div id="farm-map-advisory-body">
                            <div id="farm-map-advisory-inner">
                                <p class="text-sm text-slate-500">Loading advisory…</p>
                            </div>
                        </div>
                    </section>
                </aside>

                <div class="farm-map-map-column">
                    <section class="ag-card farm-map-stack farm-map-stack--focus" aria-label="Farm map">
                        <div class="farm-map-map-head">
                            <h2 class="farm-map-map-head__title">Farm Map</h2>
                        </div>
                        <div class="farm-map-stack__frame">
                            <div class="farm-map-stack__map-inner farm-map-stack__map-inner--tall farm-map-main-canvas">
                                <div id="farm-map-layer-toggles" class="farm-map-layer-toggles farm-map-layer-toggles--float" role="toolbar" aria-label="Map layers"></div>

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
                        <p class="farm-map-stack__footnote">Forecast-based map guide</p>
                    </section>
                </div>
            </div>

            {{-- Section 3: Info cards below map --}}
            <div class="farm-map-below space-y-2">
                <section id="farm-map-today-one" class="ag-card farm-map-today-one" aria-labelledby="farm-map-today-one-label">
                    <h2 id="farm-map-today-one-label" class="sr-only">Today</h2>
                    <p id="farm-map-today-summary" class="farm-map-today-one__text">Loading…</p>
                </section>

                <section class="ag-card farm-map-info-panel" aria-labelledby="farm-map-snapshot-heading">
                    <h2 id="farm-map-snapshot-heading" class="farm-map-info-panel__heading">Field snapshot</h2>
                    <div id="farm-map-summary-grid" class="farm-map-snapshot-grid"></div>
                </section>

                <div id="farm-map-no-gps-hint" class="farm-map-no-gps-inline text-center text-xs text-slate-500 {{ $initialHasDeviceGps ? 'hidden' : '' }}">Save GPS to load map details.</div>
            </div>
        </div>
    </section>
@endsection
