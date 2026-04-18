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

    $cpTimelineCurrentItem = null;
    $cpTimelineNextItem = null;
    foreach ($timelineItems as $idx => $stageItem) {
        if (strtolower((string) ($stageItem['status'] ?? '')) === 'current') {
            $cpTimelineCurrentItem = $stageItem;
            for ($j = $idx + 1; $j < $timelineCount; $j++) {
                $nj = $timelineItems[$j];
                if (strtolower((string) ($nj['status'] ?? '')) !== 'completed') {
                    $cpTimelineNextItem = $nj;
                    break;
                }
            }
            break;
        }
    }

    $cpStageDateLine = static function (array $item) use ($user): ?string {
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
        $dateLine = $dateLine !== null ? trim((string) $dateLine) : '';

        return $dateLine !== '' ? $dateLine : null;
    };

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
    <section class="dashboard-shell dashboard-shell--dashboard-home py-4 sm:py-6 pb-24 cp-page-enter">
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
                            </div>
                        </div>
                        <div class="dashboard-hero__meta">
                            <span class="dashboard-hero__pill crop-progress-page-hero__pill">
                                <span class="dashboard-hero__pill-ic" aria-hidden="true">
                                    <i data-lucide="tractor" class="dashboard-hero__lucide"></i>
                                </span>
                                <span class="dashboard-hero__pill-text">{{ $farm_name }}</span>
                            </span>
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

            {{-- Stage snapshot — same section footprint as weather Field snapshot (hero row + stat strip only) --}}
            <section
                class="ag-card weather-snapshot weather-page__snap-layout cp-stage-snapshot overflow-hidden rounded-3xl border border-teal-200/70 bg-gradient-to-br from-teal-50/95 via-sky-50/88 to-indigo-50/50 p-3.5 shadow-sm sm:p-4"
                aria-label="Current crop stage highlight"
            >
                <div class="flex items-start gap-3">
                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-white to-teal-50 ring-1 ring-teal-100 shadow-sm sm:h-11 sm:w-11" aria-hidden="true">
                        <img src="{{ $cpImg('leaf_stage') }}" alt="" class="weather-clay-ic h-7 w-7 object-contain sm:h-8 sm:w-8" width="32" height="32" decoding="async">
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <span class="inline-flex items-center gap-1 rounded-full border border-teal-200/80 bg-white/75 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-[0.14em] text-teal-900/85 shadow-sm">
                            <i data-lucide="sprout" class="h-3 w-3 shrink-0 text-teal-600"></i>
                            Active stage
                        </span>
                        <div class="mt-1.5 flex flex-wrap items-end gap-x-2 gap-y-0.5">
                            <p class="text-2xl font-extrabold leading-none tracking-tight text-slate-900 sm:text-3xl">{{ $current_stage_label }}</p>
                            <p class="max-w-[14rem] text-xs font-semibold leading-snug text-slate-600 sm:text-sm">Growth lifecycle</p>
                        </div>
                    </div>
                </div>
                <div class="mt-2.5 flex divide-x divide-slate-200/90 overflow-hidden rounded-2xl border border-slate-200/85 bg-white/65 shadow-inner ring-1 ring-white/60" role="list">
                    <article class="min-w-0 flex-1 px-1 py-1.5 text-center sm:px-1.5" role="listitem">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Growth speed</p>
                        <p class="mt-0.5 text-xs font-bold tabular-nums text-slate-900 sm:text-sm">{{ ucfirst($growthSpeed) }}</p>
                    </article>
                    <article class="min-w-0 flex-1 px-1 py-1.5 text-center sm:px-1.5" role="listitem">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Confidence</p>
                        <p class="mt-0.5 text-xs font-bold text-slate-900 sm:text-sm">{{ $stageConfidence['label'] ?? 'Medium' }}</p>
                    </article>
                    <article class="min-w-0 flex-1 px-1 py-1.5 text-center sm:px-1.5" role="listitem">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Timeline</p>
                        <p class="mt-0.5 text-xs font-bold tabular-nums text-slate-900 sm:text-sm">{{ (int) $progressPercent }}%</p>
                    </article>
                </div>
            </section>

            {{-- Planting & progress: compact card with date + timeline progress --}}
            <section class="ag-card cp-planting-progress cp-planting-progress--enter" aria-labelledby="cp-planting-progress-heading">
                <div class="cp-planting-progress__ambient" aria-hidden="true"></div>
                <div class="cp-planting-progress__inner">
                    <div class="cp-planting-progress__head">
                        <span class="cp-planting-progress__head-icon" aria-hidden="true">
                            <i data-lucide="calendar-range" class="cp-planting-progress__lucide-head"></i>
                        </span>
                        <h2 id="cp-planting-progress-heading" class="cp-planting-progress__title">Planting &amp; progress</h2>
                    </div>
                    <div class="cp-planting-progress__grid">
                        <div class="cp-planting-progress__date">
                            <span class="cp-planting-progress__label">Planting date</span>
                            @if (($has_planting_date ?? false) && ! empty($planting_date_formatted))
                                <div class="cp-planting-progress__date-row">
                                    <time class="cp-planting-progress__time" datetime="{{ $user->planting_date?->format('Y-m-d') }}">{{ $planting_date_formatted }}</time>
                                    <img src="{{ $cpImg('pin') }}" alt="" class="weather-clay-ic cp-planting-progress__pin" width="18" height="18" decoding="async" aria-hidden="true">
                                </div>
                            @else
                                <div class="cp-planting-progress__date-row cp-planting-progress__date-row--missing">
                                    <img src="{{ $cpImg('alert') }}" alt="" class="weather-clay-ic cp-planting-progress__alert-ic" width="18" height="18" decoding="async" aria-hidden="true">
                                    <span class="cp-planting-progress__missing">Not set — Farm Settings</span>
                                </div>
                            @endif
                        </div>
                        <div class="cp-planting-progress__meter">
                            <div class="cp-planting-progress__meter-top">
                                <span class="cp-planting-progress__label">Cycle progress</span>
                                <span class="cp-planting-progress__pct">{{ (int) $progressPercent }}%</span>
                            </div>
                            <div
                                class="cp-planting-progress__track"
                                role="progressbar"
                                aria-valuemin="0"
                                aria-valuemax="100"
                                aria-valuenow="{{ (int) $progressPercent }}"
                                aria-label="Estimated progress through crop cycle"
                            >
                                <span class="cp-planting-progress__fill cp-progress-line-fill" @style(['width: '.((int) $progressPercent).'%'])></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- AI Smart Advisory — same section pattern as weather-details (emerald smart card + slate blocks + plan rows + split grid) --}}
            <article class="ag-card dash-smart weather-page__smart rounded-3xl border border-emerald-200 bg-emerald-50/80 p-4 sm:p-5" aria-label="AI smart advisory">
                <div class="dash-smart__debug">
                    <p class="text-xs font-semibold text-slate-700">
                        @if ($cpAiOk)
                            <span class="text-emerald-700">AI Smart Advisory: Active</span>
                        @elseif ($cpAiStatus === 'missing_context')
                            <span class="text-amber-800">AI Smart Advisory: Profile incomplete</span>
                        @else
                            <span class="text-rose-700">AI Smart Advisory: Unavailable</span>
                        @endif
                    </p>
                    @if (! $cpAiOk && $cpAiError !== '')
                        <p class="mt-1 text-xs text-slate-600">Error: {{ $cpAiError }}</p>
                    @endif
                </div>

                <div class="dash-smart__head">
                    <div class="dash-smart__title-wrap">
                        <span class="inline-flex items-center gap-1.5 border-b border-slate-200 pb-1 text-xs font-extrabold uppercase tracking-[0.1em] text-slate-700 transition-all duration-300 hover:tracking-[0.12em] hover:text-slate-900">
                            <i data-lucide="sparkles" class="h-3.5 w-3.5 text-emerald-600"></i>
                            Smart action
                        </span>
                    </div>
                </div>
                <div class="dash-smart__body">
                    <p class="dash-smart__action">{{ $cpAiOk ? $smartActionLine : $cpAiMsg }}</p>
                </div>
            </article>

            @if ($cpAiOk)
                <section class="ag-card cp-advice-compact relative overflow-hidden rounded-2xl border border-violet-200/50 bg-gradient-to-br from-violet-50/95 via-white to-emerald-50/50 shadow-lg shadow-violet-500/10 ring-1 ring-violet-100/90 sm:rounded-[1.35rem]" aria-label="Advice and next steps">
                    <div class="cp-advice-compact__ambient" aria-hidden="true"></div>
                    <div class="cp-advice-compact__sheen" aria-hidden="true"></div>

                    <div class="relative space-y-3 px-3 py-3.5 sm:space-y-3.5 sm:px-4 sm:py-4">
                        <div class="cp-advice-compact__segment cp-advice-compact__segment--summary cp-advice-compact__stagger cp-advice-compact__stagger--1 rounded-xl border border-violet-100/90 bg-white/85 p-3 shadow-sm backdrop-blur-[2px]">
                            <div class="flex items-center gap-2">
                                <span class="cp-advice-compact__icon-ring cp-advice-compact__icon-ring--violet">
                                    <img src="{{ $cpImg('bulb') }}" alt="" class="h-4 w-4 object-contain" width="16" height="16" decoding="async">
                                </span>
                                <h2 class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-violet-900/85">Advice summary</h2>
                            </div>
                            <p class="cp-advice-compact__prose mt-2 text-[13px] leading-relaxed text-slate-700">
                                {{ $mainAdvice !== '' ? $mainAdvice : 'No summary returned for this stage yet.' }}
                            </p>
                        </div>

                        @if (count($whatToDoRest) > 0)
                            <div class="cp-advice-compact__segment cp-advice-compact__segment--do cp-advice-compact__stagger cp-advice-compact__stagger--2 rounded-xl border border-emerald-100/95 bg-gradient-to-br from-emerald-50/90 to-white/95 p-3 shadow-sm ring-1 ring-emerald-100/70">
                                <div class="flex items-center gap-2">
                                    <span class="cp-advice-compact__icon-ring cp-advice-compact__icon-ring--emerald">
                                        <img src="{{ $cpImg('sprout') }}" alt="" class="h-4 w-4 object-contain" width="16" height="16" decoding="async">
                                    </span>
                                    <h3 class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-emerald-900/85">What to do</h3>
                                </div>
                                <ul class="cp-advice-compact__task-list mt-2.5 space-y-2">
                                    @foreach ($whatToDoRest as $line)
                                        <li class="cp-advice-compact__task">
                                            <span class="cp-advice-compact__task-dot" aria-hidden="true"></span>
                                            <span class="cp-advice-compact__task-text">{{ $line }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="cp-advice-compact__segment cp-advice-compact__segment--watch cp-advice-compact__stagger cp-advice-compact__stagger--3 rounded-xl border border-sky-200/80 bg-gradient-to-br from-sky-50/95 to-indigo-50/40 p-3 shadow-md ring-1 ring-sky-100/80">
                            <div class="flex items-center gap-2">
                                <span class="cp-advice-compact__icon-ring cp-advice-compact__icon-ring--sky">
                                    <img src="{{ $cpImg('eye') }}" alt="" class="h-4 w-4 object-contain" width="16" height="16" decoding="async">
                                </span>
                                <h3 class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-sky-950/90">What to watch</h3>
                            </div>
                            @if (count($whatToWatch) > 0)
                                <div class="cp-advice-compact__watch-body mt-2.5 space-y-2">
                                    @foreach ($whatToWatch as $line)
                                        <p class="cp-advice-compact__watch-line relative rounded-lg border border-sky-100/70 bg-white/75 px-2.5 py-2 text-[13px] leading-snug text-slate-700 shadow-sm">{{ $line }}</p>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-2 text-[13px] text-slate-500">No watch items for this stage.</p>
                            @endif
                        </div>
                    </div>
                </section>
            @else
                <section class="ag-card cp-advice-compact cp-advice-compact--muted relative overflow-hidden rounded-2xl border border-slate-200/90 bg-slate-50/95 p-4 shadow-inner sm:p-4" aria-label="Advice unavailable">
                    <div class="flex items-center gap-2">
                        <span class="cp-advice-compact__icon-ring cp-advice-compact__icon-ring--slate">
                            <img src="{{ $cpImg('bulb') }}" alt="" class="h-4 w-4 object-contain opacity-90" width="16" height="16" decoding="async">
                        </span>
                        <h2 class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-slate-600">Advice summary</h2>
                    </div>
                    <p class="cp-advice-compact__prose mt-2 text-[13px] leading-relaxed text-slate-600">{{ $cpAiMsg }}</p>
                </section>
            @endif

            <section class="cp-insight-split grid gap-3 sm:grid-cols-2 sm:gap-4" aria-label="What to avoid and why this matters">
                <article class="cp-insight-card cp-insight-card--avoid cp-insight-card--enter cp-insight-card--enter-1 ag-card relative overflow-hidden rounded-[1.35rem] border border-rose-200/85 bg-gradient-to-br from-rose-50/98 via-white to-amber-50/45 shadow-lg shadow-rose-500/10 ring-1 ring-rose-100/90">
                    <div class="cp-insight-card__ambient cp-insight-card__ambient--rose" aria-hidden="true"></div>
                    <div class="cp-insight-card__sheen cp-insight-card__sheen--warm" aria-hidden="true"></div>
                    <div class="relative z-[1] p-4 sm:p-5">
                        <header class="flex items-start gap-3">
                            <span class="cp-insight-card__ring cp-insight-card__ring--rose">
                                <img src="{{ $cpImg('alert') }}" alt="" class="h-[18px] w-[18px] object-contain" width="18" height="18" decoding="async">
                            </span>
                            <div class="min-w-0 pt-0.5">
                                <h2 class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-rose-950/90">What to avoid</h2>
                                <p class="mt-0.5 text-[10px] font-semibold text-rose-800/65">Actions to skip for today</p>
                            </div>
                        </header>
                        @if ($cpAiOk)
                            <ul class="cp-insight-card__list mt-5 space-y-2 sm:mt-6">
                                @forelse ($whatToAvoid as $line)
                                    <li class="cp-insight-card__avoid-row">
                                        <span class="cp-insight-card__avoid-mark" aria-hidden="true"></span>
                                        <span class="cp-insight-card__avoid-text">{{ $line }}</span>
                                    </li>
                                @empty
                                    <li class="cp-insight-card__empty">Nothing specific to avoid right now.</li>
                                @endforelse
                            </ul>
                        @else
                            <p class="cp-insight-card__fallback mt-5 text-[13px] leading-relaxed text-slate-600 sm:mt-6">{{ $cpAiMsg }}</p>
                        @endif
                    </div>
                </article>

                <article class="cp-insight-card cp-insight-card--why cp-insight-card--enter cp-insight-card--enter-2 ag-card relative overflow-hidden rounded-[1.35rem] border border-violet-200/80 bg-gradient-to-br from-violet-50/95 via-fuchsia-50/25 to-indigo-50/40 shadow-lg shadow-violet-500/10 ring-1 ring-violet-100/85">
                    <div class="cp-insight-card__ambient cp-insight-card__ambient--violet" aria-hidden="true"></div>
                    <div class="cp-insight-card__sheen cp-insight-card__sheen--cool" aria-hidden="true"></div>
                    <div class="relative z-[1] p-4 sm:p-5">
                        <header class="flex items-start gap-3">
                            <span class="cp-insight-card__ring cp-insight-card__ring--violet">
                                <img src="{{ $cpImg('bulb') }}" alt="" class="h-[18px] w-[18px] object-contain" width="18" height="18" decoding="async">
                            </span>
                            <div class="min-w-0 pt-0.5">
                                <h2 class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-violet-950/90">Why this matters</h2>
                                <p class="mt-0.5 text-[10px] font-semibold text-violet-800/65">Context behind the guidance</p>
                            </div>
                        </header>
                        @if ($cpAiOk)
                            <div class="cp-insight-card__why-panel mt-5 sm:mt-6">
                                <p class="cp-insight-card__why-text">
                                    {{ $whyThisMatters !== '' ? $whyThisMatters : '—' }}
                                </p>
                            </div>
                        @else
                            <p class="cp-insight-card__fallback mt-5 text-[13px] leading-relaxed text-slate-600 sm:mt-6">{{ $cpAiMsg }}</p>
                        @endif
                    </div>
                </article>
            </section>

            @if ($showAiDebug && ! empty($recommendation['ai_error'] ?? ''))
                <p class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $recommendation['ai_error'] }}</p>
            @endif

            {{-- Crop Growth Timeline: update stage + stepper + progress + current/next dates --}}
            <section class="ag-card cp-growth-timeline" aria-labelledby="cp-stage-update-heading cp-growth-timeline-heading">
                <div class="cp-growth-timeline__update">
                    <h2 id="cp-stage-update-heading" class="cp-section-title">Update stage</h2>
                    <form class="cp-override-form cp-growth-timeline__override-form" method="post" action="{{ route('crop-progress.update-current-stage') }}" id="cp-stage-override-form">
                        @csrf
                        @method('PUT')
                        <div class="cp-growth-timeline__field-group">
                            <label for="cp-stage-select" class="cp-growth-timeline__field-label">Current growth stage</label>
                            <select name="farming_stage" id="cp-stage-select" class="cp-stage-select">
                                @php
                                    $cpNormalizedStage = app(\App\Services\CropTimelineService::class)->normalizeStageKey((string) ($user->farming_stage ?? 'planting'));
                                @endphp
                                @foreach ($stages as $key => $label)
                                    <option value="{{ $key }}" @selected($cpNormalizedStage === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="cp-btn cp-btn--secondary">Apply</button>
                    </form>
                </div>

                <h2 id="cp-growth-timeline-heading" class="cp-growth-timeline__title cp-growth-timeline__title--timeline">Crop Growth Timeline</h2>

                <div class="cp-growth-timeline__stepper-scroll">
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
                            <div class="cp-tracker-node {{ $isHere ? 'cp-tracker-node--here cp-growth-timeline__node--current' : '' }} {{ $isPast ? 'cp-tracker-node--past' : '' }}" role="listitem">
                                <span class="cp-tracker-emoji" aria-hidden="true">{{ $meta['emoji'] }}</span>
                                <span class="cp-tracker-label">{{ $meta['label'] }}</span>
                                @if ($isHere)
                                    <span class="cp-tracker-here">Current</span>
                                @endif
                            </div>
                            @if (! $loop->last)
                                <span class="cp-tracker-connector" aria-hidden="true"></span>
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="cp-growth-timeline__progress-block">
                    <div class="cp-growth-timeline__progress-row">
                        <span class="cp-growth-timeline__progress-label">Progress</span>
                        <span class="cp-growth-timeline__progress-pct">{{ (int) $progressPercent }}%</span>
                    </div>
                    <div
                        class="cp-growth-timeline__track"
                        role="progressbar"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ (int) $progressPercent }}"
                        aria-label="Crop cycle progress"
                    >
                        <span class="cp-growth-timeline__fill cp-progress-line-fill" @style(['width: '.((int) $progressPercent).'%'])></span>
                    </div>
                </div>

                @php
                    $curTitle = $cpTimelineCurrentItem ? (string) ($cpTimelineCurrentItem['stage'] ?? '') : (string) $current_stage_label;
                    $curEmoji = $timelineStageEmoji($curTitle !== '' ? $curTitle : (string) $current_stage_label);
                    $curDates = $cpTimelineCurrentItem ? $cpStageDateLine($cpTimelineCurrentItem) : null;

                    $nextTitle = $cpTimelineNextItem
                        ? (string) ($cpTimelineNextItem['stage'] ?? '')
                        : (string) ($next_stage ?? '');
                    $nextEmoji = $nextTitle !== ''
                        ? $timelineStageEmoji($nextTitle)
                        : ($next_stage ? $timelineStageEmoji((string) $next_stage) : '🌿');
                    $nextDates = $cpTimelineNextItem
                        ? $cpStageDateLine($cpTimelineNextItem)
                        : null;
                    if ($nextDates === null && ! empty($next_stage_date_range)) {
                        $nextDates = trim((string) $next_stage_date_range);
                    }
                    if ($nextDates === null && ! empty($next_stage_target_date)) {
                        try {
                            $nextDates = app(\App\Services\CropTimelineService::class)->formatStageTypicalWindow(
                                (string) ($next_stage ?? ''),
                                (string) $next_stage_target_date,
                                (string) (($user ?? null)?->crop_type ?? '')
                            );
                        } catch (\Throwable) {
                            $nextDates = null;
                        }
                    }
                    $showNextBlock = trim($nextTitle) !== '';
                @endphp

                <div class="cp-growth-timeline__details">
                    <div class="cp-growth-timeline__detail cp-growth-timeline__detail--current">
                        <div class="cp-growth-timeline__detail-head">
                            <span class="cp-growth-timeline__detail-emoji" aria-hidden="true">{{ $curEmoji }}</span>
                            <div class="cp-growth-timeline__detail-text">
                                <p class="cp-growth-timeline__detail-name">{{ $curTitle !== '' ? $curTitle : $current_stage_label }}</p>
                                <span class="cp-growth-timeline__detail-badge">Current</span>
                            </div>
                        </div>
                        @if ($curDates)
                            <p class="cp-growth-timeline__detail-dates">{{ $curDates }}</p>
                        @endif
                    </div>

                    @if ($showNextBlock)
                        <div class="cp-growth-timeline__detail cp-growth-timeline__detail--next">
                            <p class="cp-growth-timeline__next-label">Next</p>
                            <div class="cp-growth-timeline__detail-head">
                                <span class="cp-growth-timeline__detail-emoji" aria-hidden="true">{{ $nextEmoji }}</span>
                                <div class="cp-growth-timeline__detail-text">
                                    <p class="cp-growth-timeline__detail-name">{{ $nextTitle }}</p>
                                </div>
                            </div>
                            @if ($nextDates)
                                <p class="cp-growth-timeline__detail-dates">{{ $nextDates }}</p>
                            @endif
                            @if (is_numeric($days_remaining ?? null))
                                <p class="cp-growth-timeline__days-left">≈ {{ (int) $days_remaining }} days to next stage</p>
                            @endif
                        </div>
                    @endif
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

            {{-- Key details: 4 themed cards --}}
            <section class="cp-key-details" aria-labelledby="cp-key-details-heading">
                <h2 id="cp-key-details-heading" class="cp-key-details__heading">Key details</h2>
                <div class="cp-key-details__grid">
                    <article class="cp-key-details__card cp-key-details__card--farm cp-key-details__card--enter" style="--kd-delay: 0ms">
                        <div class="cp-key-details__card-glow" aria-hidden="true"></div>
                        <div class="cp-key-details__card-inner">
                            <span class="cp-key-details__icon-wrap cp-key-details__icon-wrap--farm" aria-hidden="true">
                                <i data-lucide="tractor" class="cp-key-details__lucide"></i>
                            </span>
                            <div class="cp-key-details__body">
                                <h3 class="cp-key-details__label">Farm</h3>
                                <p class="cp-key-details__value">{{ $farm_name }}</p>
                            </div>
                        </div>
                    </article>
                    <article class="cp-key-details__card cp-key-details__card--crop cp-key-details__card--enter" style="--kd-delay: 55ms">
                        <div class="cp-key-details__card-glow" aria-hidden="true"></div>
                        <div class="cp-key-details__card-inner">
                            <span class="cp-key-details__icon-wrap cp-key-details__icon-wrap--crop" aria-hidden="true">
                                <i data-lucide="leaf" class="cp-key-details__lucide"></i>
                            </span>
                            <div class="cp-key-details__body">
                                <h3 class="cp-key-details__label">Crop</h3>
                                <p class="cp-key-details__value">{{ $user->crop_type ?: 'Not set' }}</p>
                            </div>
                        </div>
                    </article>
                    <article class="cp-key-details__card cp-key-details__card--stage cp-key-details__card--enter" style="--kd-delay: 110ms">
                        <div class="cp-key-details__card-glow" aria-hidden="true"></div>
                        <div class="cp-key-details__card-inner">
                            <span class="cp-key-details__icon-wrap cp-key-details__icon-wrap--stage" aria-hidden="true">
                                <i data-lucide="sprout" class="cp-key-details__lucide"></i>
                            </span>
                            <div class="cp-key-details__body">
                                <h3 class="cp-key-details__label">Stage</h3>
                                <p class="cp-key-details__value">{{ $current_stage_label }}</p>
                            </div>
                        </div>
                    </article>
                    <article class="cp-key-details__card cp-key-details__card--plant cp-key-details__card--enter" style="--kd-delay: 165ms">
                        <div class="cp-key-details__card-glow" aria-hidden="true"></div>
                        <div class="cp-key-details__card-inner">
                            <span class="cp-key-details__icon-wrap cp-key-details__icon-wrap--plant" aria-hidden="true">
                                <i data-lucide="calendar-days" class="cp-key-details__lucide"></i>
                            </span>
                            <div class="cp-key-details__body">
                                <h3 class="cp-key-details__label">Planting date</h3>
                                <p class="cp-key-details__value cp-key-details__value--date">
                                    @if (! empty($planting_date_formatted))
                                        <time datetime="{{ $user->planting_date?->format('Y-m-d') }}">{{ $planting_date_formatted }}</time>
                                    @else
                                        Not set
                                    @endif
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </section>
@endsection
