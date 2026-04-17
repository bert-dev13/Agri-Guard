@php
    $rainProbDisplay = $rain_probability_display ?? $forecast_rain_probability ?? ($weatherData['today_rain_probability'] ?? null);
    $rainfallMm = $weather['today_expected_rainfall'] ?? ($weatherData['today_expected_rainfall'] ?? null);
    $rainStatIsChance = is_numeric($rainProbDisplay);
    $rainStatLabel = $rainStatIsChance ? 'Rain' : 'Rainfall';
    $rainStatValue = $rainStatIsChance
        ? ((int) round((float) $rainProbDisplay)) . '%'
        : (is_numeric($rainfallMm) ? round((float) $rainfallMm, 1) . ' mm' : '—');

    $stageLabel = filled(Auth::user()->farming_stage)
        ? app(\App\Services\CropTimelineService::class)->displayLabel(Auth::user()->farming_stage)
        : null;
    $farmName = $crop_type ? ($crop_type . ' Farm') : 'Rice Farm';
    $farmStage = $stageLabel ?: 'Planting';
    $headerLocation = $farm_location_display ?: 'Barangay Calamagui, Amulung, Cagayan';
    $insights = $agri_insights ?? [];
    $recoForRisk = is_array($recommendation ?? null) ? $recommendation : [];
    $riskForBadge = strtolower((string) ($recoForRisk['risk'] ?? $recoForRisk['risk_level'] ?? ''));
    $weatherRiskTone = $riskForBadge !== '' ? $riskForBadge : 'moderate';
    $weatherRiskBadgeClass = $weatherRiskTone === 'low'
        ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
        : ($weatherRiskTone === 'high'
            ? 'bg-rose-100 text-rose-800 border border-rose-200'
            : 'bg-amber-100 text-amber-800 border border-amber-200');
    $impactReco = is_array($recommendation ?? null) ? $recommendation : [];
    $impactAiStatus = strtolower(trim((string) ($impactReco['ai_status'] ?? 'failed')));
    $impactAiReady = $impactAiStatus === 'success';
    $impactAiError = trim((string) ($impactReco['ai_error'] ?? ''));
    $impactSummary = $impactAiReady
        ? trim((string) ($impactReco['main_recommendation'] ?? 'AI advisory active'))
        : 'AI advisory unavailable';
    if (strcasecmp($impactSummary, 'Prepare for the upcoming rain to protect newly planted rice.') === 0) {
        $impactSummary = 'Monitor field conditions today and prepare your crop protection plan before the next rainfall.';
    }
    if (strcasecmp($impactSummary, 'Plant rice now but prepare for rain on Wednesday') === 0) {
        $impactSummary = '';
    }
    $impactDetails = $impactAiReady
        ? array_values(array_filter(array_unique([
            trim((string) ($impactReco['why'] ?? '')),
            trim((string) ($impactReco['today_plan']['afternoon'] ?? '')),
            trim((string) ($impactReco['today_plan']['evening'] ?? '')),
        ]), fn ($item) => $item !== ''))
        : [];
    $impactAdvice = $impactAiReady
        ? array_values(array_filter(array_unique([
            trim((string) ($impactReco['today_plan']['morning'] ?? '')),
            trim((string) ($impactReco['avoid'] ?? '')),
            trim((string) ($impactReco['water_strategy'] ?? '')),
        ]), fn ($item) => $item !== ''))
        : [];
    $impactLevel = strtolower(trim((string) ($impactReco['risk_level'] ?? $impactReco['risk'] ?? 'unknown')));
    if (! $impactAiReady) {
        $impactDetails = [($impactAiError !== '' ? $impactAiError : 'Together AI advisory is currently unavailable.')];
        $impactAdvice = ['Refresh in a few minutes after weather data updates.'];
    }
    $impactToneClass = match ($impactLevel) {
        'critical', 'high' => 'weather-impact__summary-badge--high',
        'moderate' => 'weather-impact__summary-badge--caution',
        'low' => 'weather-impact__summary-badge--normal',
        default => 'weather-impact__summary-badge--unknown',
    };
    $riskSnapshot = is_array($risk_snapshot ?? null) ? $risk_snapshot : [];
    $snapshotCropLoss = (string) ($riskSnapshot['estimated_crop_loss'] ?? 'N/A');
    $snapshotEffect = (string) ($riskSnapshot['three_day_effect'] ?? 'No forecast impact available');
    $snapshotFlood = (string) ($riskSnapshot['flood_risk_level'] ?? 'Unknown');

    $clayDataUri = static function (string $svg): string {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    };

    $clay = [
        'sun' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="cs" cx="32%" cy="28%" r="70%"><stop offset="0%" stop-color="#FFFBEB"/><stop offset="45%" stop-color="#FDE047"/><stop offset="100%" stop-color="#EAB308"/></radialGradient><filter id="ds"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><circle cx="24" cy="26" r="13" fill="url(#cs)" filter="url(#ds)"/><ellipse cx="19" cy="21" rx="5" ry="3" fill="#fff" opacity=".45"/></svg>',
        'partly_cloudy' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="cs2" cx="30%" cy="25%" r="65%"><stop offset="0%" stop-color="#FEF9C3"/><stop offset="100%" stop-color="#FACC15"/></radialGradient><linearGradient id="cg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F8FAFC"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="ds2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><circle cx="14" cy="16" r="8" fill="url(#cs2)" filter="url(#ds2)"/><ellipse cx="18" cy="14" rx="3" ry="2" fill="#fff" opacity=".5"/><ellipse cx="26" cy="30" rx="14" ry="10" fill="url(#cg)" filter="url(#ds2)"/><ellipse cx="20" cy="28" rx="9" ry="7" fill="#E2E8F0"/><ellipse cx="32" cy="29" rx="8" ry="6" fill="#F1F5F9"/></svg>',
        'cloud' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="clg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F8FAFC"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="dsc"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><ellipse cx="24" cy="28" rx="16" ry="11" fill="url(#clg)" filter="url(#dsc)"/><ellipse cx="16" cy="26" rx="10" ry="8" fill="#E2E8F0"/><ellipse cx="32" cy="26" rx="9" ry="7" fill="#F1F5F9"/></svg>',
        'overcast' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ov" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E2E8F0"/><stop offset="100%" stop-color="#94A3B8"/></linearGradient><filter id="dso"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0f172a" flood-opacity=".2"/></filter></defs><ellipse cx="24" cy="28" rx="17" ry="12" fill="url(#ov)" filter="url(#dso)"/><ellipse cx="15" cy="26" rx="11" ry="9" fill="#CBD5E1"/><ellipse cx="34" cy="27" rx="10" ry="8" fill="#94A3B8"/></svg>',
        'rain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="rc" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F1F5F9"/><stop offset="100%" stop-color="#94A3B8"/></linearGradient><linearGradient id="rd" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#7DD3FC"/><stop offset="100%" stop-color="#38BDF8"/></linearGradient><filter id="dsr"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><ellipse cx="24" cy="22" rx="15" ry="10" fill="url(#rc)" filter="url(#dsr)"/><ellipse cx="17" cy="21" rx="9" ry="7" fill="#E2E8F0"/><path d="M16 34v8M24 32v9M32 34v7" stroke="url(#rd)" stroke-width="3.5" stroke-linecap="round" filter="url(#dsr)"/></svg>',
        'storm' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="stc" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E2E8F0"/><stop offset="100%" stop-color="#64748B"/></linearGradient><filter id="dst"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><ellipse cx="24" cy="22" rx="15" ry="10" fill="url(#stc)" filter="url(#dst)"/><path d="M22 28 L18 36h6l-3 8 10-12h-7l4-4z" fill="#FDE047" stroke="#EAB308" stroke-width="0.5" filter="url(#dst)"/></svg>',
        'snow' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="sn" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F8FAFC"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="dss"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="22" rx="15" ry="10" fill="url(#sn)" filter="url(#dss)"/><circle cx="18" cy="34" r="2.2" fill="#E0F2FE"/><circle cx="24" cy="36" r="2" fill="#BAE6FD"/><circle cx="30" cy="33" r="2.2" fill="#E0F2FE"/></svg>',
        'wind' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="wg" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#DDD6FE"/><stop offset="100%" stop-color="#A78BFA"/></linearGradient><filter id="dsw"><feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M8 20 Q22 14 38 20 Q22 26 8 20" fill="none" stroke="url(#wg)" stroke-width="5" stroke-linecap="round" filter="url(#dsw)"/><path d="M10 30 Q24 24 40 30 Q24 36 10 30" fill="none" stroke="url(#wg)" stroke-width="4" stroke-linecap="round" opacity=".85" filter="url(#dsw)"/></svg>',
        'droplet' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="dg" cx="35%" cy="25%" r="65%"><stop offset="0%" stop-color="#A7F3D0"/><stop offset="100%" stop-color="#34D399"/></radialGradient><filter id="dsd"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 10 C16 22 12 28 12 32a12 12 0 0 0 24 0c0-4-4-10-12-22z" fill="url(#dg)" filter="url(#dsd)"/><ellipse cx="20" cy="30" rx="4" ry="5" fill="#fff" opacity=".35"/></svg>',
        'pin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="pg" cx="35%" cy="20%" r="70%"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#D97706"/></radialGradient><filter id="dsp"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><path d="M24 8c-6 0-10 4.5-10 10 0 8 10 22 10 22s10-14 10-22c0-5.5-4-10-10-10z" fill="url(#pg)" filter="url(#dsp)"/><circle cx="24" cy="18" r="4" fill="#fff" opacity=".5"/></svg>',
        'sprout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="sg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#86EFAC"/><stop offset="100%" stop-color="#22C55E"/></linearGradient><filter id="dss2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M24 38v-8" stroke="#A3E635" stroke-width="4" stroke-linecap="round" filter="url(#dss2)"/><path d="M24 30 Q14 22 18 12 Q24 18 24 30" fill="url(#sg)" filter="url(#dss2)"/><path d="M24 30 Q34 22 30 12 Q24 18 24 30" fill="url(#sg)" opacity=".9" filter="url(#dss2)"/></svg>',
        'gauge' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="gg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#FEF3C7"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsg"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M10 32 A16 16 0 0 1 38 32" fill="none" stroke="#E2E8F0" stroke-width="5" stroke-linecap="round"/><path d="M10 32 A16 16 0 0 1 28 18" fill="none" stroke="url(#gg)" stroke-width="5" stroke-linecap="round" filter="url(#dsg)"/><circle cx="24" cy="32" r="3" fill="#F59E0B" filter="url(#dsg)"/></svg>',
        'brain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="bg" cx="30%" cy="30%" r="65%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="100%" stop-color="#A78BFA"/></radialGradient><filter id="dsb"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M18 14c-4 0-7 3-7 7 0 2 1 4 2 5-1 1-2 3-2 5 0 5 4 9 9 9h10c5 0 9-4 9-9 0-2-1-4-2-5 1-1 2-3 2-5 0-4-3-7-7-7-1-3-4-5-8-5s-7 2-8 5z" fill="url(#bg)" filter="url(#dsb)"/></svg>',
        'alert' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsa"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 8 L40 38H8L24 8z" fill="url(#ag)" filter="url(#dsa)"/><path d="M24 16v12M24 32v2" stroke="#92400E" stroke-width="2.5" stroke-linecap="round"/></svg>',
        'clock' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ck" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F1F5F9"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="dscl"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><circle cx="24" cy="24" r="14" fill="url(#ck)" filter="url(#dscl)"/><path d="M24 14v10l6 4" stroke="#475569" stroke-width="2.5" stroke-linecap="round" fill="none"/></svg>',
        'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="cal" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#FBBF24"/></linearGradient><filter id="dscal"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><rect x="10" y="14" width="28" height="26" rx="4" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="2" filter="url(#dscal)"/><rect x="10" y="14" width="28" height="9" rx="4" fill="url(#cal)"/><path d="M16 10v8M32 10v8" stroke="#D97706" stroke-width="3" stroke-linecap="round"/></svg>',
        'grid' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsgr"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="8" width="14" height="14" rx="4" fill="#E0E7FF" filter="url(#dsgr)"/><rect x="26" y="8" width="14" height="14" rx="4" fill="#FEF3C7" filter="url(#dsgr)"/><rect x="8" y="26" width="14" height="14" rx="4" fill="#D1FAE5" filter="url(#dsgr)"/><rect x="26" y="26" width="14" height="14" rx="4" fill="#EDE9FE" filter="url(#dsgr)"/></svg>',
        'chart' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsch"><feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="28" width="8" height="12" rx="2" fill="#86EFAC" filter="url(#dsch)"/><rect x="20" y="18" width="8" height="22" rx="2" fill="#A78BFA" filter="url(#dsch)"/><rect x="32" y="22" width="8" height="18" rx="2" fill="#FCD34D" filter="url(#dsch)"/></svg>',
        'dashboard' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsdb"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="8" width="30" height="14" rx="4" fill="#C4B5FD" filter="url(#dsdb)"/><rect x="8" y="26" width="13" height="14" rx="4" fill="#FDE68A" filter="url(#dsdb)"/><rect x="25" y="26" width="13" height="14" rx="4" fill="#A7F3D0" filter="url(#dsdb)"/></svg>',
        'thermo' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="tg" x1="0" y1="1" x2="0" y2="0"><stop offset="0%" stop-color="#FCA5A5"/><stop offset="100%" stop-color="#FEF08A"/></linearGradient><filter id="dsth"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><rect x="20" y="10" width="8" height="22" rx="4" fill="#F1F5F9" filter="url(#dsth)"/><circle cx="24" cy="34" r="8" fill="url(#tg)" filter="url(#dsth)"/></svg>',
        'eye' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="eg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E0E7FF"/><stop offset="100%" stop-color="#A5B4FC"/></linearGradient><filter id="dse"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="24" rx="18" ry="11" fill="url(#eg)" filter="url(#dse)"/><circle cx="24" cy="24" r="7" fill="#F8FAFC"/><circle cx="24" cy="24" r="4" fill="#6366F1"/></svg>',
        'moon' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="mg" cx="28%" cy="22%" r="75%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="55%" stop-color="#A78BFA"/><stop offset="100%" stop-color="#6D28D9"/></radialGradient><filter id="dsm"><feDropShadow dx="0" dy="2" stdDeviation="2.5" flood-color="#0f172a" flood-opacity=".22"/></filter></defs><circle cx="22" cy="24" r="14" fill="url(#mg)" filter="url(#dsm)"/><circle cx="33" cy="20" r="11" fill="#ffffff"/></svg>',
        'wifi_off' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dswf"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><path d="M8 38 L40 10" stroke="#CBD5E1" stroke-width="3" stroke-linecap="round"/><path d="M12 28 Q24 18 36 28" stroke="#E2E8F0" stroke-width="3" fill="none" stroke-linecap="round" filter="url(#dswf)"/></svg>',
    ];

    $wImg = static function (string $key) use ($clay, $clayDataUri): string {
        $svg = $clay[$key] ?? $clay['cloud'];

        return $clayDataUri($svg);
    };

    $wWeatherKey = static function (int $conditionId): string {
        return match (true) {
            $conditionId >= 200 && $conditionId < 300 => 'storm',
            $conditionId >= 300 && $conditionId < 600 => 'rain',
            $conditionId >= 600 && $conditionId < 700 => 'snow',
            $conditionId === 800 => 'sun',
            $conditionId === 801 => 'partly_cloudy',
            $conditionId === 802 || $conditionId === 803 => 'cloud',
            $conditionId === 804 => 'overcast',
            default => 'cloud',
        };
    };
@endphp
@extends('layouts.user')

@section('title', 'Weather Details – AGRIGUARD')

@push('head')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('body-class', 'dashboard-page weather-page min-h-screen bg-[#f3f8fc]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell py-4 sm:py-6 pb-24">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            @php
                $weatherReco = is_array($recommendation ?? null) ? $recommendation : [];
                $whCondId = is_array($weather ?? null)
                    ? (int) ($weather['condition']['id'] ?? 800)
                    : 800;
                $weatherHeroEmoji = \App\Http\Controllers\WeatherDetailsController::simpleWeatherEmoji($whCondId);
                $weatherHeroLabel = is_array($weather ?? null) && isset($weather['simple_label']) && $weather['simple_label'] !== ''
                    ? (string) $weather['simple_label']
                    : \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($whCondId);
            @endphp
            <header class="dashboard-hero weather-page-hero ag-card" aria-labelledby="weather-page-hero-heading">
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
                                <i data-lucide="cloud-sun" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="weather-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">Weather</span>
                                    <span class="dashboard-hero__title-emoji" aria-hidden="true">{{ $weatherHeroEmoji }}</span>
                                </h1>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill weather-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="map-pin" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $headerLocation }}</span>
                            </span>
                            <span class="dashboard-hero__pill weather-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
                            </span>
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card weather-page-hero__status-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow" aria-hidden="true"></span>
                            <span class="dashboard-hero__weather-ring" aria-hidden="true">
                                <i data-lucide="cloud-sun" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="cloud-sun" class="dashboard-hero__lucide dashboard-hero__lucide--sm" aria-hidden="true"></i>
                                    Live weather
                                </span>
                                <span class="dashboard-hero__weather-temp weather-page-hero__status-value">{{ $weatherHeroLabel ?: 'Clear' }}</span>
                                <span class="dashboard-hero__weather-desc">Live conditions</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            @php
                $todayPlan = is_array($weatherReco['today_plan'] ?? null) ? $weatherReco['today_plan'] : [];
                $wAiStatus = strtolower((string) ($weatherReco['ai_status'] ?? 'failed'));
                $wAiError = trim((string) ($weatherReco['ai_error'] ?? ''));
                $wAiAdvisoryReady = $wAiStatus === 'success';
                $wAiUnavailableMsg = 'AI advisory temporarily unavailable.';
                $wBlockedMsg = $wAiStatus === 'missing_context' && $wAiError !== ''
                    ? $wAiError
                    : ($wAiStatus === 'missing_context' ? 'Please update your crop type and farming stage in Farm Settings to receive AI advisory from Together AI.' : $wAiUnavailableMsg);
                $wSmartAction = $wAiAdvisoryReady ? trim((string) ($weatherReco['main_recommendation'] ?? '')) : $wBlockedMsg;
                if (strcasecmp($wSmartAction, 'Prepare for the upcoming rain to protect newly planted rice.') === 0) {
                    $wSmartAction = 'Monitor field conditions today and prepare your crop protection plan before the next rainfall.';
                }
            @endphp

            @if ($weather && isset($weather['temp']))
                @php
                    $condId = (int) ($weather['condition']['id'] ?? 800);
                    $condLabel = $weather['simple_label'] ?? \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($condId);
                    $snapshotSummary = $summary_message ?? 'Weather looks stable. Low chance of rain.';
                @endphp
                <article class="ag-card weather-snapshot overflow-hidden rounded-2xl border border-sky-200 bg-sky-50/85 p-3 shadow-sm sm:p-3.5" aria-label="Current weather snapshot">
                    <div class="flex items-start justify-between gap-2.5">
                        <div class="min-w-0">
                            <p class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-[11px] font-bold uppercase tracking-[0.12em] text-slate-700 transition-all duration-300 hover:tracking-[0.14em] hover:text-slate-900">
                                <i data-lucide="cloud-sun" class="h-3.5 w-3.5 text-sky-600"></i>
                                Current weather snapshot
                            </p>
                            <p class="mt-1.5 text-2xl font-extrabold leading-none text-slate-900 sm:text-3xl">{{ round((float) $weather['temp']) }}°C</p>
                            <p class="mt-1 text-xs font-medium text-slate-600 sm:text-sm">{{ $condLabel }}</p>
                        </div>
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 sm:h-11 sm:w-11" aria-hidden="true">
                            <img src="{{ $wImg($wWeatherKey((int) ($weather['condition']['id'] ?? 800))) }}" alt="" class="weather-clay-ic weather-clay-ic--hero" width="32" height="32" decoding="async">
                        </span>
                    </div>
                    <div class="mt-2.5 grid grid-cols-3 gap-1.5 sm:gap-2">
                        <article class="rounded-xl border border-sky-100 bg-sky-50 px-2 py-1.5">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Rain %</p>
                            <p class="mt-0.5 text-xs font-semibold text-slate-900 sm:text-sm">{{ $rainStatValue }}</p>
                        </article>
                        <article class="rounded-xl border border-emerald-100 bg-emerald-50 px-2 py-1.5">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Humidity</p>
                            <p class="mt-0.5 text-xs font-semibold text-slate-900 sm:text-sm">{{ is_numeric($weather['humidity'] ?? null) ? ((int) round((float) $weather['humidity'])) . '%' : '—' }}</p>
                        </article>
                        <article class="rounded-xl border border-violet-100 bg-violet-50 px-2 py-1.5">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-500">Wind</p>
                            <p class="mt-0.5 text-xs font-semibold text-slate-900 sm:text-sm">{{ is_numeric($weather['wind_speed'] ?? null) ? round((float) $weather['wind_speed'], 1) . ' km/h' : '—' }}</p>
                        </article>
                    </div>
                </article>
            @else
                <article class="ag-card p-4 text-center weather-page__empty">
                    <img src="{{ $wImg('wifi_off') }}" alt="" class="weather-clay-ic weather-clay-ic--lg mx-auto opacity-90" width="48" height="48" decoding="async">
                    <p class="mt-3 text-sm font-semibold text-slate-600">Weather data unavailable</p>
                </article>
            @endif

            <article class="ag-card dash-smart weather-page__smart rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5" aria-label="AI smart advisory">
                <div class="dash-smart__debug">
                    <p class="text-xs font-semibold text-slate-700">
                        @if ($wAiAdvisoryReady)
                            <span class="text-emerald-700">AI Smart Advisory: Active</span>
                        @elseif ($wAiStatus === 'missing_context')
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
                    <p class="dash-smart__action">{{ $wSmartAction }}</p>
                </div>
            </article>

            <section class="ag-card rounded-3xl border border-slate-200 bg-slate-50/90 p-4 sm:p-5 shadow-sm" aria-label="Today's plan">
                <h2 class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                    <i data-lucide="calendar-check-2" class="h-4 w-4 text-amber-600"></i>
                    Today&rsquo;s Plan
                </h2>
                <div class="mt-3 space-y-3">
                    <article class="flex gap-3 rounded-2xl border border-amber-100/80 bg-amber-50 px-3 py-3">
                        <img src="{{ $wImg('sun') }}" alt="" class="weather-clay-ic weather-clay-ic--plan mt-0.5" width="22" height="22" decoding="async">
                        <div><p class="text-sm font-semibold text-slate-800">Morning</p><p class="text-sm text-slate-600">{{ $wAiAdvisoryReady ? trim((string) ($todayPlan['morning'] ?? '')) : $wBlockedMsg }}</p></div>
                    </article>
                    <article class="flex gap-3 rounded-2xl border border-sky-100/80 bg-sky-50 px-3 py-3">
                        <img src="{{ $wImg('partly_cloudy') }}" alt="" class="weather-clay-ic weather-clay-ic--plan mt-0.5" width="22" height="22" decoding="async">
                        <div><p class="text-sm font-semibold text-slate-800">Afternoon</p><p class="text-sm text-slate-600">{{ $wAiAdvisoryReady ? trim((string) ($todayPlan['afternoon'] ?? '')) : $wBlockedMsg }}</p></div>
                    </article>
                    <article class="flex gap-3 rounded-2xl border border-violet-100/80 bg-violet-50 px-3 py-3">
                        <img src="{{ $wImg('moon') }}" alt="" class="weather-clay-ic weather-clay-ic--plan mt-0.5" width="22" height="22" decoding="async">
                        <div><p class="text-sm font-semibold text-slate-800">Evening</p><p class="text-sm text-slate-600">{{ $wAiAdvisoryReady ? trim((string) ($todayPlan['evening'] ?? '')) : $wBlockedMsg }}</p></div>
                    </article>
                </div>
            </section>

            <section class="grid gap-3 sm:grid-cols-2" aria-label="Avoid and water strategy">
                <div class="ag-card rounded-3xl border border-rose-100 bg-rose-50/70 p-4">
                    <div class="dash-split__card dash-split__card--avoid">
                    <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <img src="{{ $wImg('alert') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Avoid
                        </div>
                        <p class="dash-split__body">{{ $wAiAdvisoryReady ? trim((string) ($weatherReco['avoid'] ?? '')) : $wBlockedMsg }}</p>
                    </div>
                </div>
                <div class="ag-card rounded-3xl border border-cyan-100 bg-cyan-50/70 p-4">
                    <div class="dash-split__card dash-split__card--water">
                    <div class="dash-split__head inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <img src="{{ $wImg('droplet') }}" class="dash-split__ic-img" width="15" height="15" alt="">
                            Water
                        </div>
                        <p class="dash-split__body">{{ $wAiAdvisoryReady ? trim((string) ($weatherReco['water_strategy'] ?? '')) : $wBlockedMsg }}</p>
                    </div>
                </div>
            </section>

            @if (!empty($forecast))
                <section class="ag-card weather-page__fc-card weather-page__fc-card--emph bg-slate-50/90" aria-label="Five day forecast">
                    <div class="weather-page__fc-head rounded-2xl px-3 py-2">
                        <img src="{{ $wImg('calendar') }}" alt="" class="weather-clay-ic weather-clay-ic--fc-head" width="28" height="28" decoding="async">
                        <div>
                            <h2 class="weather-page__fc-title inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">5-Day Forecast</h2>
                        </div>
                    </div>
                    <div class="weather-page__fc-list" role="list">
                        @foreach (array_slice($forecast, 0, 5) as $day)
                            @php
                                $conditionId = (int) ($day['condition']['id'] ?? 800);
                                $wk = $wWeatherKey($conditionId);
                                $dayCondition = \App\Http\Controllers\WeatherDetailsController::simpleWeatherLabel($conditionId);
                                $dayLabel = $day['day_name'] ?? \Carbon\Carbon::parse($day['date'] ?? now())->format('D');
                                $dayRainChance = isset($day['pop']) && is_numeric($day['pop']) ? (int) round((float) $day['pop']) . '%' : '0%';
                                $dayRainMm = isset($day['rain_mm']) && is_numeric($day['rain_mm']) ? round((float) $day['rain_mm'], 1) . ' mm' : 'N/A';
                                $dayWind = isset($day['wind_speed']) && is_numeric($day['wind_speed']) ? round((float) $day['wind_speed'], 1) . ' km/h' : 'N/A';
                                $toneClass = ['weather-page__fc-row--slate', 'weather-page__fc-row--amber', 'weather-page__fc-row--mint', 'weather-page__fc-row--violet'][$loop->index % 4];
                            @endphp
                            <article class="weather-page__fc-row {{ $toneClass }}" role="listitem">
                                <p class="weather-page__fc-day">{{ $dayLabel }}</p>
                                <span class="weather-page__fc-icon" aria-hidden="true">
                                    <img src="{{ $wImg($wk) }}" alt="" class="weather-clay-ic weather-clay-ic--fc-row" width="26" height="26" decoding="async">
                                </span>
                                <div class="weather-page__fc-meta">
                                    <p class="weather-page__fc-condition">{{ $dayCondition }}</p>
                                    <p class="weather-page__fc-temp-line">
                                        @if (isset($day['temp_max']) && isset($day['temp_min']))
                                            <span class="weather-page__fc-hi">{{ round((float) $day['temp_max']) }}°</span>
                                            <span class="weather-page__fc-sep">/</span>
                                            <span class="weather-page__fc-lo">{{ round((float) $day['temp_min']) }}°</span>
                                        @else
                                            —
                                        @endif
                                    </p>
                                    <p class="weather-page__fc-extra">Rainfall {{ $dayRainMm }} • Wind {{ $dayWind }}</p>
                                </div>
                                <p class="weather-page__fc-rain">Rain {{ $dayRainChance }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="ag-card weather-impact-card border border-slate-200 bg-white p-4 sm:p-5" aria-label="3-day impact and advisory">
                <div class="weather-impact-min__head">
                    <h2 class="weather-impact-min__title inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                        <i data-lucide="triangle-alert" class="h-4 w-4 text-amber-600"></i>
                        3-Day Impact
                    </h2>
                    @if ($impactSummary !== '')
                        <span class="weather-impact__summary-badge {{ $impactToneClass }}">{{ $impactSummary }}</span>
                    @endif
                </div>

                <div class="weather-impact-min__kpis">
                    <article class="weather-impact-min__kpi weather-impact-min__kpi--amber">
                        <p class="weather-impact-min__kpi-label">Crop loss</p>
                        <p class="weather-impact-min__kpi-value">{{ $snapshotCropLoss }}</p>
                    </article>
                    <article class="weather-impact-min__kpi weather-impact-min__kpi--sky">
                        <p class="weather-impact-min__kpi-label">Effect</p>
                        <p class="weather-impact-min__kpi-value weather-impact-min__kpi-value--text">{{ $snapshotEffect }}</p>
                    </article>
                    <article class="weather-impact-min__kpi weather-impact-min__kpi--rose">
                        <p class="weather-impact-min__kpi-label">Flood risk</p>
                        <p class="weather-impact-min__kpi-value">{{ $snapshotFlood }}</p>
                    </article>
                </div>

                <div class="weather-impact-min__grid weather-impact-min__grid--minimal">
                    <article class="weather-impact-min__block weather-impact-min__block--effect">
                        <h3 class="weather-impact-min__block-title">Effects</h3>
                        @if (!empty($impactDetails))
                            <ul class="weather-impact-min__list">
                                @foreach ($impactDetails as $effect)
                                    <li>{{ $effect }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="weather-impact-min__fallback">No effect data</p>
                        @endif
                    </article>

                    <article class="weather-impact-min__block weather-impact-min__block--advice">
                        <h3 class="weather-impact-min__block-title">Advice</h3>
                        @if (!empty($impactAdvice))
                            <ul class="weather-impact-min__list">
                                @foreach ($impactAdvice as $adviceItem)
                                    <li>{{ $adviceItem }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p class="weather-impact-min__fallback">No advice yet</p>
                        @endif
                    </article>
                </div>
            </section>

            @if (!empty($hourly_forecast))
                <section class="ag-card border border-slate-200 bg-slate-100/80 p-4 weather-page__hourly-card">
                    <h2 class="farm-dash__title weather-page__section-kicker inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-sm font-extrabold uppercase tracking-[0.1em] text-slate-800 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                        <img src="{{ $wImg('clock') }}" alt="" class="weather-clay-ic weather-clay-ic--title" width="18" height="18" decoding="async">
                        Hourly
                    </h2>
                    <div class="weather-page__hourly-scroll">
                        @foreach ($hourly_forecast as $hourly)
                            @php
                                $hourlyCondition = (int) ($hourly['condition_id'] ?? 800);
                                $hk = $wWeatherKey($hourlyCondition);
                                $hourlyRain = is_numeric($hourly['pop'] ?? null) ? ((int) round((float) $hourly['pop'])) . '%' : '0%';
                            @endphp
                            <article class="weather-page__hourly-cell">
                                <p class="weather-page__hourly-time">{{ $hourly['time'] ?? '—' }}</p>
                                <img src="{{ $wImg($hk) }}" alt="" class="weather-clay-ic weather-clay-ic--fc mx-auto" width="22" height="22" decoding="async">
                                <p class="weather-page__hourly-temp">{{ isset($hourly['temp']) ? round((float) $hourly['temp']) . '°' : '—' }}</p>
                                <p class="weather-page__hourly-rain">Rain {{ $hourlyRain }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif


        </div>
    </section>
@endsection

