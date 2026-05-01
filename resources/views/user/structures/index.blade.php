@extends('layouts.user')

@section('title', 'Smart Site & Structure Advisor – AGRIGUARD')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('body-class', 'dashboard-page structures-page min-h-screen bg-[#EEF1F6]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell py-5 pb-20">
        <div
            id="structures-page"
            class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4"
            data-analysis-url="{{ route('structures.analysis') }}"
            data-location-url="{{ route('structures.location') }}"
            data-geofence-url="{{ asset('amulung.json') }}"
            data-csrf-token="{{ csrf_token() }}"
        >
            <header class="dashboard-hero structures-page-hero ag-card" aria-labelledby="structures-page-hero-heading">
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
                            <span class="dashboard-hero__greet-badge structures-page-hero__badge" aria-hidden="true">
                                <span class="dashboard-hero__greet-badge-glow"></span>
                                <i data-lucide="warehouse" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="structures-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">Structures</span>
                                    <span class="dashboard-hero__title-emoji" aria-hidden="true">🏗️</span>
                                </h1>
                                <p class="dashboard-hero__subtitle">
                                    <span class="dashboard-hero__subtitle-ic" aria-hidden="true">
                                        <i data-lucide="sparkles" class="dashboard-hero__lucide dashboard-hero__lucide--xs"></i>
                                    </span>
                                    <span>Smart Site &amp; Structure Advisor</span>
                                </p>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill structures-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="map-pin" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">Barangay auto-detection enabled</span>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card structures-page-hero__map-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow"></span>
                            <span class="dashboard-hero__weather-ring">
                                <i data-lucide="radar" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="mouse-pointer-click" class="dashboard-hero__lucide dashboard-hero__lucide--sm"></i>
                                    Analysis Mode
                                </span>
                                <span class="dashboard-hero__weather-temp">Map Click</span>
                                <span class="dashboard-hero__weather-desc">Real-time site assessment</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            <section class="ag-card structures-map-card" aria-label="Structures map">
                <div id="structures-map" class="structures-map"></div>
            </section>

            <section class="structures-grid">
                <article class="ag-card structures-panel">
                    <h2 class="structures-panel__title">Detected Location</h2>
                    <div id="structures-location" class="structures-panel__body">Click the map to detect location details.</div>
                </article>

                <article class="ag-card structures-panel">
                    <h2 class="structures-panel__title">Site Conditions</h2>
                    <div id="structures-conditions" class="structures-panel__body">
                        <p class="structures-conditions-placeholder">Click the map to detect location and flood risk.</p>
                    </div>
                    <div class="structures-actions">
                        <button type="button" id="structures-analyze-btn" class="structures-analyze-btn" disabled>
                            <span id="structures-analyze-spinner" class="structures-analyze-spinner hidden" aria-hidden="true"></span>
                            <span id="structures-analyze-text">Analyze Site</span>
                        </button>
                    </div>
                </article>
            </section>

            <section class="ag-card structures-results" aria-live="polite">
                <div id="structures-analysis-status" class="structures-results__status">Adjust site conditions and click Analyze Site to generate recommendations.</div>
                <div id="structures-classification" class="structures-results__classification"></div>
                <div id="structures-summary" class="structures-results__summary"></div>
                <div id="structures-recommendations" class="structures-results__cards"></div>
            </section>
        </div>
    </section>
@endsection
