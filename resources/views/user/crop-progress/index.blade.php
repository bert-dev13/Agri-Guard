@extends('layouts.user')

@section('title', 'Crop Progress - AGRIGUARD')

@section('body-class', 'crop-progress-page min-h-screen bg-[#F4F6F5]')

@section('main-class', 'pt-20')

@section('content')
    @php
        $showAiDebug = app()->environment('local') || (bool) config('app.debug');
        $aiStatus = strtolower((string) ($recommendation['ai_status'] ?? 'failed'));
        $riskLevel = (string) ($recommendation['risk_level'] ?? 'Moderate');
        $riskClass = match (strtolower($riskLevel)) {
            'high' => 'bg-rose-100 text-rose-700 border border-rose-200',
            'low' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
            default => 'bg-amber-100 text-amber-700 border border-amber-200',
        };
        $adjustmentLabelClass = match (strtolower($timeline_adjustment_label ?? 'on schedule')) {
            'slightly delayed' => 'bg-amber-100 text-amber-700 border border-amber-200',
            'faster than usual' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
            default => 'bg-slate-100 text-slate-700 border border-slate-200',
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
        $progressPercent = $timelineCount > 1 ? (int) round(($currentIndex / ($timelineCount - 1)) * 100) : 0;
    @endphp

    <section class="py-4 sm:py-5 pb-16 cp-page-enter">
        <div class="max-w-4xl mx-auto px-4 sm:px-5 space-y-3.5 sm:space-y-4">
            @if (session('success'))
                <div class="rounded-2xl bg-[#66BB6A]/15 border border-[#66BB6A]/30 text-[#1B5E20] px-4 py-3.5 text-sm font-medium flex items-center gap-2" role="alert">
                    <i data-lucide="check-circle" class="w-5 h-5 text-[#2E7D32] shrink-0"></i>
                    {{ session('success') }}
                </div>
            @endif

            <header class="ag-card ag-welcome-gradient overflow-hidden" aria-label="Crop progress header">
                <div class="relative px-5 py-5 sm:px-6 sm:py-6 flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl sm:text-2xl font-bold text-white tracking-tight">Crop Progress</h1>
                        <p class="mt-1.5 text-white/90 text-sm">Track crop stages and estimated target dates.</p>
                        <p class="mt-2 text-white/95 text-sm flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-4 h-4 shrink-0 opacity-90"></i>
                            {{ $farm_name }}
                        </p>
                        <p class="mt-1.5 text-white/85 text-sm">{{ $current_stage_label }}</p>
                        <p class="mt-1 text-white/70 text-xs">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                    <span class="flex items-center justify-center w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-white/20 shrink-0" aria-hidden="true">
                        <i data-lucide="sprout" class="w-7 h-7 sm:w-8 sm:h-8 text-white"></i>
                    </span>
                </div>
            </header>

            <section class="cp-farm-grid">
                <article class="cp-farm-item cp-farm-item--green">
                    <span class="cp-farm-icon"><i data-lucide="wheat" class="w-4 h-4"></i></span>
                    <p class="cp-farm-text-label">Farm</p>
                    <p class="cp-farm-text-value cp-farm-value-full">{{ $farm_name }}</p>
                </article>
                <article class="cp-farm-item cp-farm-item--blue">
                    <span class="cp-farm-icon"><i data-lucide="sprout" class="w-4 h-4"></i></span>
                    <p class="cp-farm-text-label">Crop</p>
                    <p class="cp-farm-text-value">{{ $user->crop_type ?: 'Not set' }}</p>
                </article>
                <article class="cp-farm-item cp-farm-item--amber cp-farm-item-current">
                    <span class="cp-farm-icon"><i data-lucide="map-pin" class="w-4 h-4"></i></span>
                    <p class="cp-farm-text-label">Stage</p>
                    <p class="cp-farm-text-value">{{ $current_stage_label }}</p>
                </article>
                <article class="cp-farm-item cp-farm-item--violet">
                    <span class="cp-farm-icon"><i data-lucide="calendar-days" class="w-4 h-4"></i></span>
                    <p class="cp-farm-text-label">Planted</p>
                    <p class="cp-farm-text-value">{{ $user->planting_date?->format('M d, Y') ?: 'Not set' }}</p>
                </article>
            </section>

            <section class="ag-card p-4 sm:p-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="cp-section-title">
                        <i data-lucide="git-branch-plus" class="w-4.5 h-4.5 cp-accent-icon"></i>
                        Growth Timeline
                    </h2>
                    <span class="cp-timeline-chip">{{ $progressPercent }}% complete</span>
                </div>
                <div class="cp-timeline-shell mt-3.5">
                    <div class="cp-progress-line" aria-hidden="true">
                        <span class="cp-progress-line-fill" style="width: {{ $progressPercent }}%"></span>
                    </div>
                    <div class="cp-stage-row" role="list" aria-label="Crop growth stages">
                    @foreach ($timelineItems as $idx => $item)
                        @php
                            $state = strtolower((string) ($item['status'] ?? 'upcoming'));
                        @endphp
                        <article class="cp-stage-node cp-stage-{{ $state }} cp-reveal" style="--stagger: {{ $idx * 70 }}ms;" role="listitem">
                            <span class="cp-stage-marker" aria-hidden="true">
                                <span class="cp-stage-day">{{ (int) (($item['estimated_day_count'] ?? 0) / 1) }}</span>
                            </span>
                            <div class="cp-stage-meta">
                                <p class="cp-stage-name">{{ $item['stage'] ?? 'Stage' }}</p>
                                <p class="cp-stage-date">{{ \Carbon\Carbon::parse($item['target_date'] ?? now())->format('M d, Y') }}</p>
                            </div>
                        </article>
                    @endforeach
                    </div>
                </div>
            </section>

            <section class="cp-next-card cp-float">
                <div class="cp-next-top">
                    <p class="cp-next-label">NEXT STAGE</p>
                    <span class="cp-next-icon" aria-hidden="true"><i data-lucide="leaf" class="w-4 h-4 cp-accent-icon"></i></span>
                </div>
                <p class="cp-next-stage">{{ $next_stage ?: 'No upcoming stage' }}</p>
                <p class="cp-next-meta">
                    {{ $next_stage_target_date ? \Carbon\Carbon::parse($next_stage_target_date)->format('M d, Y') : 'N/A' }}
                    <span>•</span>
                    {{ is_numeric($days_remaining) ? (int) $days_remaining . ' days left' : 'N/A' }}
                </p>
                <div class="mt-2.5 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold {{ $adjustmentLabelClass }}">
                        <i data-lucide="activity" class="w-3.5 h-3.5"></i>
                        {{ $timeline_adjustment_label }}
                    </span>
                </div>
            </section>

            @php
                $aiModel = trim((string) ($recommendation['ai_model'] ?? ''));
                $whyThisMatters = trim((string) ($recommendation['why_this_matters'] ?? ''));
                if ($whyThisMatters === '') {
                    $whyThisMatters = 'Proper stage timing reduces crop stress and supports healthier, more consistent growth.';
                }
            @endphp
            <section class="ag-card p-4 sm:p-5 cp-ai-panel">
                <header class="cp-ai-head">
                    <p class="cp-ai-title">
                        <i data-lucide="brain" class="w-4 h-4 cp-accent-icon"></i>
                        STAGE-BASED SMART ADVICE
                    </p>
                    <div class="cp-ai-head-meta">
                        <span class="cp-ai-pill {{ $aiStatus === 'success' && empty($recommendation_failed) ? 'cp-ai-pill-ok' : 'cp-ai-pill-fallback' }}">
                            <i data-lucide="{{ $aiStatus === 'success' && empty($recommendation_failed) ? 'bot' : 'triangle-alert' }}" class="w-3.5 h-3.5"></i>
                            {{ $aiStatus === 'success' && empty($recommendation_failed) ? 'AI Active' : 'AI Fallback' }}
                        </span>
                        @if ($aiModel !== '')
                            <p class="cp-ai-model">Model: {{ $aiModel }}</p>
                        @endif
                    </div>
                </header>

                <div class="cp-ai-main">
                    <p class="cp-ai-main-text">{{ $recommendation['main_advice'] ?? 'Update your stage to receive stage-based smart advice.' }}</p>
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold {{ $riskClass }}">
                        <i data-lucide="shield-alert" class="w-3.5 h-3.5"></i>
                        {{ $riskLevel }} Risk
                    </span>
                </div>

                <div class="cp-ai-grid">
                    <article class="cp-insight-card cp-insight-do">
                        <h3><i data-lucide="sprout" class="w-4 h-4"></i>What to Do</h3>
                        <p>{{ $recommendation['what_to_do'] ?? 'Keep field conditions stable and follow the current stage routine.' }}</p>
                    </article>
                    <article class="cp-insight-card cp-insight-watch">
                        <h3><i data-lucide="eye" class="w-4 h-4"></i>What to Watch</h3>
                        <p>{{ $recommendation['what_to_watch'] ?? 'Check daily for weather shifts, moisture changes, and early pest signs.' }}</p>
                    </article>
                    <article class="cp-insight-card cp-insight-avoid">
                        <h3><i data-lucide="triangle-alert" class="w-4 h-4"></i>What to Avoid</h3>
                        <p>{{ $recommendation['what_to_avoid'] ?? 'Avoid rushed interventions that can stress crops during this stage.' }}</p>
                    </article>
                    <article class="cp-insight-card cp-insight-why">
                        <h3><i data-lucide="lightbulb" class="w-4 h-4"></i>Why This Matters</h3>
                        <p>{{ $whyThisMatters }}</p>
                    </article>
                </div>
            </section>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
@endpush
