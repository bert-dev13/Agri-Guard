@php
    $clayDataUri = static function (string $svg): string {
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    };

    $clay = [
        'barn' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="bnr" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FCA5A5"/><stop offset="100%" stop-color="#EF4444"/></linearGradient><linearGradient id="bnw" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#FEF9C3"/><stop offset="100%" stop-color="#FDE68A"/></linearGradient><filter id="dsbn"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M24 6 L42 20v22H6V20L24 6z" fill="url(#bnr)" filter="url(#dsbn)"/><rect x="12" y="26" width="24" height="16" rx="2" fill="url(#bnw)" filter="url(#dsbn)"/><rect x="20" y="30" width="8" height="12" rx="1" fill="#D97706" filter="url(#dsbn)"/></svg>',
        'grain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="grg" cx="40%" cy="35%" r="65%"><stop offset="0%" stop-color="#FEF9C3"/><stop offset="100%" stop-color="#EAB308"/></radialGradient><filter id="dsgrn"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="30" rx="4" ry="14" fill="#A3E635" filter="url(#dsgrn)"/><ellipse cx="24" cy="16" rx="10" ry="8" fill="url(#grg)" filter="url(#dsgrn)"/><ellipse cx="18" cy="14" rx="3" ry="5" fill="#FDE047" opacity=".9" filter="url(#dsgrn)"/><ellipse cx="30" cy="14" rx="3" ry="5" fill="#FDE047" opacity=".9" filter="url(#dsgrn)"/></svg>',
        'leaf_stage' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="lfg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#4ADE80"/><stop offset="100%" stop-color="#16A34A"/></linearGradient><filter id="dslf"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M24 38V22" stroke="#86EFAC" stroke-width="4" stroke-linecap="round" filter="url(#dslf)"/><path d="M24 22 C8 22 8 8 24 6 C40 8 40 22 24 22z" fill="url(#lfg)" filter="url(#dslf)"/><ellipse cx="18" cy="16" rx="5" ry="3" fill="#fff" opacity=".35"/></svg>',
        'check' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="ckg" cx="30%" cy="30%" r="70%"><stop offset="0%" stop-color="#86EFAC"/><stop offset="100%" stop-color="#22C55E"/></radialGradient><filter id="dsck"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><circle cx="24" cy="24" r="15" fill="url(#ckg)" filter="url(#dsck)"/><path d="M16 24l6 6 12-14" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>',
        'bulb' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="blb" cx="35%" cy="28%" r="65%"><stop offset="0%" stop-color="#FEF9C3"/><stop offset="100%" stop-color="#FACC15"/></radialGradient><filter id="dsbl"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="20" rx="12" ry="14" fill="url(#blb)" filter="url(#dsbl)"/><rect x="18" y="32" width="12" height="8" rx="2" fill="#94A3B8" filter="url(#dsbl)"/><ellipse cx="20" cy="16" rx="4" ry="3" fill="#fff" opacity=".4"/></svg>',
        'pin' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="pg" cx="35%" cy="20%" r="70%"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#D97706"/></radialGradient><filter id="dsp"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".18"/></filter></defs><path d="M24 8c-6 0-10 4.5-10 10 0 8 10 22 10 22s10-14 10-22c0-5.5-4-10-10-10z" fill="url(#pg)" filter="url(#dsp)"/><circle cx="24" cy="18" r="4" fill="#fff" opacity=".5"/></svg>',
        'sprout' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="sg" x1="0" y1="1" x2="1" y2="0"><stop offset="0%" stop-color="#86EFAC"/><stop offset="100%" stop-color="#22C55E"/></linearGradient><filter id="dss2"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><path d="M24 38v-8" stroke="#A3E635" stroke-width="4" stroke-linecap="round" filter="url(#dss2)"/><path d="M24 30 Q14 22 18 12 Q24 18 24 30" fill="url(#sg)" filter="url(#dss2)"/><path d="M24 30 Q34 22 30 12 Q24 18 24 30" fill="url(#sg)" opacity=".9" filter="url(#dss2)"/></svg>',
        'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="cal" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#FBBF24"/></linearGradient><filter id="dscal"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><rect x="10" y="14" width="28" height="26" rx="4" fill="#F8FAFC" stroke="#E2E8F0" stroke-width="2" filter="url(#dscal)"/><rect x="10" y="14" width="28" height="9" rx="4" fill="url(#cal)"/><path d="M16 10v8M32 10v8" stroke="#D97706" stroke-width="3" stroke-linecap="round"/></svg>',
        'clock' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ck" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#F1F5F9"/><stop offset="100%" stop-color="#CBD5E1"/></linearGradient><filter id="dscl"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><circle cx="24" cy="24" r="14" fill="url(#ck)" filter="url(#dscl)"/><path d="M24 14v10l6 4" stroke="#475569" stroke-width="2.5" stroke-linecap="round" fill="none"/></svg>',
        'chart' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><filter id="dsch"><feDropShadow dx="0" dy="2" stdDeviation="1.5" flood-color="#0f172a" flood-opacity=".12"/></filter></defs><rect x="8" y="28" width="8" height="12" rx="2" fill="#86EFAC" filter="url(#dsch)"/><rect x="20" y="18" width="8" height="22" rx="2" fill="#A78BFA" filter="url(#dsch)"/><rect x="32" y="22" width="8" height="18" rx="2" fill="#FCD34D" filter="url(#dsch)"/></svg>',
        'brain' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><radialGradient id="bg" cx="30%" cy="30%" r="65%"><stop offset="0%" stop-color="#EDE9FE"/><stop offset="100%" stop-color="#A78BFA"/></radialGradient><filter id="dsb"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".15"/></filter></defs><path d="M18 14c-4 0-7 3-7 7 0 2 1 4 2 5-1 1-2 3-2 5 0 5 4 9 9 9h10c5 0 9-4 9-9 0-2-1-4-2-5 1-1 2-3 2-5 0-4-3-7-7-7-1-3-4-5-8-5s-7 2-8 5z" fill="url(#bg)" filter="url(#dsb)"/></svg>',
        'alert' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="ag" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient><filter id="dsa"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".16"/></filter></defs><path d="M24 8 L40 38H8L24 8z" fill="url(#ag)" filter="url(#dsa)"/><path d="M24 16v12M24 32v2" stroke="#92400E" stroke-width="2.5" stroke-linecap="round"/></svg>',
        'eye' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><defs><linearGradient id="eg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#E0E7FF"/><stop offset="100%" stop-color="#A5B4FC"/></linearGradient><filter id="dse"><feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#0f172a" flood-opacity=".14"/></filter></defs><ellipse cx="24" cy="24" rx="18" ry="11" fill="url(#eg)" filter="url(#dse)"/><circle cx="24" cy="24" r="7" fill="#F8FAFC"/><circle cx="24" cy="24" r="4" fill="#6366F1"/></svg>',
    ];

    $cpImg = static function (string $key) use ($clay, $clayDataUri): string {
        $svg = $clay[$key] ?? $clay['sprout'];

        return $clayDataUri($svg);
    };

    $cpBulletItems = static function (?string $text): array {
        $t = trim((string) $text);
        if ($t === '') {
            return [];
        }
        if (str_contains($t, "\n")) {
            $out = [];
            foreach (preg_split('/\r\n|\r|\n/', $t) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $line = preg_replace('/^[\-\*•]\s*/u', '', $line);
                $line = preg_replace('/^\d+\.\s*/', '', $line);
                $out[] = $line;
            }

            return $out;
        }
        if (str_contains($t, ';')) {
            return array_values(array_filter(array_map('trim', explode(';', $t))));
        }

        return [$t];
    };

    $showAiDebug = app()->environment('local') || (bool) config('app.debug');
    $cpAiOk = empty($recommendation_failed) && strtolower((string) ($recommendation['ai_status'] ?? '')) === 'success';
    $cpAiStatus = strtolower((string) ($recommendation['ai_status'] ?? 'failed'));
    $cpAiMsg = $cpAiOk
        ? null
        : ($cpAiStatus === 'missing_context' && trim((string) ($recommendation['ai_error'] ?? '')) !== ''
            ? trim((string) $recommendation['ai_error'])
            : 'AI advisory temporarily unavailable.');
    $cpAiError = trim((string) ($recommendation['ai_error'] ?? ''));
    $riskLevel = $cpAiOk ? (string) ($recommendation['risk_level'] ?? '') : '';
    $riskKey = strtolower($riskLevel);
    $adjustmentLabelClass = match (strtolower((string) ($timeline_adjustment_label ?? ''))) {
        'growth is slower than expected' => 'cp-status-pill--amber',
        'growing faster than typical for this season' => 'cp-status-pill--mint',
        default => 'cp-status-pill--slate',
    };
    $timelineItems = is_array($timeline ?? null) ? $timeline : [];
    $timelineCount = count($timelineItems);
    $currentIndex = 0;
    foreach ($timelineItems as $idx => $stageItem) {
        if (strtolower((string) ($stageItem['status'] ?? '')) === 'current') {
            $currentIndex = $idx;
            break;
        }
    }
    $progressPercent = $timelineCount > 1 ? (int) round(($currentIndex / ($timelineCount - 1)) * 100) : ($timelineCount === 1 ? 100 : 0);

    $mainAdvice = $cpAiOk ? trim((string) ($recommendation['main_advice'] ?? '')) : '';
    $whatToDo = $cpAiOk ? $cpBulletItems($recommendation['what_to_do'] ?? null) : [];
    $whatToWatch = $cpAiOk ? $cpBulletItems($recommendation['what_to_watch'] ?? null) : [];
    $whatToAvoid = $cpAiOk ? $cpBulletItems($recommendation['what_to_avoid'] ?? null) : [];
    $whyThisMatters = $cpAiOk ? trim((string) ($recommendation['why_this_matters'] ?? '')) : '';

    $smartActionLine = $cpAiOk
        ? (count($whatToDo) > 0 ? $whatToDo[0] : $mainAdvice)
        : '';
    $whatToDoRest = count($whatToDo) > 1 ? array_slice($whatToDo, 1) : [];

    $growthSpeed = $growth_speed ?? 'normal';
    $stageConfidence = $stage_confidence ?? ['label' => 'Medium', 'level' => 'medium', 'tooltip' => 'Based on weather data and standard growth patterns for your crop.'];
    $showRealityCheckCard = $show_reality_check_card ?? ($show_reality_check_form ?? true);
    $realityQuestionStage = $reality_question_stage ?? $current_stage_label;
    $trackerStages = [
        'planting' => ['emoji' => '🌱', 'label' => 'Planting'],
        'early_growth' => ['emoji' => '🌿', 'label' => 'Early Growth'],
        'vegetative' => ['emoji' => '🌾', 'label' => 'Vegetative'],
        'flowering' => ['emoji' => '🌼', 'label' => 'Flowering'],
        'harvest' => ['emoji' => '🧺', 'label' => 'Harvest'],
    ];
    $userStageKey = app(\App\Services\CropTimelineService::class)->normalizeStageKey((string) ($current_stage ?? 'planting'));

    $timelineStageEmoji = static function (string $stageName): string {
        $n = strtolower($stageName);
        if (str_contains($n, 'plant')) {
            return '🌱';
        }
        if (str_contains($n, 'early')) {
            return '🌿';
        }
        if (str_contains($n, 'vegetative') || str_contains($n, 'veget')) {
            return '🌾';
        }
        if (str_contains($n, 'growing') && ! str_contains($n, 'early')) {
            return '🌾';
        }
        if (str_contains($n, 'flower')) {
            return '🌼';
        }
        if (str_contains($n, 'harvest')) {
            return '🧺';
        }

        return '🌱';
    };

@endphp

@extends('layouts.user')

@section('title', 'Crop Progress - AGRIGUARD')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
@endpush

@section('body-class', 'dashboard-page crop-progress-page min-h-screen bg-[#EEF1F6]')

@section('main-class', 'pt-20')

@section('content')
    <section class="dashboard-shell py-4 sm:py-6 pb-24 cp-page-enter">
        <div class="dashboard-container max-w-3xl mx-auto px-4 sm:px-5 space-y-4 sm:space-y-5">
            @if (session('success'))
                <div class="cp-flash-success flex items-center gap-3" role="alert">
                    <img src="{{ $cpImg('check') }}" alt="" class="weather-clay-ic weather-clay-ic--inline shrink-0" width="20" height="20" decoding="async">
                    <span class="cp-flash-success__text">{{ session('success') }}</span>
                </div>
            @endif

            {{-- 1. Compact header --}}
            <header class="dashboard-hero crop-progress-page-hero ag-card" aria-labelledby="crop-progress-page-hero-heading">
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
                            <span class="dashboard-hero__greet-badge" aria-hidden="true">
                                <span class="dashboard-hero__greet-badge-glow"></span>
                                <i data-lucide="sprout" class="dashboard-hero__lucide dashboard-hero__lucide--greet"></i>
                            </span>
                            <div class="dashboard-hero__title-stack">
                                <h1 id="crop-progress-page-hero-heading" class="dashboard-hero__title">
                                    <span class="dashboard-hero__title-line">Crop Progress</span>
                                </h1>
                                <p class="dashboard-hero__subtitle crop-progress-page-hero__farm">
                                    <span class="dashboard-hero__subtitle-ic" aria-hidden="true">
                                        <i data-lucide="tractor" class="dashboard-hero__lucide dashboard-hero__lucide--xs"></i>
                                    </span>
                                    <span>{{ $farm_name }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill crop-progress-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="leaf" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $user->crop_type ?: 'Crop not set' }} · {{ $current_stage_label }}</span>
                            </span>
                            <span class="dashboard-hero__pill crop-progress-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="calendar-days" class="dashboard-hero__lucide"></i>
                                </span>
                                <time class="dashboard-hero__pill-text" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
                            </span>
                            @if (!empty($planting_day_line))
                                <span class="dashboard-hero__pill crop-progress-page-hero__pill">
                                    <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                        <i data-lucide="calendar-range" class="dashboard-hero__lucide"></i>
                                    </span>
                                    <span class="dashboard-hero__pill-text text-left">{{ $planting_day_line }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="dashboard-hero__aside">
                        <span class="dashboard-hero__weather-card crop-progress-page-hero__stage-card" aria-hidden="true">
                            <span class="dashboard-hero__weather-glow"></span>
                            <span class="dashboard-hero__weather-ring">
                                <img src="{{ $cpImg('leaf_stage') }}" alt="" class="weather-clay-ic weather-clay-ic--hero" width="36" height="36" decoding="async">
                            </span>
                            <span class="dashboard-hero__weather-body">
                                <span class="dashboard-hero__weather-label">
                                    <i data-lucide="sprout" class="dashboard-hero__lucide dashboard-hero__lucide--sm" aria-hidden="true"></i>
                                    Stage
                                </span>
                                <span class="dashboard-hero__weather-temp">{{ $current_stage_label }}</span>
                                <span class="dashboard-hero__weather-desc">Growth lifecycle</span>
                            </span>
                        </span>
                    </div>
                </div>
            </header>

            @if (($has_planting_date ?? false) && ($manual_stage_label ?? '') !== '' && ($manual_stage_label ?? '') !== ($current_stage_label ?? ''))
                <div class="rounded-2xl border border-amber-200 bg-amber-50/90 px-4 py-3 text-sm text-amber-950" role="status">
                    <strong>Field note:</strong> Your profile stage is <strong>{{ $manual_stage_label }}</strong>; the calendar from your planting date suggests <strong>{{ $current_stage_label }}</strong>. Update the stage on this page if the field matches your profile.
                </div>
            @endif

            {{-- 2. Stage snapshot (calendar stage + planting + progress) --}}
            <section class="ag-card cp-stage-highlight-card" aria-label="Current crop stage highlight">
                <div class="cp-stage-highlight-card__head">
                    <div class="cp-stage-highlight-card__lead">
                        <span class="cp-stage-highlight-card__lead-ic" aria-hidden="true">
                            <img src="{{ $cpImg('leaf_stage') }}" alt="" class="cp-stage-highlight-card__lead-img" width="44" height="44" decoding="async">
                        </span>
                        <div class="cp-stage-highlight-card__titles">
                            <p class="cp-stage-highlight-card__eyebrow">Stage</p>
                            <p class="cp-stage-highlight-card__stage-name">{{ $current_stage_label }}</p>
                            <p class="cp-stage-highlight-card__tagline">Growth lifecycle</p>
                        </div>
                    </div>
                    <span class="cp-stage-highlight-card__live-badge">
                        <img src="{{ $cpImg('sprout') }}" alt="" class="cp-stage-highlight-card__live-ic" width="16" height="16" decoding="async">
                        Active stage
                    </span>
                </div>

                <div class="cp-stage-highlight-card__planting-row">
                    <img src="{{ $cpImg('calendar') }}" alt="" class="cp-stage-highlight-card__planting-hero-ic" width="32" height="32" decoding="async" aria-hidden="true">
                    <div class="cp-stage-highlight-card__planting-copy">
                        <p class="cp-stage-highlight-card__planting-label">Planting date</p>
                        @if (($has_planting_date ?? false) && ! empty($planting_date_formatted))
                            <div class="cp-stage-highlight-card__date-line">
                                <time class="cp-stage-highlight-card__planting-date" datetime="{{ $user->planting_date?->format('Y-m-d') }}">{{ $planting_date_formatted }}</time>
                                <img src="{{ $cpImg('pin') }}" alt="" class="cp-stage-highlight-card__date-ic cp-stage-highlight-card__date-ic--end" width="18" height="18" decoding="async" aria-hidden="true">
                            </div>
                        @else
                            <div class="cp-stage-highlight-card__date-line">
                                <img src="{{ $cpImg('alert') }}" alt="" class="cp-stage-highlight-card__date-ic" width="18" height="18" decoding="async" aria-hidden="true">
                                <span class="cp-stage-highlight-card__planting-date cp-stage-highlight-card__planting-date--empty">Not set — add it in Farm Settings</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="cp-stage-highlight-card__progress-block">
                    <div class="cp-stage-highlight-card__progress-head">
                        <span class="cp-stage-highlight-card__progress-label">Progress</span>
                        <span class="cp-stage-highlight-card__progress-pct">{{ (int) $progressPercent }}%</span>
                    </div>
                    <div
                        class="cp-stage-highlight-card__track"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ (int) $progressPercent }}"
                        aria-label="Crop timeline progress"
                    >
                        <div class="cp-stage-highlight-card__fill" @style(['width' => ((int) $progressPercent).'%'])></div>
                    </div>
                </div>

                <div class="cp-stage-highlight-card__stats">
                    <article class="cp-stage-highlight-card__stat">
                        <p class="cp-stage-highlight-card__stat-label">Growth speed</p>
                        <p class="cp-stage-highlight-card__stat-value">{{ ucfirst($growthSpeed) }}</p>
                    </article>
                    <article class="cp-stage-highlight-card__stat">
                        <p class="cp-stage-highlight-card__stat-label">Confidence</p>
                        <p class="cp-stage-highlight-card__stat-value">{{ $stageConfidence['label'] ?? 'Medium' }}</p>
                    </article>
                    <article class="cp-stage-highlight-card__stat">
                        <p class="cp-stage-highlight-card__stat-label">Timeline</p>
                        <p class="cp-stage-highlight-card__stat-value">{{ (int) $progressPercent }}%</p>
                        <p class="cp-stage-highlight-card__stat-hint">Along growth stages</p>
                    </article>
                </div>
            </section>

            {{-- 2. AI Smart Advisory (hero) --}}
            <section class="ag-card dash-smart cp-smart-panel cp-smart-hero" aria-label="Smart advice for this stage">
                <div class="cp-smart-hero__status-row dash-smart__debug">
                    <p class="text-xs font-semibold text-slate-700">
                        @if ($cpAiStatus === 'success' && empty($recommendation_failed))
                            <span class="text-emerald-700">AI Smart Advisory: Active</span>
                        @else
                            <span class="text-rose-700">AI Smart Advisory: Unavailable</span>
                        @endif
                    </p>
                    @if ($cpAiStatus !== 'success' && $cpAiError !== '')
                        <p class="text-xs text-slate-600 mt-1">Error: {{ $cpAiError }}</p>
                    @endif
                </div>

                <header class="cp-smart-head cp-smart-hero__head">
                    <div class="cp-smart-head__left">
                        <h2 class="cp-smart-title">
                            <img src="{{ $cpImg('brain') }}" alt="" class="weather-clay-ic weather-clay-ic--title" width="18" height="18" decoding="async">
                            AI Smart Advisory
                        </h2>
                        <p class="cp-smart-sub">Tailored to your current growth stage</p>
                    </div>
                    <div class="cp-smart-badges">
                        @if ($cpAiOk && $riskLevel !== '')
                            <span class="dash-smart__badge dash-smart__badge--risk-{{ $riskKey === 'high' ? 'high' : ($riskKey === 'low' ? 'low' : 'mid') }}">
                                {{ $riskLevel }} risk
                            </span>
                        @endif
                    </div>
                </header>

                <div class="cp-smart-action-callout">
                    <span class="cp-smart-action-callout__chip" aria-hidden="true">🔥 Smart action</span>
                    <p class="cp-smart-action-callout__text">
                        @if ($cpAiOk)
                            {{ $smartActionLine }}
                        @else
                            {{ $cpAiMsg }}
                        @endif
                    </p>
                </div>

                <div class="cp-smart-summary cp-smart-hero__summary">
                    <div class="cp-smart-summary__head">
                        <img src="{{ $cpImg('bulb') }}" alt="" class="weather-clay-ic weather-clay-ic--plan" width="22" height="22" decoding="async">
                        <h3 class="cp-smart-block-title">Advice summary</h3>
                    </div>
                    <p class="cp-smart-summary__text">
                        @if ($cpAiOk)
                            {{ $mainAdvice }}
                        @else
                            {{ $cpAiMsg }}
                        @endif
                    </p>
                </div>

                <div class="cp-smart-grid">
                    <article class="cp-smart-block cp-smart-block--do">
                        <div class="cp-smart-block__head">
                            <img src="{{ $cpImg('sprout') }}" alt="" class="weather-clay-ic weather-clay-ic--inline" width="18" height="18" decoding="async">
                            <h3 class="cp-smart-block-title">What to do</h3>
                        </div>
                        <ul class="cp-advice-list">
                            @if ($cpAiOk)
                                @forelse ($whatToDoRest as $line)
                                    <li>{{ $line }}</li>
                                @empty
                                @endforelse
                            @else
                                <li class="text-slate-500">{{ $cpAiMsg }}</li>
                            @endif
                        </ul>
                    </article>
                    <article class="cp-smart-block cp-smart-block--watch">
                        <div class="cp-smart-block__head">
                            <img src="{{ $cpImg('eye') }}" alt="" class="weather-clay-ic weather-clay-ic--inline" width="18" height="18" decoding="async">
                            <h3 class="cp-smart-block-title">What to watch</h3>
                        </div>
                        <ul class="cp-advice-list">
                            @if ($cpAiOk)
                                @forelse ($whatToWatch as $line)
                                    <li>{{ $line }}</li>
                                @empty
                                @endforelse
                            @else
                                <li class="text-slate-500">{{ $cpAiMsg }}</li>
                            @endif
                        </ul>
                    </article>
                    <article class="cp-smart-block cp-smart-block--avoid">
                        <div class="cp-smart-block__head">
                            <img src="{{ $cpImg('alert') }}" alt="" class="weather-clay-ic weather-clay-ic--inline" width="18" height="18" decoding="async">
                            <h3 class="cp-smart-block-title">What to avoid</h3>
                        </div>
                        <ul class="cp-advice-list">
                            @if ($cpAiOk)
                                @forelse ($whatToAvoid as $line)
                                    <li>{{ $line }}</li>
                                @empty
                                @endforelse
                            @else
                                <li class="text-slate-500">{{ $cpAiMsg }}</li>
                            @endif
                        </ul>
                    </article>
                    <article class="cp-smart-block cp-smart-block--why">
                        <div class="cp-smart-block__head">
                            <img src="{{ $cpImg('bulb') }}" alt="" class="weather-clay-ic weather-clay-ic--inline" width="18" height="18" decoding="async">
                            <h3 class="cp-smart-block-title">Why this matters</h3>
                        </div>
                        <p class="cp-smart-why-text">
                            @if ($cpAiOk)
                                {{ $whyThisMatters }}
                            @else
                                {{ $cpAiMsg }}
                            @endif
                        </p>
                    </article>
                </div>

                @if ($showAiDebug && ! empty($recommendation['ai_error'] ?? ''))
                    <p class="cp-smart-debug text-xs text-slate-600 mt-3">{{ $recommendation['ai_error'] }}</p>
                @endif
            </section>

            {{-- 4. Growth timeline (visual stepper) --}}
            <section class="ag-card cp-tracker-card" aria-label="Growth timeline">
                <h2 class="cp-section-title">Growth timeline</h2>
                <p class="cp-section-lead cp-tracker-card__lead">Stages for your crop — current step is highlighted.</p>
                <div class="cp-tracker" role="list">
                    @php
                        $trackerKeys = array_keys($trackerStages);
                        $userIdx = array_search($userStageKey, $trackerKeys, true);
                        $userIdx = $userIdx === false ? 0 : (int) $userIdx;
                    @endphp
                    @foreach ($trackerStages as $key => $meta)
                        @php
                            $kIdx = array_search($key, $trackerKeys, true);
                            $kIdx = $kIdx === false ? 0 : (int) $kIdx;
                            $isHere = $userStageKey === $key;
                            $isPast = $kIdx < $userIdx;
                        @endphp
                        <div class="cp-tracker-node {{ $isHere ? 'cp-tracker-node--here' : '' }} {{ $isPast ? 'cp-tracker-node--past' : '' }}" role="listitem">
                            <span class="cp-tracker-emoji" aria-hidden="true">{{ $meta['emoji'] }}</span>
                            <span class="cp-tracker-label">{{ $meta['label'] }}</span>
                            @if ($isHere)
                                <span class="cp-tracker-here">You are here</span>
                            @endif
                        </div>
                        @if (!$loop->last)
                            <span class="cp-tracker-connector" aria-hidden="true"></span>
                        @endif
                    @endforeach
                </div>
            </section>

            @if ($showRealityCheckCard)
                <div
                    id="cp-reality-wrap"
                    class="cp-reality-wrap"
                    data-reality-url="{{ route('crop-progress.reality-check') }}"
                >
                    <section class="ag-card cp-reality-card" id="cp-reality-question" aria-label="Field reality check">
                        <h2 class="cp-section-title">Reality check</h2>
                        <p class="cp-section-lead">
                            Is your crop already in <strong>{{ $realityQuestionStage }}</strong>?
                        </p>
                        <form class="cp-reality-actions" method="post" action="{{ route('crop-progress.reality-check') }}" id="cp-reality-form">
                            @csrf
                            <button type="submit" name="response" value="yes" class="cp-btn cp-btn--primary">Yes</button>
                            <button type="submit" name="response" value="not_yet" class="cp-btn cp-btn--ghost">Not yet</button>
                        </form>
                        <p class="cp-reality-footnote">“Not yet” marks progress as delayed and nudges future dates forward so advice stays realistic.</p>
                    </section>
                </div>
            @endif

            {{-- 6. Update stage (action) --}}
            <section class="ag-card cp-override-card" aria-label="Update current stage">
                <h2 class="cp-section-title">Update stage</h2>
                <p class="cp-section-lead">Override if the field is ahead or behind — we’ll refresh dates and advice.</p>
                <form class="cp-override-form" method="post" action="{{ route('crop-progress.update-current-stage') }}" id="cp-stage-override-form">
                    @csrf
                    @method('PUT')
                    <label class="cp-sr-only" for="cp-stage-select">Current growth stage</label>
                    <select name="farming_stage" id="cp-stage-select" class="cp-stage-select">
                        @php
                            $cpNormalizedStage = app(\App\Services\CropTimelineService::class)->normalizeStageKey((string) ($user->farming_stage ?? 'planting'));
                        @endphp
                        @foreach ($stages as $key => $label)
                            <option value="{{ $key }}" @selected($cpNormalizedStage === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="cp-btn cp-btn--secondary">Apply</button>
                </form>
            </section>

            {{-- 7. Detailed timeline (date ranges & stage breakdown) --}}
            <section class="ag-card cp-detailed-timeline" aria-labelledby="cp-detailed-timeline-heading">
                <h2 id="cp-detailed-timeline-heading" class="cp-section-title cp-detailed-timeline__title">
                    <span aria-hidden="true">📅</span>
                    Detailed timeline
                </h2>
                <p class="cp-section-lead cp-detailed-timeline__lead">Date ranges and stage breakdown</p>
                <div class="cp-detailed-timeline__body">
                    <section class="cp-timeline-card cp-timeline-card--nested" aria-labelledby="cp-timeline-heading">
                        <div class="cp-timeline-card__head">
                            <h3 id="cp-timeline-heading" class="cp-timeline-card__title">
                                <img src="{{ $cpImg('chart') }}" alt="" class="weather-clay-ic weather-clay-ic--title" width="18" height="18" decoding="async">
                                Estimated timeline
                            </h3>
                            <div class="cp-timeline-card__meta">
                                <img src="{{ $cpImg('clock') }}" alt="" class="weather-clay-ic weather-clay-ic--xs" width="14" height="14" decoding="async">
                                <span class="cp-timeline-pill">{{ $progressPercent }}% along</span>
                            </div>
                        </div>
                        <p class="cp-timeline-intro">Each row starts from your saved planting date for that stage; typical lengths follow your crop profile. Weather may shift when stages end in the field.</p>
                        <div class="cp-progress-block">
                            <div class="cp-progress-block__labels">
                                <span class="cp-progress-block__lbl">Progress</span>
                                <span class="cp-progress-block__pct">{{ $progressPercent }}%</span>
                            </div>
                            <div class="cp-progress-block__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progressPercent }}" aria-label="Overall crop stage progress">
                                <span class="cp-progress-block__fill cp-progress-line-fill" @style(['width: '.$progressPercent.'%'])></span>
                            </div>
                        </div>
                        <div class="cp-timeline-shell">
                            <div class="cp-progress-line" aria-hidden="true">
                                <span class="cp-progress-line-fill" @style(['width: '.$progressPercent.'%'])></span>
                            </div>
                            <div class="cp-stage-row" role="list">
                                @foreach ($timelineItems as $idx => $item)
                                    @php
                                        $state = strtolower((string) ($item['status'] ?? 'upcoming'));
                                        $dayMark = (int) (($item['estimated_day_count'] ?? 0) / 1);
                                        $dateLine = $item['date_range_line'] ?? null;
                                        if ($dateLine === null && ! empty($item['target_date'])) {
                                            try {
                                                $dateLine = app(\App\Services\CropTimelineService::class)->formatStageTypicalWindow(
                                                    (string) ($item['stage'] ?? ''),
                                                    (string) $item['target_date'],
                                                    (string) (($user ?? null)?->crop_type ?? '')
                                                );
                                            } catch (\Throwable) {
                                                $dateLine = (string) ($item['target_date'] ?? '');
                                            }
                                        }
                                        $stageTitle = $item['stage'] ?? 'Stage';
                                        $stEmoji = $timelineStageEmoji($stageTitle);
                                        $miniFillPct = ($state === 'completed' || $state === 'current') ? 100 : 0;
                                    @endphp
                                    <article class="cp-stage-node cp-stage-{{ $state }} cp-reveal" @style(['--stagger: '.($idx * 70).'ms']) role="listitem">
                                        <span class="cp-stage-marker" aria-hidden="true">
                                            @if ($state === 'completed')
                                                <img src="{{ $cpImg('check') }}" alt="" class="cp-stage-marker__ic" width="18" height="18" decoding="async">
                                            @else
                                                <span class="cp-stage-day">{{ $dayMark }}</span>
                                            @endif
                                        </span>
                                        <div class="cp-stage-meta">
                                            <p class="cp-stage-name">
                                                <span class="cp-stage-emoji" aria-hidden="true">{{ $stEmoji }}</span>
                                                {{ $stageTitle }}
                                                @if ($state === 'current')
                                                    <span class="cp-stage-current-tag">(Current)</span>
                                                @endif
                                            </p>
                                            <p class="cp-stage-date">{{ $dateLine }}</p>
                                            <div class="cp-stage-mini" aria-hidden="true">
                                                <span class="cp-stage-mini__fill" @style(['width: '.$miniFillPct.'%'])></span>
                                            </div>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                    <details class="cp-why-details cp-why-details--nested">
                        <summary>Why timeline may change</summary>
                        <ul class="cp-why-list">
                            <li><strong>Weather</strong> — rain, heat, and wind affect how fast the crop moves.</li>
                            <li><strong>Water</strong> — too much or too little changes root growth and stage timing.</li>
                            <li><strong>Soil</strong> — fertility, compaction, and drainage shift real-world progress.</li>
                        </ul>
                    </details>
                </div>
            </section>

            {{-- 8. Next stage preview --}}
            <section class="ag-card cp-next-card" aria-label="Next growth stage">
                <div class="cp-next-card__accent" aria-hidden="true"></div>
                <div class="cp-next-card__inner">
                    <div class="cp-next-card__top">
                        <p class="cp-next-label">Next stage</p>
                        <span class="cp-next-icon cp-next-icon--emoji" aria-hidden="true">{{ $next_stage ? $timelineStageEmoji((string) $next_stage) : '🌿' }}</span>
                    </div>
                    <p class="cp-next-stage">{{ $next_stage ?: 'No upcoming stage' }}</p>
                    <div class="cp-next-stats">
                        <div class="cp-next-stat">
                            <span class="cp-next-stat__lbl">Typical window</span>
                            <span class="cp-next-stat__val">{{ $next_stage_date_range ?? ($next_stage_target_date ? app(\App\Services\CropTimelineService::class)->formatStageTypicalWindow((string) ($next_stage ?? ''), (string) $next_stage_target_date, (string) (($user ?? null)?->crop_type ?? '')) : '—') }}</span>
                        </div>
                        <div class="cp-next-stat">
                            <span class="cp-next-stat__lbl">Days remaining (approx.)</span>
                            <span class="cp-next-stat__val cp-next-stat__val--emph">{{ is_numeric($days_remaining) ? (int) $days_remaining : '—' }}</span>
                        </div>
                    </div>
                    <span class="cp-status-pill {{ $adjustmentLabelClass }}">
                        {{ $timeline_adjustment_label }}
                    </span>
                </div>
            </section>

            {{-- 9. Key details --}}
            <section class="ag-card farm-dash cp-key-details" aria-label="Key crop details">
                <div class="cp-key-details__head">
                    <h2 class="farm-dash__title cp-key-details__title">Key details</h2>
                </div>
                <div class="cp-key-details__grid">
                    <div class="cp-key-details__item farm-dash__cell farm-dash__cell--slate">
                        <span class="farm-dash__emoji farm-dash__emoji--clay cp-key-details__ic" aria-hidden="true">
                            <img src="{{ $cpImg('barn') }}" alt="" class="weather-clay-ic weather-clay-ic--stat" width="28" height="28" decoding="async">
                        </span>
                        <div class="cp-key-details__copy">
                            <p class="farm-dash__lbl">Farm</p>
                            <p class="farm-dash__val">{{ $farm_name }}</p>
                        </div>
                    </div>
                    <div class="cp-key-details__item farm-dash__cell farm-dash__cell--amber">
                        <span class="farm-dash__emoji farm-dash__emoji--clay cp-key-details__ic" aria-hidden="true">
                            <img src="{{ $cpImg('grain') }}" alt="" class="weather-clay-ic weather-clay-ic--stat" width="28" height="28" decoding="async">
                        </span>
                        <div class="cp-key-details__copy">
                            <p class="farm-dash__lbl">Crop</p>
                            <p class="farm-dash__val">{{ $user->crop_type ?: 'Not set' }}</p>
                        </div>
                    </div>
                    <div class="cp-key-details__item farm-dash__cell farm-dash__cell--mint">
                        <span class="farm-dash__emoji farm-dash__emoji--clay cp-key-details__ic" aria-hidden="true">
                            <img src="{{ $cpImg('leaf_stage') }}" alt="" class="weather-clay-ic weather-clay-ic--stat" width="28" height="28" decoding="async">
                        </span>
                        <div class="cp-key-details__copy">
                            <p class="farm-dash__lbl">Stage</p>
                            <p class="farm-dash__val">{{ $current_stage_label }}</p>
                        </div>
                    </div>
                    <div class="cp-key-details__item farm-dash__cell farm-dash__cell--violet">
                        <span class="farm-dash__emoji farm-dash__emoji--clay cp-key-details__ic" aria-hidden="true">
                            <img src="{{ $cpImg('calendar') }}" alt="" class="weather-clay-ic weather-clay-ic--stat" width="28" height="28" decoding="async">
                        </span>
                        <div class="cp-key-details__copy">
                            <p class="farm-dash__lbl">Planting date</p>
                            <p class="farm-dash__val">{{ $user->planting_date?->format('M d, Y') ?: 'Not set' }}</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </section>
@endsection
