@php
    $user = Auth::user();
    $weather = $advisoryData['weather'] ?? null;
    $forecast = $advisoryData['forecast'] ?? [];
    $locationDisplay = $advisoryData['location_display'] ?? (($user->farm_municipality ?? 'Amulung') . ', Cagayan');

    $weatherConditionId = $weather['condition']['id'] ?? 800;
    $weatherIcon = \App\Http\Controllers\WeatherDetailsController::simpleWeatherIcon((int) $weatherConditionId);
    $weatherLabel = \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel((int) $weatherConditionId);

    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $safeUserName = trim((string) ($user->name ?? 'Farmer'));
    $farmName = $safeUserName !== '' ? $safeUserName . ' Farm' : 'My Farm';
    $farmType = trim((string) ($user->crop_type ?? 'Not set'));
    $farmingStage = $user->farming_stage ? ucfirst(str_replace('_', ' ', (string) $user->farming_stage)) : 'Not set';
    $rainChance = $weather['today_rain_probability'] ?? ($advisoryData['today_rain_probability'] ?? null);
    $rainfallMm = $weather['today_expected_rainfall'] ?? ($advisoryData['forecast_rainfall_mm'] ?? null);
    $rainStatIsChance = is_numeric($rainChance);
    $rainStatLabel = $rainStatIsChance ? 'Rain Chance' : 'Rainfall';
    $rainStatValue = $rainStatIsChance
        ? ((int) round((float) $rainChance)) . '%'
        : (is_numeric($rainfallMm) ? round((float) $rainfallMm, 1) . ' mm' : '0%');
    $humidity = $weather['humidity'] ?? null;
    $windSpeed = $weather['wind_speed'] ?? null;
@endphp

@extends('layouts.user')

@section('title', 'Dashboard – AGRIGUARD')

@section('body-class', 'dashboard-page min-h-screen bg-[#F4F6F5]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            <header class="ag-card ag-welcome-gradient overflow-hidden" style="background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 40%, #388e3c 70%, #43a047 100%);" aria-label="Welcome section">
                <div class="relative px-5 py-5 sm:px-6 sm:py-6 flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">{{ $greeting }}, {{ $safeUserName ?: 'Farmer' }}</h1>
                        <p class="mt-1.5 text-white/90 text-sm">Here is your farm snapshot for today.</p>
                        <p class="mt-2 text-white/95 text-sm flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-4 h-4 shrink-0 opacity-90"></i>
                            {{ $locationDisplay }}
                        </p>
                        <p class="mt-1 text-white/70 text-xs">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                    <span class="flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-white/20 shrink-0" aria-hidden="true">
                        <i data-lucide="layout-dashboard" class="w-7 h-7 sm:w-8 sm:h-8 text-white"></i>
                    </span>
                </div>
            </header>

            <section class="ag-card p-4 sm:p-5" aria-label="Farm summary">
                <h2 class="text-base font-bold text-slate-900 mb-3.5 flex items-center gap-2">
                    <i data-lucide="tractor" class="w-5 h-5 text-[#2E7D32]"></i>
                    Farm Summary
                </h2>
                <div class="farm-summary-grid">
                    <article class="farm-summary-card farm-summary-card--green">
                        <span class="farm-summary-icon"><i data-lucide="tractor" class="text-[#2E7D32]"></i></span>
                        <div class="farm-summary-content">
                            <p class="farm-summary-label">Farm Name</p>
                            <p class="farm-summary-value">{{ $farmName }}</p>
                        </div>
                    </article>
                    <article class="farm-summary-card farm-summary-card--blue">
                        <span class="farm-summary-icon"><i data-lucide="sprout" class="text-blue-600"></i></span>
                        <div class="farm-summary-content">
                            <p class="farm-summary-label">Crop Type</p>
                            <p class="farm-summary-value">{{ $farmType }}</p>
                        </div>
                    </article>
                    <article class="farm-summary-card farm-summary-card--amber">
                        <span class="farm-summary-icon"><i data-lucide="activity" class="text-amber-600"></i></span>
                        <div class="farm-summary-content">
                            <p class="farm-summary-label">Growth Stage</p>
                            <p class="farm-summary-value">{{ $farmingStage }}</p>
                        </div>
                    </article>
                    <article class="farm-summary-card farm-summary-card--violet">
                        <span class="farm-summary-icon"><i data-lucide="map-pin" class="text-violet-600"></i></span>
                        <div class="farm-summary-content">
                            <p class="farm-summary-label">Farm Location</p>
                            <p class="farm-summary-value">{{ $locationDisplay }}</p>
                        </div>
                    </article>
                </div>
            </section>

            <x-smart-recommendation :recommendation="$recommendation ?? []" :failed="(bool) ($recommendation_failed ?? false)" />

            <section class="weather-dashboard-wrap" aria-label="Weather section">
                <article class="ag-card weather-dashboard-card p-4 sm:p-5" aria-label="Current weather and 5 day forecast summary">
                    <div class="weather-featured-card">
                        <div class="weather-featured-head">
                            <h2 class="weather-section-title">
                                <i data-lucide="cloud-sun" class="w-4 h-4" aria-hidden="true"></i>
                                Current Weather
                            </h2>
                        </div>
                        <div class="weather-featured-main">
                            <span class="weather-featured-icon" aria-hidden="true">
                                <i data-lucide="{{ $weatherIcon }}"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="weather-featured-temp">
                                    @if ($weather && isset($weather['temp']))
                                        {{ round($weather['temp']) }}<span class="weather-featured-unit">°C</span>
                                    @else
                                        --
                                    @endif
                                </p>
                                <p class="weather-featured-condition">{{ $weatherLabel ?: 'No weather data' }}</p>
                            </div>
                        </div>
                        <div class="weather-mini-stats" aria-label="Weather quick metrics">
                            <article class="weather-mini-stat">
                                <span class="weather-mini-stat-icon"><i data-lucide="cloud-rain" aria-hidden="true"></i></span>
                                <span class="weather-mini-stat-meta">
                                    <span class="weather-mini-stat-label">{{ $rainStatLabel }}</span>
                                    <span class="weather-mini-stat-value">{{ $rainStatValue }}</span>
                                </span>
                            </article>
                            <article class="weather-mini-stat">
                                <span class="weather-mini-stat-icon"><i data-lucide="droplets" aria-hidden="true"></i></span>
                                <span class="weather-mini-stat-meta">
                                    <span class="weather-mini-stat-label">Humidity</span>
                                    <span class="weather-mini-stat-value">{{ is_numeric($humidity) ? ((int) round((float) $humidity)) . '%' : '0%' }}</span>
                                </span>
                            </article>
                            <article class="weather-mini-stat">
                                <span class="weather-mini-stat-icon"><i data-lucide="wind" aria-hidden="true"></i></span>
                                <span class="weather-mini-stat-meta">
                                    <span class="weather-mini-stat-label">Wind</span>
                                    <span class="weather-mini-stat-value">{{ is_numeric($windSpeed) ? round((float) $windSpeed, 1) . ' km/h' : '0 km/h' }}</span>
                                </span>
                            </article>
                        </div>
                    </div>

                    <div class="weather-forecast-panel mt-3.5 sm:mt-4" aria-label="5 day weather forecast">
                        <h3 class="weather-section-title weather-section-title--sub">
                            <i data-lucide="calendar-days" class="w-4 h-4" aria-hidden="true"></i>
                            5-Day Forecast
                        </h3>
                        <div class="forecast-vertical-list">
                            @forelse (array_slice($forecast, 0, 5) as $day)
                                @php
                                    $conditionId = (int) ($day['condition']['id'] ?? 800);
                                    $dayIcon = \App\Http\Controllers\WeatherDetailsController::simpleWeatherIcon($conditionId);
                                    $dayLabel = $day['day_name'] ?? \Carbon\Carbon::parse($day['date'] ?? now())->format('D');
                                    $dayCondition = \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($conditionId);
                                    $minTemp = isset($day['temp_min']) ? round((float) $day['temp_min']) . '°' : '--';
                                    $maxTemp = isset($day['temp_max']) ? round((float) $day['temp_max']) . '°' : '--';
                                    $dayRainChance = isset($day['pop']) && is_numeric($day['pop']) ? (int) round((float) $day['pop']) . '%' : null;
                                @endphp
                                <article class="forecast-vertical-item">
                                    <p class="forecast-vertical-day">{{ $dayLabel }}</p>
                                    <span class="forecast-vertical-icon" aria-hidden="true"><i data-lucide="{{ $dayIcon }}"></i></span>
                                    <div class="forecast-vertical-meta">
                                        <p class="forecast-vertical-condition">{{ $dayCondition }}</p>
                                        <p class="forecast-vertical-temp">{{ $maxTemp }} / {{ $minTemp }}</p>
                                    </div>
                                    <p class="forecast-vertical-rain">{{ $dayRainChance ? ('Rain ' . $dayRainChance) : 'Rain 0%' }}</p>
                                </article>
                            @empty
                                @for ($i = 0; $i < 5; $i++)
                                    <article class="forecast-vertical-item forecast-vertical-item--empty">
                                        <p class="forecast-vertical-day">{{ now()->addDays($i)->format('D') }}</p>
                                        <span class="forecast-vertical-icon" aria-hidden="true"><i data-lucide="cloud-off"></i></span>
                                        <div class="forecast-vertical-meta">
                                            <p class="forecast-vertical-condition">No data</p>
                                            <p class="forecast-vertical-temp">-- / --</p>
                                        </div>
                                        <p class="forecast-vertical-rain">Rain 0%</p>
                                    </article>
                                @endfor
                            @endforelse
                        </div>
                    </div>
                </article>
            </section>

            <section class="ag-card p-5 sm:p-6" aria-label="Quick actions">
                <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <i data-lucide="zap" class="w-5 h-5 text-[#2E7D32]"></i>
                    Quick Actions
                </h2>
                <div class="quick-actions-grid">
                    <a href="{{ route('weather-details') }}" class="quick-action-card"><span class="quick-action-icon"><i data-lucide="cloud-sun"></i></span><span class="quick-action-label">Weather</span></a>
                    <a href="{{ route('rainfall-trends') }}" class="quick-action-card"><span class="quick-action-icon"><i data-lucide="bar-chart-3"></i></span><span class="quick-action-label">Rainfall</span></a>
                    <a href="{{ route('settings') }}#farm-profile" class="quick-action-card"><span class="quick-action-icon"><i data-lucide="tractor"></i></span><span class="quick-action-label">Farm</span></a>
                    <a href="{{ route('settings') }}" class="quick-action-card"><span class="quick-action-icon"><i data-lucide="settings"></i></span><span class="quick-action-label">Settings</span></a>
                </div>
            </section>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
@endpush
