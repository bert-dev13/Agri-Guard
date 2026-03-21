@php
    $rainProbDisplay = $rain_probability_display ?? $forecast_rain_probability ?? ($weatherData['today_rain_probability'] ?? null);
    $rainfallMm = $weather['today_expected_rainfall'] ?? ($weatherData['today_expected_rainfall'] ?? null);
    $rainStatIsChance = is_numeric($rainProbDisplay);
    $rainStatLabel = $rainStatIsChance ? 'Rain Chance' : 'Rainfall';
    $rainStatValue = $rainStatIsChance
        ? ((int) round((float) $rainProbDisplay)) . '%'
        : (is_numeric($rainfallMm) ? round((float) $rainfallMm, 1) . ' mm' : '0%');

    $stageLabels = [
        'land_preparation' => 'Land Preparation',
        'planting' => 'Planting',
        'early_growth' => 'Early Growth',
        'growing' => 'Growing',
        'flowering_fruiting' => 'Flowering',
        'harvesting' => 'Harvesting',
    ];
    $stageLabel = Auth::user()->farming_stage ? ($stageLabels[Auth::user()->farming_stage] ?? Auth::user()->farming_stage) : null;
    $farmName = $crop_type ? ($crop_type . ' Farm') : 'Rice Farm';
    $farmStage = $stageLabel ?: 'Planting';
    $headerLocation = $farm_location_display ?: 'Barangay Calamagui, Amulung, Cagayan';
    $insights = $agri_insights ?? [];
@endphp
@extends('layouts.user')

@section('title', 'Weather Details – AGRIGUARD')

@section('body-class', 'weather-page min-h-screen bg-[#F5F7FA]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            <header class="ag-card ag-welcome-gradient overflow-hidden" aria-label="Weather header">
                <div class="relative px-5 py-5 sm:px-6 sm:py-6 flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">Weather Details</h1>
                        <p class="mt-1.5 text-white/90 text-sm">Detailed weather insights for your farm.</p>
                        <p class="mt-2 text-white/95 text-sm flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-4 h-4 shrink-0 opacity-90"></i>
                            {{ $headerLocation }}
                        </p>
                        <p class="mt-1.5 text-white/85 text-sm">{{ $farmName }} • {{ $farmStage }}</p>
                        <p class="mt-1 text-white/70 text-xs">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <span class="flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-white/20" aria-hidden="true">
                            <i data-lucide="cloud-sun" class="w-7 h-7 sm:w-8 sm:h-8 text-white"></i>
                        </span>
                        <a
                            href="{{ route('rainfall-trends') }}"
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/95 hover:bg-white text-[#0F172A] text-xs sm:text-sm font-semibold px-3 py-1.5 border border-white/70 shadow-sm transition-colors"
                            aria-label="View Rainfall Trends"
                        >
                            <i data-lucide="cloud-rain" class="w-3.5 h-3.5 text-blue-600"></i>
                            View Rainfall Trends
                        </a>
                    </div>
                </div>
            </header>

            @if ($weather && isset($weather['temp']))
                <article class="ag-card weather-dashboard-card p-4 sm:p-5">
                    <div class="weather-featured-card">
                        <div class="weather-featured-head">
                            <h2 class="weather-section-title">
                                <i data-lucide="cloud-sun" class="w-4 h-4" aria-hidden="true"></i>
                                Current Weather
                            </h2>
                        </div>
                        <div class="weather-featured-main">
                            <span class="weather-featured-icon" aria-hidden="true">
                                <i data-lucide="{{ $weather['simple_icon'] ?? 'sun' }}"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="weather-featured-temp">{{ round((float) $weather['temp']) }}<span class="weather-featured-unit">°C</span></p>
                                <p class="weather-featured-condition">{{ $weather['simple_label'] ?? 'Clear' }}</p>
                                <p class="weather-featured-summary">{{ $summary_message ?? 'Weather looks stable for normal field activity.' }}</p>
                            </div>
                        </div>
                        <div class="weather-mini-stats">
                            <article class="weather-mini-stat">
                                <span class="weather-mini-stat-icon"><i data-lucide="cloud-rain"></i></span>
                                <span>
                                    <span class="weather-mini-stat-label">{{ $rainStatLabel }}</span>
                                    <span class="weather-mini-stat-value">{{ $rainStatValue }}</span>
                                </span>
                            </article>
                            <article class="weather-mini-stat">
                                <span class="weather-mini-stat-icon"><i data-lucide="droplets"></i></span>
                                <span>
                                    <span class="weather-mini-stat-label">Humidity</span>
                                    <span class="weather-mini-stat-value">{{ is_numeric($weather['humidity'] ?? null) ? ((int) round((float) $weather['humidity'])) . '%' : '0%' }}</span>
                                </span>
                            </article>
                            <article class="weather-mini-stat">
                                <span class="weather-mini-stat-icon"><i data-lucide="wind"></i></span>
                                <span>
                                    <span class="weather-mini-stat-label">Wind</span>
                                    <span class="weather-mini-stat-value">{{ is_numeric($weather['wind_speed'] ?? null) ? round((float) $weather['wind_speed'], 1) . ' km/h' : '0 km/h' }}</span>
                                </span>
                            </article>
                        </div>
                    </div>
                </article>
            @else
                <article class="ag-card p-5 sm:p-6 text-center">
                    <i data-lucide="cloud-off" class="w-12 h-12 text-slate-300 mx-auto"></i>
                    <p class="mt-3 text-sm font-medium text-slate-500 text-center">Weather data unavailable</p>
                </article>
            @endif

            @php
                $weatherReco = is_array($recommendation ?? null) ? $recommendation : [];
                $todayPlan = is_array($weatherReco['today_plan'] ?? null) ? $weatherReco['today_plan'] : [];
                $aiStatus = strtolower((string) ($weatherReco['ai_status'] ?? 'failed'));
                $aiModel = trim((string) ($weatherReco['ai_model'] ?? ''));
                $aiError = trim((string) ($weatherReco['ai_error'] ?? ''));
                $showAiDebug = app()->environment('local') || (bool) config('app.debug');
                $riskLevel = strtolower((string) ($weatherReco['risk_level'] ?? 'moderate'));
                $riskLabel = $riskLevel === 'high' ? 'High' : ($riskLevel === 'low' ? 'Low' : 'Moderate');

                $riskMap = [
                    'low' => [
                        'badge' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
                    ],
                    'moderate' => [
                        'badge' => 'bg-amber-100 text-amber-700 border border-amber-200',
                    ],
                    'high' => [
                        'badge' => 'bg-rose-100 text-rose-700 border border-rose-200',
                    ],
                ];
                $riskStyle = $riskMap[$riskLevel] ?? $riskMap['moderate'];
            @endphp

            <article class="ag-card border border-slate-200 bg-white p-4 sm:p-5 rounded-2xl shadow-sm" aria-label="Today's smart weather action">
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
                            <p class="text-sm text-amber-900 font-semibold">Live AI weather advice is temporarily unavailable. Showing a safe fallback recommendation.</p>
                        </div>
                    @endif

                    <header class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 flex items-center gap-1.5">
                            <i data-lucide="sprout" class="w-4 h-4 text-[#2E7D32]"></i>
                            TODAY’S SMART WEATHER ACTION
                        </p>
                        <p class="text-xl sm:text-2xl font-extrabold text-slate-900 leading-tight">{{ $weatherReco['main_recommendation'] ?? 'Check weather conditions before starting field work today.' }}</p>
                        <div class="flex flex-wrap items-center gap-2.5 text-xs">
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-700 px-3 py-1.5 border border-slate-200">
                                <i data-lucide="gauge" class="w-3.5 h-3.5"></i>
                                Farm Score: {{ $weatherReco['farm_score'] ?? 6 }}/10
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1.5 border border-emerald-200">
                                <i data-lucide="brain" class="w-3.5 h-3.5"></i>
                                AI Confidence: {{ $weatherReco['ai_confidence'] ?? 'Medium' }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 font-semibold {{ $riskStyle['badge'] }}">
                                ⚠️ Risk Level: {{ $riskLabel }}
                            </span>
                        </div>
                    </header>

                    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                            <i data-lucide="clock-3" class="w-4 h-4 text-[#2E7D32]"></i>
                            Today&rsquo;s Plan
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-900 mb-1">🌅 Morning</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $todayPlan['morning'] ?? 'No action needed.' }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-900 mb-1">☀️ Afternoon</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $todayPlan['afternoon'] ?? 'No action needed.' }}</p>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <p class="text-sm font-semibold text-slate-900 mb-1">🌙 Evening</p>
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $todayPlan['evening'] ?? 'No action needed.' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 shadow-sm">
                            <p class="text-sm font-semibold text-amber-800 mb-1.5 flex items-center gap-1.5">
                                <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                                Avoid
                            </p>
                            <p class="text-sm text-amber-900 leading-relaxed">{{ $weatherReco['avoid'] ?? 'Avoid risky operations when rain or wind increases.' }}</p>
                        </div>

                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 shadow-sm">
                            <p class="text-sm font-semibold text-emerald-800 mb-1.5 flex items-center gap-1.5">
                                <i data-lucide="droplets" class="w-4 h-4"></i>
                                Water Strategy
                            </p>
                            <p class="text-sm text-emerald-900 leading-relaxed">{{ $weatherReco['water_strategy'] ?? 'Maintain normal irrigation and monitor rain updates.' }}</p>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-slate-50/80 p-3">
                        <details>
                            <summary class="cursor-pointer text-xs sm:text-sm font-semibold text-slate-700">Why this recommendation?</summary>
                            <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">{{ $weatherReco['why'] ?? 'Weather data is limited right now. Follow caution and monitor updates.' }}</p>
                        </details>
                    </section>
                </div>
            </article>

            @if (!empty($forecast))
                <section class="ag-card weather-forecast-panel p-5 sm:p-6">
                    <h2 class="weather-section-title weather-section-title--sub">
                        <i data-lucide="calendar-days" class="w-4 h-4" aria-hidden="true"></i>
                        5-Day Forecast
                    </h2>
                    <div class="forecast-vertical-list">
                        @foreach ($forecast as $day)
                            @php
                                $conditionId = (int) ($day['condition']['id'] ?? 800);
                                $dayIcon = \App\Http\Controllers\WeatherDetailsController::simpleWeatherIcon($conditionId);
                                $dayCondition = \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($conditionId);
                                $dayLabel = $day['day_name'] ?? \Carbon\Carbon::parse($day['date'] ?? now())->format('D');
                                $dayRainChance = isset($day['pop']) && is_numeric($day['pop']) ? (int) round((float) $day['pop']) . '%' : '0%';
                            @endphp
                            <article class="forecast-vertical-item">
                                <p class="forecast-vertical-day">{{ $dayLabel }}</p>
                                <span class="forecast-vertical-icon"><i data-lucide="{{ $dayIcon }}"></i></span>
                                <div class="forecast-vertical-meta">
                                    <p class="forecast-vertical-condition">{{ $dayCondition }}</p>
                                    <p class="forecast-vertical-temp">{{ $day['temp_max'] }}° / {{ $day['temp_min'] }}°</p>
                                </div>
                                <p class="forecast-vertical-rain">Rain {{ $dayRainChance }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if (!empty($hourly_forecast))
                <section class="ag-card p-5 sm:p-6">
                    <h2 class="weather-section-title weather-section-title--sub">
                        <i data-lucide="clock-3" class="w-4 h-4" aria-hidden="true"></i>
                        Hourly Forecast
                    </h2>
                    <div class="hourly-scroll">
                        @foreach ($hourly_forecast as $hourly)
                            @php
                                $hourlyCondition = (int) ($hourly['condition_id'] ?? 800);
                                $hourlyIcon = \App\Http\Controllers\WeatherDetailsController::simpleWeatherIcon($hourlyCondition);
                                $hourlyLabel = \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($hourlyCondition);
                                $hourlyRain = is_numeric($hourly['pop'] ?? null) ? ((int) round((float) $hourly['pop'])) . '%' : '0%';
                            @endphp
                            <article class="hourly-card">
                                <p class="hourly-time">{{ $hourly['time'] ?? '--' }}</p>
                                <span class="hourly-icon"><i data-lucide="{{ $hourlyIcon }}"></i></span>
                                <p class="hourly-temp">{{ isset($hourly['temp']) ? round((float) $hourly['temp']) . '°C' : '--' }}</p>
                                <p class="hourly-rain">Rain {{ $hourlyRain }}</p>
                                <p class="hourly-label">{{ $hourlyLabel }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($weather)
                <section class="ag-card p-5 sm:p-6">
                    <h2 class="weather-section-title weather-section-title--sub">
                        <i data-lucide="layout-grid" class="w-4 h-4" aria-hidden="true"></i>
                        Additional Weather Details
                    </h2>
                    <div class="details-enhanced-grid">
                        @php
                            $hideMissingDetailCards = $hide_missing_weather_cards ?? false;

                            $detailItems = [
                                [
                                    'label' => 'Actual Feel',
                                    'raw' => is_numeric($weather['feels_like'] ?? null)
                                        ? round((float) $weather['feels_like']) . '°C'
                                        : (is_numeric($weather['temp'] ?? null) ? round((float) $weather['temp']) . '°C' : null),
                                    'icon' => 'thermometer',
                                    'tone' => 'green',
                                    'helpText' => 'How hot it really feels',
                                ],
                                [
                                    'label' => 'Rain Chance Today',
                                    'raw' => is_numeric($rainProbDisplay ?? null) ? ((int) round((float) $rainProbDisplay)) . '%' : null,
                                    'icon' => 'cloud-rain',
                                    'tone' => 'blue',
                                    'helpText' => 'Chance of rain that may affect farm work',
                                ],
                                [
                                    'label' => 'Air Moisture Level',
                                    'raw' => is_numeric($dew_point ?? null) ? round((float) $dew_point, 1) . '°C' : null,
                                    'icon' => 'droplets',
                                    'tone' => 'green',
                                    'helpText' => 'Helps show if the air feels dry or sticky',
                                ],
                                [
                                    'label' => 'Cloud Level',
                                    'raw' => is_numeric($cloud_cover ?? null) ? ((int) $cloud_cover) . '%' : null,
                                    'icon' => 'cloud',
                                    'tone' => 'blue',
                                    'helpText' => 'How cloudy the sky is',
                                ],
                            ];

                            if ($hideMissingDetailCards) {
                                $detailItems = array_values(array_filter($detailItems, fn($item) => !is_null($item['raw'])));
                            }
                        @endphp
                        @foreach ($detailItems as $item)
                            <article class="metric-card metric-card--{{ $item['tone'] }}">
                                <span class="metric-card-icon"><i data-lucide="{{ $item['icon'] }}"></i></span>
                                <p class="metric-card-label">{{ $item['label'] }}</p>
                                <p class="metric-card-value">{{ $item['raw'] ?? 'No data' }}</p>
                                <p class="metric-card-help">{{ $item['helpText'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

            @endif

            @if (!empty($forecast))
                <section class="ag-card p-5 sm:p-6">
                    <h2 class="weather-section-title weather-section-title--sub">
                        <i data-lucide="chart-no-axes-combined" class="w-4 h-4" aria-hidden="true"></i>
                        Weather Trends
                    </h2>
                    <div class="trend-list">
                        @foreach ($forecast as $day)
                            @php
                                $tempRange = max(1, (int) ($day['temp_max'] ?? 0));
                                $tempPercent = max(0, min(100, (int) round(($tempRange / 45) * 100)));
                                $rainPercent = max(0, min(100, (int) ($day['pop'] ?? 0)));
                            @endphp
                            <article class="trend-item">
                                <p class="trend-day">{{ $day['day_name'] ?? 'Day' }}</p>
                                <div class="trend-track-group">
                                    <div class="trend-label-row">
                                        <span>Temperature</span>
                                        <span>{{ $day['temp_max'] ?? '--' }}°C</span>
                                    </div>
                                    <div class="trend-track"><span style="width: {{ $tempPercent }}%"></span></div>
                                    <div class="trend-label-row mt-2">
                                        <span>Rain Probability</span>
                                        <span>{{ $rainPercent }}%</span>
                                    </div>
                                    <div class="trend-track trend-track--rain"><span style="width: {{ $rainPercent }}%"></span></div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <div class="grid grid-cols-2 gap-3">
                <a href="{{ route('dashboard') }}" class="weather-action-link">
                    <span class="weather-action-icon"><i data-lucide="layout-dashboard"></i></span>
                    <p class="weather-action-label">Dashboard</p>
                </a>
                <a href="{{ route('rainfall-trends') }}" class="weather-action-link">
                    <span class="weather-action-icon"><i data-lucide="cloud-rain"></i></span>
                    <p class="weather-action-label">Rainfall Trends</p>
                </a>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
@endpush
