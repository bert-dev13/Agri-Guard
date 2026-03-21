@props([
    'recommendation' => [],
    'failed' => false,
    'title' => 'TODAY’S SMART ACTION',
])

@php
    $safe = is_array($recommendation) ? $recommendation : [];
    $aiStatus = strtolower((string) ($safe['ai_status'] ?? 'success'));
    $aiError = trim((string) ($safe['ai_error'] ?? ''));
    $aiModel = trim((string) ($safe['ai_model'] ?? ''));
    $showAiDebug = app()->environment('local') || (bool) config('app.debug');
    $risk = strtolower((string) ($safe['risk'] ?? 'moderate'));
    $riskLabel = $risk === 'high' ? 'High' : ($risk === 'low' ? 'Low' : 'Moderate');

    $riskMap = [
        'low' => [
            'card' => 'border-emerald-200 bg-emerald-50/60',
            'dot' => 'bg-emerald-500',
            'text' => 'text-emerald-700',
            'badge' => 'bg-emerald-100 text-emerald-700',
        ],
        'moderate' => [
            'card' => 'border-amber-200 bg-amber-50/60',
            'dot' => 'bg-amber-500',
            'text' => 'text-amber-700',
            'badge' => 'bg-amber-100 text-amber-700',
        ],
        'high' => [
            'card' => 'border-rose-200 bg-rose-50/60',
            'dot' => 'bg-rose-500',
            'text' => 'text-rose-700',
            'badge' => 'bg-rose-100 text-rose-700',
        ],
    ];
    $riskStyle = $riskMap[$risk] ?? $riskMap['moderate'];

    $plan = is_array($safe['plan'] ?? null) ? $safe['plan'] : [];
    $morning = is_array($plan['morning'] ?? null) ? $plan['morning'] : [];
    $afternoon = is_array($plan['afternoon'] ?? null) ? $plan['afternoon'] : [];
    $evening = is_array($plan['evening'] ?? null) ? $plan['evening'] : [];
    $avoid = is_array($safe['avoid'] ?? null) ? $safe['avoid'] : [];
@endphp

<article class="ag-card border border-slate-200 bg-white p-4 sm:p-5 rounded-2xl shadow-sm" aria-label="Today's smart AI recommendation">
    <div class="mx-auto w-full max-w-4xl space-y-4">
            @if ($failed)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                    <p class="text-sm text-amber-900 font-semibold">AI recommendation is temporarily unavailable due to API connection or model error. Showing backup advice.</p>
                </div>
            @endif

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

            <header class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 flex items-center gap-1.5">
                    <i data-lucide="sprout" class="w-4 h-4 text-[#2E7D32]"></i>
                    {{ $title }}
                </p>
                <p class="text-xl sm:text-2xl font-extrabold text-slate-900 leading-tight">{{ $safe['action'] ?? 'Check weather before field work today.' }}</p>
                <div class="flex flex-wrap items-center gap-2.5 text-xs">
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-700 px-3 py-1.5 border border-slate-200">
                        <i data-lucide="gauge" class="w-3.5 h-3.5"></i>
                        Farm Score: {{ $safe['score'] ?? 5 }}/10
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 text-emerald-700 px-3 py-1.5 border border-emerald-200">
                        <i data-lucide="brain" class="w-3.5 h-3.5"></i>
                        AI Confidence: {{ $safe['confidence'] ?? 'Low' }}
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 font-semibold {{ $riskStyle['badge'] }}">
                        ⚠️ Risk: {{ $riskLabel }}
                    </span>
                </div>
            </header>

            <div class="space-y-4">
                <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <i data-lucide="clock-3" class="w-4 h-4 text-[#2E7D32]"></i>
                        Today&rsquo;s Plan
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-900 mb-1">🌅 Morning</p>
                            <p class="text-sm text-slate-700 leading-relaxed">{{ !empty($morning) ? implode('. ', $morning) . '.' : 'No action needed.' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-900 mb-1">☀️ Afternoon</p>
                            <p class="text-sm text-slate-700 leading-relaxed">{{ !empty($afternoon) ? implode('. ', $afternoon) . '.' : 'No action needed.' }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <p class="text-sm font-semibold text-slate-900 mb-1">🌙 Evening</p>
                            <p class="text-sm text-slate-700 leading-relaxed">{{ !empty($evening) ? implode('. ', $evening) . '.' : 'No action needed.' }}</p>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 shadow-sm">
                        <p class="text-sm font-semibold text-amber-800 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="triangle-alert" class="w-4 h-4"></i>
                            Avoid
                        </p>
                        <ul class="space-y-1">
                            @forelse ($avoid as $item)
                                <li class="text-sm text-amber-900 leading-relaxed">• {{ $item }}</li>
                            @empty
                                <li class="text-sm text-amber-800 leading-relaxed">• No critical mistakes flagged today.</li>
                            @endforelse
                        </ul>
                    </div>

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 shadow-sm">
                        <p class="text-sm font-semibold text-emerald-800 mb-1.5 flex items-center gap-1.5">
                            <i data-lucide="droplets" class="w-4 h-4"></i>
                            💧 Water Strategy
                        </p>
                        <p class="text-sm text-emerald-900 leading-relaxed">{{ $safe['water'] ?? 'Maintain normal irrigation and monitor rain updates.' }}</p>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-slate-50/80 p-3">
                    <details>
                        <summary class="cursor-pointer text-xs sm:text-sm font-semibold text-slate-700">Why this recommendation?</summary>
                        <p class="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">{{ $safe['why'] ?? 'Weather and crop-stage data are limited today.' }}</p>
                    </details>
                </section>
            </div>
        </div>
</article>
