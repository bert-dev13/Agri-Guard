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
    $rainfallMm = $weather['today_expected_rainfall'] ?? ($advisoryData['forecast_rainfall_mm'] ?? null);
    $rainStatIsChance = is_numeric($rainChance);
    $rainStatLabel = $rainStatIsChance ? 'Rain' : 'Rainfall';
    $rainStatValue = $rainStatIsChance
        ? ((int) round((float) $rainChance)) . '%'
        : (is_numeric($rainfallMm) ? round((float) $rainfallMm, 1) . ' mm' : '—');
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

    $weatherOutlookCard = is_array($weather_outlook ?? null) ? $weather_outlook : [];
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
                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Rain</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-900 sm:text-sm">{{ $rainStatIsChance ? $rainStatValue : '—' }}</p>
                    </article>
                    <article class="rounded-xl border border-emerald-100 bg-emerald-50 px-2 py-1.5 text-center">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Humidity</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-900 sm:text-sm">{{ is_numeric($humidity) ? ((int) round((float) $humidity)) . '%' : '—' }}</p>
                    </article>
                    <article class="rounded-xl border border-violet-100 bg-violet-50 px-2 py-1.5 text-center">
                        <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Wind</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-900 sm:text-sm">{{ is_numeric($windSpeed) ? round((float) $windSpeed, 1) . ' km/h' : '—' }}</p>
                    </article>
                </div>
            </section>

            @include('partials.farm-advisory-card', [
                'weather_outlook' => $weatherOutlookCard,
                'wrapperClass' => 'rounded-2xl',
                'detailsLinkHref' => route('weather-details'),
                'detailsLinkAria' => 'Open full weather details',
                'detailsLinkSr' => 'Weather details',
            ])

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

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5" aria-label="Forecast preview">
                <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                    <i data-lucide="calendar-days" class="h-4 w-4 text-violet-600"></i>
                    Forecast preview
                </h2>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-5 sm:gap-3" role="list">
                    @forelse (array_slice($forecast, 0, 5) as $day)
                        @php
                            $conditionId = (int) ($day['condition']['id'] ?? 800);
                            $dayEmoji = \App\Http\Controllers\WeatherDetailsController::simpleWeatherEmoji($conditionId);
                            $dayLabel = $day['day_name'] ?? \Carbon\Carbon::parse($day['date'] ?? now())->format('D');
                            $dayCond = \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($conditionId);
                            $maxTemp = isset($day['temp_max']) ? round((float) $day['temp_max']) : null;
                            $minTemp = isset($day['temp_min']) ? round((float) $day['temp_min']) : null;
                            $dayRain = isset($day['pop']) && is_numeric($day['pop']) ? ((int) round((float) $day['pop'])) . '%' : '0%';
                        @endphp
                        <article class="rounded-2xl border {{ $loop->first ? 'border-slate-300 bg-slate-100' : 'border-slate-100 bg-slate-50' }} p-3 text-center" role="listitem">
                            <p class="text-xs font-semibold text-slate-700">{{ $dayLabel }}</p>
                            <p class="mt-1 text-2xl" aria-hidden="true">{{ $dayEmoji }}</p>
                            <p class="mt-1 text-[11px] text-slate-500">{{ $dayCond }}</p>
                            <p class="mt-1 text-xs font-semibold text-slate-700">{{ $maxTemp !== null && $minTemp !== null ? ($maxTemp . '° / ' . $minTemp . '°') : '—' }}</p>
                            <p class="mt-1 text-[11px] font-medium text-slate-500">Rain {{ $dayRain }}</p>
                        </article>
                    @empty
                        <p class="col-span-full text-sm text-slate-500">Forecast data is not available yet.</p>
                    @endforelse
                </div>
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
                    <a href="{{ route('assistant.index') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"><i data-lucide="bot" class="h-4 w-4"></i>Assistant</a>
                </div>
            </section>
        </div>
    </section>
@endsection
