@php
    $user = Auth::user();
    $weather = $advisoryData['weather'] ?? null;
    $forecast = $advisoryData['forecast'] ?? [];
    $locationDisplay = $advisoryData['location_display'] ?? (($user->farm_municipality ?? 'Amulung') . ', Cagayan');

    $weatherConditionId = $weather['condition']['id'] ?? 800;
    $weatherEmoji = \App\Http\Controllers\WeatherDetailsController::simpleWeatherEmoji((int) $weatherConditionId);
    $weatherLabel = \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel((int) $weatherConditionId);
    $weatherLucide = \App\Http\Controllers\WeatherDetailsController::simpleWeatherIcon((int) $weatherConditionId);

    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $safeUserName = trim((string) ($user->name ?? 'Farmer'));
    $farmName = $safeUserName !== '' ? $safeUserName . ' Farm' : 'My Farm';
    $farmType = trim((string) ($user->crop_type ?? 'Not set'));
    $cropTimelineSvc = app(\App\Services\CropTimelineService::class);
    $farmingStage = $cropTimelineSvc->inferExpectedStageFromPlanting(
        $user,
        $cropTimelineSvc->stageDurationsForCrop((string) ($user->crop_type ?? ''))
    )['label'];
    $rainChance = $weather['today_rain_probability'] ?? ($advisoryData['today_rain_probability'] ?? null);
    $conditionLabel = $weatherLabel ?: 'No data';
    $humidity = $weather['humidity'] ?? null;
    $windSpeed = $weather['wind_speed'] ?? null;
    $recommendationRisk = strtolower((string) ($recommendation['risk'] ?? 'moderate'));
    $dashboardReco = is_array($recommendation ?? null) ? $recommendation : [];
    $aiStatus = strtolower(trim((string) ($dashboardReco['ai_status'] ?? '')));
    $aiAdvisoryReady = $aiStatus === 'success';
    $aiUnavailableMsg = 'AI advisory temporarily unavailable.';
    $aiMissingProfileMsg = 'Please update your crop type and farming stage in Farm Settings to receive AI advisory from Together AI.';
    $sprayingOk = is_numeric($rainChance) && (float) $rainChance < 35 && is_numeric($windSpeed) && (float) $windSpeed < 20;
    $sprayingLabel = $sprayingOk ? 'Safe' : ((is_numeric($rainChance) && (float) $rainChance >= 60) || (is_numeric($windSpeed) && (float) $windSpeed >= 30) ? 'Risk' : 'Caution');
    $irrigationNeedLabel = is_numeric($rainChance) ? (((float) $rainChance < 40) ? 'Caution' : 'Safe') : 'Caution';
    $todayPlan = is_array($dashboardReco['today_plan'] ?? null) ? $dashboardReco['today_plan'] : [];
    $toText = static function ($value, string $fallback = '—'): string {
        if (is_string($value)) {
            $v = trim($value);
            return $v !== '' ? $v : $fallback;
        }
        if (is_numeric($value) || is_bool($value)) {
            return (string) $value;
        }
        return $fallback;
    };

    $advisoryBlockedMessage = $aiStatus === 'missing_context' ? $aiMissingProfileMsg : $aiUnavailableMsg;
    $dashboardSummary = $aiAdvisoryReady
        ? $toText($dashboardReco['main_recommendation'] ?? $dashboardReco['action'] ?? null, '')
        : $advisoryBlockedMessage;
    if ($aiAdvisoryReady && $dashboardSummary === '') {
        $dashboardSummary = $aiUnavailableMsg;
    }
    $riskLabel = $aiAdvisoryReady ? ucfirst($toText($dashboardReco['risk'] ?? '', '')) : '—';
    $confidenceLabel = $aiAdvisoryReady ? $toText($dashboardReco['ai_confidence'] ?? $dashboardReco['confidence'] ?? null, '—') : '—';
    $planMorning = $aiAdvisoryReady ? $toText($todayPlan['morning'] ?? null, '') : $advisoryBlockedMessage;
    $planAfternoon = $aiAdvisoryReady ? $toText($todayPlan['afternoon'] ?? null, '') : $advisoryBlockedMessage;
    $planEvening = $aiAdvisoryReady ? $toText($todayPlan['evening'] ?? null, '') : $advisoryBlockedMessage;
    $avoidText = $aiAdvisoryReady ? $toText($dashboardReco['avoid'] ?? null, '') : $advisoryBlockedMessage;
    $waterText = $aiAdvisoryReady ? $toText($dashboardReco['water_strategy'] ?? $dashboardReco['water'] ?? null, '') : $advisoryBlockedMessage;
    $weatherAriaTemp = $weather && isset($weather['temp']) ? (string) round($weather['temp']) . ' degrees Celsius' : 'temperature unavailable';
    $weatherAriaLabel = 'Weather: ' . ($weatherLabel ?: 'conditions') . ', ' . $weatherAriaTemp . '. Tap for full weather details';
@endphp

@extends('layouts.user')

@section('title', 'Dashboard – AGRIGUARD')

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('body-class', 'dashboard-page min-h-screen bg-[#EEF1F6]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell dashboard-shell--dashboard-home py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            <header class="dashboard-hero dashboard-home-page-hero ag-card" aria-labelledby="dashboard-hero-heading">
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
                            <span class="dashboard-hero__greet-badge dashboard-home-page-hero__badge" aria-hidden="true">
                                <span class="dashboard-hero__greet-badge-glow"></span>
                                <i data-lucide="layout-dashboard" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="dashboard-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">{{ $greeting }}, {{ $safeUserName ?: 'Farmer' }}</span>
                                    <span class="dashboard-hero__title-emoji" aria-hidden="true">{{ $hour < 18 ? '👋' : '🌙' }}</span>
                                </h1>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill dashboard-home-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="map-pin" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $locationDisplay }}</span>
                            </span>
                            <span class="dashboard-hero__pill dashboard-home-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <a
                            href="{{ route('weather-details') }}"
                            class="dashboard-hero__weather-card dashboard-home-page-hero__weather-card"
                            aria-label="{{ $weatherAriaLabel }}"
                        >
                            <span class="dashboard-hero__weather-glow"></span>
                            <span class="dashboard-hero__weather-ring">
                                <i data-lucide="{{ $weatherLucide }}" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="cloud-sun" class="dashboard-hero__lucide dashboard-hero__lucide--sm"></i>
                                    {{ $weatherLabel ?: 'Clear' }}
                                </span>
                                @if ($weather && isset($weather['temp']))
                                    <span class="dashboard-hero__weather-temp">{{ round($weather['temp']) }}°C</span>
                                @else
                                    <span class="dashboard-hero__weather-temp dashboard-hero__weather-temp--muted">—</span>
                                @endif
                                <span class="dashboard-hero__weather-desc">Tap for full weather details</span>
                            </span>
                        </a>
                    </div>
                </div>
            </header>

            <section class="ag-card weather-snapshot overflow-hidden rounded-3xl border border-sky-200 bg-sky-50/85 p-3.5 shadow-sm sm:p-4" aria-label="TODAYS WEATHER">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-700 transition-all duration-300 hover:tracking-[0.14em] hover:text-slate-900">
                            <i data-lucide="cloud-sun" class="h-3.5 w-3.5 text-sky-600"></i>
                            TODAYS WEATHER                        </p>
                        <p class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-slate-600">
                            <i data-lucide="calendar-days" class="h-3.5 w-3.5 text-slate-500"></i>
                            <time datetime="{{ now()->toDateString() }}">{{ now()->format('F j, Y') }}</time>
                        </p>
                        <div class="mt-1.5 flex items-end gap-2">
                            <p class="text-2xl font-extrabold leading-none text-slate-900 sm:text-3xl">
                                @if ($weather && isset($weather['temp']))
                                    {{ round($weather['temp']) }}°C
                                @else
                                    —
                                @endif
                            </p>
                            <p class="text-xs font-semibold text-slate-600 sm:text-sm">{{ $weatherLabel ?: 'No data' }}</p>
                        </div>
                    </div>
                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/80 text-xl shadow-sm sm:h-11 sm:w-11 sm:text-2xl" aria-hidden="true">{{ $weatherEmoji }}</div>
                </div>
                <div class="mt-2.5 grid grid-cols-3 gap-1.5 sm:gap-2">
                    <article class="rounded-xl border border-sky-100 bg-sky-50 px-2 py-1.5 text-center">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Condition</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-900 sm:text-sm">{{ $conditionLabel }}</p>
                    </article>
                    <article class="rounded-xl border border-emerald-100 bg-emerald-50 px-2 py-1.5 text-center">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Humidity</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-900 sm:text-sm">{{ is_numeric($humidity) ? ((int) round((float) $humidity)) . '%' : '—' }}</p>
                    </article>
                    <article class="rounded-xl border border-violet-100 bg-violet-50 px-2 py-1.5 text-center">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Source</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-900 sm:text-sm">Live API</p>
                    </article>
                </div>
            </section>

            <section class="ag-card overflow-hidden rounded-3xl border border-indigo-200/90 bg-gradient-to-br from-indigo-50 via-violet-50 to-sky-50 p-4 shadow-sm sm:p-5" aria-label="Today's AI forecast">
                <div class="flex items-start justify-between gap-3 border-b border-indigo-100/80 pb-2.5">
                    <h2 class="inline-flex items-center gap-2 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200 animate-pulse">
                            <i data-lucide="brain-circuit" class="h-4 w-4"></i>
                        </span>
                        Today's AI Forecast
                    </h2>
                    <span class="inline-flex items-center gap-1 rounded-full border border-indigo-200/90 bg-white/80 px-2 py-1 text-[10px] font-bold uppercase tracking-[0.1em] text-indigo-700">
                        <i data-lucide="sparkles" class="h-3 w-3"></i>
                        Live model
                    </span>
                </div>
                <div class="mt-2 flex items-center justify-start">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200/90 bg-indigo-50/80 px-3 py-1 text-[11px] font-semibold text-indigo-700 shadow-sm">
                        <i data-lucide="calendar-days" class="h-3.5 w-3.5"></i>
                        <span class="uppercase tracking-wide text-[10px] font-bold text-indigo-600">Forecast</span>
                        <span class="text-slate-700">{{ now()->format('M j, Y') }}</span>
                    </span>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <article class="rounded-2xl border border-indigo-100 bg-white/80 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="thermometer" class="mr-1 inline h-3.5 w-3.5 text-rose-500"></i>Temperature (API)</p>
                        <p class="mt-0.5 text-sm font-bold text-slate-900">{{ is_numeric($weather['temp'] ?? null) ? round((float) $weather['temp'], 1) . ' °C' : 'Not available' }}</p>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white/80 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="cloud-rain" class="mr-1 inline h-3.5 w-3.5 text-blue-500"></i>Rainfall</p>
                        <p id="dash-ai-rain" class="mt-0.5 text-sm font-bold text-slate-900" data-skeleton aria-busy="true">
                            <span class="ag-skeleton ag-skeleton--text" aria-hidden="true"></span>
                        </p>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white/80 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="wind" class="mr-1 inline h-3.5 w-3.5 text-cyan-600"></i>Wind Speed</p>
                        <p id="dash-ai-wind" class="mt-0.5 text-sm font-bold text-slate-900" data-skeleton aria-busy="true">
                            <span class="ag-skeleton ag-skeleton--text" aria-hidden="true"></span>
                        </p>
                    </article>
                    <article class="rounded-2xl border border-indigo-100 bg-white/80 px-3 py-2 transition hover:-translate-y-0.5 hover:shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500"><i data-lucide="shield-check" class="mr-1 inline h-3.5 w-3.5 text-emerald-600"></i>Status</p>
                        <p id="dash-ai-status" class="mt-0.5 text-sm font-bold text-slate-900" data-skeleton aria-busy="true">
                            <span class="ag-skeleton ag-skeleton--text" aria-hidden="true"></span>
                        </p>
                    </article>
                </div>

                <small class="mt-2 inline-flex items-center gap-1 text-[11px] font-medium text-slate-500">
                    <i data-lucide="cpu" class="h-3.5 w-3.5"></i>
                    Powered by AgriGuard AI Model
                </small>
            </section>

            <div class="ag-advisory-toggle-row">
                <button
                    type="button"
                    class="ag-advisory-toggle-btn"
                    data-ai-advisory-toggle
                    data-target="advisory-dashboard-section"
                    data-storage-key="advisory_visibility_dashboard"
                    aria-pressed="true"
                >
                    Hide AI Smart Advisory
                </button>
            </div>
            <section id="advisory-dashboard-section" data-ai-smart-advisory-section>
            <article class="ag-card dash-smart weather-page__smart rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5" aria-label="AI smart advisory">
                <div class="dash-smart__debug">
                    <p class="text-xs font-semibold text-slate-700">
                        @if ($aiAdvisoryReady)
                            <span class="text-emerald-700">AI Smart Advisory: Active</span>
                        @elseif ($aiStatus === 'missing_context')
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
                    <p class="dash-smart__action">{{ $dashboardSummary }}</p>
                </div>
            </article>

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5" aria-label="Today's plan">
                <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                    <i data-lucide="calendar-check-2" class="h-4 w-4 text-amber-600"></i>
                    Today&rsquo;s Plan
                </h2>
                <div class="mt-3 space-y-3">
                    <article class="flex gap-3 rounded-2xl bg-amber-50 px-3 py-3">
                        <span class="text-lg" aria-hidden="true">🌤️</span>
                        <div><p class="text-sm font-semibold text-slate-800">Morning</p><p class="text-sm text-slate-600">{{ $planMorning }}</p></div>
                    </article>
                    <article class="flex gap-3 rounded-2xl bg-sky-50 px-3 py-3">
                        <span class="text-lg" aria-hidden="true">⛅</span>
                        <div><p class="text-sm font-semibold text-slate-800">Afternoon</p><p class="text-sm text-slate-600">{{ $planAfternoon }}</p></div>
                    </article>
                    <article class="flex gap-3 rounded-2xl bg-violet-50 px-3 py-3">
                        <span class="text-lg" aria-hidden="true">🌙</span>
                        <div><p class="text-sm font-semibold text-slate-800">Evening</p><p class="text-sm text-slate-600">{{ $planEvening }}</p></div>
                    </article>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2" aria-label="Avoid and water strategy">
                <article class="ag-card rounded-3xl border border-rose-100 bg-rose-50/70 p-4">
                    <p class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                        <i data-lucide="shield-alert" class="h-3.5 w-3.5 text-rose-600"></i>
                        Avoid
                    </p>
                    <p class="mt-2 text-sm text-slate-700">{{ $avoidText }}</p>
                </article>
                <article class="ag-card rounded-3xl border border-cyan-100 bg-cyan-50/70 p-4">
                    <p class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                        <i data-lucide="droplets" class="h-3.5 w-3.5 text-cyan-700"></i>
                        Water
                    </p>
                    <p class="mt-2 text-sm text-slate-700">{{ $waterText }}</p>
                </article>
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

            @php
                $cropProgress = $cropProgress ?? [
                    'has_planting_date' => false,
                    'crop_type' => null,
                    'stage_key' => 'planting',
                    'stage_label' => 'Planting',
                    'progress_percent' => 0,
                    'days_since_planting' => null,
                    'days_until_next_stage' => null,
                    'next_stage_label' => null,
                    'comparison' => 'match',
                ];
                $cmp = $cropProgress['comparison'] ?? 'match';
                $cmpStyles = $cmp === 'ahead'
                    ? 'border-sky-200 bg-sky-50 text-sky-700'
                    : ($cmp === 'behind'
                        ? 'border-rose-200 bg-rose-50 text-rose-700'
                        : 'border-emerald-200 bg-emerald-50 text-emerald-700');
                $cmpLabel = $cmp === 'ahead' ? 'Ahead' : ($cmp === 'behind' ? 'Behind' : 'On track');
                $cmpIcon = $cmp === 'ahead' ? 'trending-up' : ($cmp === 'behind' ? 'trending-down' : 'check-circle-2');
                $progressPct = max(0, min(100, (int) ($cropProgress['progress_percent'] ?? 0)));
            @endphp
            <section class="ag-card overflow-hidden rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 shadow-sm sm:p-5" aria-label="Crop progress snapshot">
                <div class="flex items-start justify-between gap-3 border-b border-emerald-100/80 pb-2.5">
                    <h2 class="inline-flex items-center gap-2 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200">
                            <i data-lucide="sprout" class="h-4 w-4"></i>
                        </span>
                        Crop Progress
                    </h2>
                    <a href="{{ route('crop-progress.index') }}" class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:text-emerald-900">
                        View timeline
                        <i data-lucide="arrow-right" class="h-3 w-3"></i>
                    </a>
                </div>

                @if (! ($cropProgress['has_planting_date'] ?? false))
                    <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-sm font-semibold text-amber-900">No planting date yet</p>
                        <p class="mt-1 text-xs text-amber-800">Set your crop type and planting date in Farm Settings to track progress.</p>
                        <a href="{{ route('settings') }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-amber-900 hover:underline">
                            Update farm settings
                            <i data-lucide="arrow-right" class="h-3 w-3"></i>
                        </a>
                    </div>
                @else
                    <div class="mt-3 flex items-end justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ $cropProgress['crop_type'] ?? 'Current crop' }} &middot; Stage</p>
                            <p class="mt-0.5 truncate text-base font-extrabold text-slate-900">{{ $cropProgress['stage_label'] }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $cmpStyles }}">
                            <i data-lucide="{{ $cmpIcon }}" class="h-3 w-3"></i>
                            {{ $cmpLabel }}
                        </span>
                    </div>

                    <div class="mt-2.5">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                            <div @style(["width: {$progressPct}%"]) class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 transition-all"></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px] font-medium text-slate-500">
                            <span>{{ $progressPct }}% complete</span>
                            @if (! empty($cropProgress['next_stage_label']))
                                <span>Next: {{ $cropProgress['next_stage_label'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3">
                        <article class="rounded-2xl border border-emerald-100 bg-white/80 px-3 py-2">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">
                                <i data-lucide="calendar-clock" class="mr-1 inline h-3 w-3 text-emerald-600"></i>Days since planting
                            </p>
                            <p class="mt-0.5 text-sm font-bold text-slate-900">{{ is_numeric($cropProgress['days_since_planting'] ?? null) ? $cropProgress['days_since_planting'] : '—' }}</p>
                        </article>
                        <article class="rounded-2xl border border-emerald-100 bg-white/80 px-3 py-2">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">
                                <i data-lucide="flag" class="mr-1 inline h-3 w-3 text-amber-600"></i>Days to next stage
                            </p>
                            <p class="mt-0.5 text-sm font-bold text-slate-900">{{ is_numeric($cropProgress['days_until_next_stage'] ?? null) ? $cropProgress['days_until_next_stage'] : '—' }}</p>
                        </article>
                    </div>
                @endif
            </section>

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5" aria-label="Farm summary and insights">
                <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                    <i data-lucide="sprout" class="h-4 w-4 text-emerald-600"></i>
                    Farm summary & insights
                </h2>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 sm:gap-3">
                    <article class="rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3"><p class="text-xs text-slate-500">Farm</p><p class="text-sm font-semibold text-slate-800">{{ $farmName }}</p></article>
                    <article class="rounded-2xl border border-amber-100 bg-amber-50 px-3 py-3"><p class="text-xs text-slate-500">Crop</p><p class="text-sm font-semibold text-slate-800">{{ $farmType }}</p></article>
                    <article class="rounded-2xl border border-emerald-100 bg-emerald-50 px-3 py-3"><p class="text-xs text-slate-500">Stage</p><p class="text-sm font-semibold text-slate-800">{{ $farmingStage }}</p></article>
                    <article class="rounded-2xl border border-violet-100 bg-violet-50 px-3 py-3"><p class="text-xs text-slate-500">Location</p><p class="text-sm font-semibold text-slate-800">{{ $locationDisplay }}</p></article>
                </div>
            </section>

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5" aria-label="Quick actions">
                <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                    <i data-lucide="zap" class="h-4 w-4 text-indigo-600"></i>
                    Quick actions
                </h2>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><i data-lucide="layout-dashboard" class="h-4 w-4"></i>Dashboard</a>
                    <a href="{{ route('weather-details') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><i data-lucide="cloud-sun" class="h-4 w-4"></i>Weather</a>
                    <a href="{{ route('rainfall-trends') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><i data-lucide="bar-chart-3" class="h-4 w-4"></i>Rainfall Trends</a>
                    <a href="{{ route('crop-progress.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><i data-lucide="sprout" class="h-4 w-4"></i>Crop</a>
                    <a href="{{ route('map.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><i data-lucide="map" class="h-4 w-4"></i>Map</a>
                    <a href="{{ route('assistant.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><i data-lucide="bot" class="h-4 w-4"></i>AI Assistant</a>
                </div>
            </section>
        </div>
    </section>
@endsection

@push('scripts')
    {{-- Defer the ML prediction fetch until after the page has settled so the
         dashboard structure paints first and the user sees a skeleton state
         instead of a blocking spinner. --}}
    <script defer>
        (function () {
            const rainEl = document.getElementById('dash-ai-rain');
            const windEl = document.getElementById('dash-ai-wind');
            const statusEl = document.getElementById('dash-ai-status');
            if (!rainEl || !windEl || !statusEl) {
                return;
            }

            function rainfallStatus(rainfall) {
                if (rainfall >= 20) return 'Heavy Rain';
                if (rainfall >= 8) return 'Moderate';
                return 'Normal';
            }

            function settle(el, value) {
                el.removeAttribute('data-skeleton');
                el.removeAttribute('aria-busy');
                el.innerText = value;
            }

            function fetchPrediction() {
                const controller = new AbortController();
                // 30s is plenty for a Python subprocess invocation; the previous 100s
                // value left users waiting on the spinner long after the request had stalled.
                const timer = setTimeout(() => controller.abort(), 30000);

                fetch("{{ route('api.weather-prediction', [], false) }}", {
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(async (response) => {
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const parts = [data.error, data.detail].filter(Boolean);
                            throw new Error(parts.length ? parts.join(' — ') : `HTTP ${response.status}`);
                        }
                        return data;
                    })
                    .then((data) => {
                        if (!Array.isArray(data.forecast) || data.forecast.length === 0) {
                            throw new Error('Invalid AI payload');
                        }
                        const today = data.forecast[0];
                        const r = Number(today.rainfall);
                        const w = Number(today.wind_speed);
                        if (!Number.isFinite(r) || !Number.isFinite(w)) {
                            throw new Error('Non-numeric AI payload');
                        }
                        settle(rainEl, `${r.toFixed(3)} mm`);
                        settle(windEl, `${w.toFixed(3)} km/h`);
                        settle(statusEl, rainfallStatus(r));
                    })
                    .catch(() => {
                        settle(rainEl, 'Not available');
                        settle(windEl, 'Not available');
                        settle(statusEl, 'Unavailable');
                    })
                    .finally(() => {
                        clearTimeout(timer);
                    });
            }

            // requestIdleCallback gives the rest of the page a moment to mount first.
            if (typeof window.requestIdleCallback === 'function') {
                window.requestIdleCallback(fetchPrediction, { timeout: 1200 });
            } else {
                setTimeout(fetchPrediction, 200);
            }
        })();
    </script>
@endpush
