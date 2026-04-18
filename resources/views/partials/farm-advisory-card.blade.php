{{--
    3-Day Outlook card — fixed layout (crop impact · possible loss · flood · outlook).
    Expects $risk_snapshot from FarmRiskSnapshotService::buildFromWeather().
--}}
@php
    $rs = is_array($risk_snapshot ?? null) ? $risk_snapshot : [];
    $impactToneKey = strtolower((string) ($rs['crop_impact_tone'] ?? 'unknown'));
    $wxOutlookBadgeClass = match ($impactToneKey) {
        'minimal', 'low' => 'bg-emerald-100 text-emerald-900 ring-1 ring-emerald-200/90',
        'moderate' => 'bg-amber-100 text-amber-950 ring-1 ring-amber-200/90',
        'high' => 'bg-orange-100 text-orange-950 ring-1 ring-orange-200/90',
        'severe' => 'bg-rose-100 text-rose-950 ring-1 ring-rose-200/90',
        default => 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/90',
    };
    $snapshotImpactLabel = (string) ($rs['crop_impact_label'] ?? '—');
    $snapshotLossRange = (string) ($rs['possible_loss_range'] ?? ($rs['estimated_crop_loss'] ?? '—'));
    $snapshotFlood = (string) ($rs['flood_risk_display'] ?? $rs['flood_risk_level'] ?? 'Unknown');
    $snapshotOutlook = (string) ($rs['three_day_outlook'] ?? $rs['three_day_effect'] ?? '');
    $snapshotReco = (string) ($rs['recommended_action'] ?? '');
    $snapshotDisclaimer = (string) ($rs['advisory_disclaimer'] ?? '');
    $wxFloodKey = strtolower((string) strtok(trim($snapshotFlood), ' '));
    $wxFloodCardClass = match ($wxFloodKey) {
        'low' => 'border-emerald-100 bg-emerald-50/80',
        'moderate' => 'border-amber-100 bg-amber-50/80',
        'high' => 'border-rose-100 bg-rose-50/80',
        default => 'border-slate-100 bg-slate-50/80',
    };
    $showRecommended = (bool) ($showRecommended ?? false);
@endphp
<section class="ag-card weather-impact-card weather-impact-card--compact border border-slate-200 bg-white p-3 shadow-sm sm:p-3.5 {{ $wrapperClass ?? '' }}" aria-label="Crop impact and three-day outlook">
    <div class="weather-impact-min__head flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-2">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <h2 class="weather-impact-min__title inline-flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-[0.1em] text-slate-800">
                <i data-lucide="triangle-alert" class="h-4 w-4 shrink-0 text-amber-600"></i>
                3-Day Outlook
            </h2>
        </div>
        @isset($detailsLinkHref)
            <a href="{{ $detailsLinkHref }}" class="inline-flex shrink-0 items-center gap-0.5 text-[11px] font-semibold text-sky-700 hover:text-sky-800" aria-label="{{ $detailsLinkAria ?? 'Open weather details' }}">
                <span class="sr-only">{{ $detailsLinkSr ?? 'Full weather details' }}</span>
                <span class="text-[11px] font-semibold text-sky-700">Details</span>
                <i data-lucide="chevron-right" class="h-3.5 w-3.5" aria-hidden="true"></i>
            </a>
        @endisset
    </div>

    <div class="mt-3 grid grid-cols-4 gap-1.5 sm:gap-2">
        <article class="flex min-h-0 min-w-0 flex-col rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50/90 to-white p-1.5 shadow-sm sm:p-2">
            <div class="flex flex-col items-center gap-0.5 text-emerald-800 sm:flex-row sm:justify-center sm:gap-1">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-500/15 sm:h-7 sm:w-7" aria-hidden="true">
                    <i data-lucide="sprout" class="h-3.5 w-3.5 sm:h-4 sm:w-4"></i>
                </span>
                <span class="text-center text-[8px] font-bold uppercase leading-tight tracking-wide sm:text-[9px]">Crop impact</span>
            </div>
            <p class="mt-1.5 flex flex-1 items-center justify-center px-0.5 text-center">
                <span class="max-w-full truncate rounded-full px-1.5 py-0.5 text-[10px] font-extrabold sm:px-2 sm:py-1 sm:text-[11px] {{ $wxOutlookBadgeClass }}">{{ $snapshotImpactLabel }}</span>
            </p>
        </article>

        <article class="flex min-h-0 min-w-0 flex-col rounded-xl border border-violet-100 bg-gradient-to-br from-violet-50/90 to-white p-1.5 shadow-sm sm:p-2">
            <div class="flex flex-col items-center gap-0.5 text-violet-800 sm:flex-row sm:justify-center sm:gap-1">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-violet-500/15 sm:h-7 sm:w-7" aria-hidden="true">
                    <i data-lucide="percent" class="h-3.5 w-3.5 sm:h-4 sm:w-4"></i>
                </span>
                <span class="text-center text-[8px] font-bold uppercase leading-tight tracking-wide sm:text-[9px]">Possible loss</span>
            </div>
            <p class="mt-1 flex flex-1 flex-col items-center justify-center text-center">
                <span class="text-[13px] font-semibold tabular-nums leading-none text-violet-950 sm:text-base">{{ $snapshotLossRange }}</span>
                <span class="mt-0.5 text-[8px] font-semibold uppercase tracking-wide text-violet-700/90">Range</span>
            </p>
        </article>

        <article class="flex min-h-0 min-w-0 flex-col rounded-xl border p-1.5 shadow-sm sm:p-2 {{ $wxFloodCardClass }}">
            <div class="flex flex-col items-center gap-0.5 text-slate-800 sm:flex-row sm:justify-center sm:gap-1">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-white/80 shadow-sm ring-1 ring-black/5 sm:h-7 sm:w-7" aria-hidden="true">
                    <i data-lucide="waves" class="h-3.5 w-3.5 text-sky-700 sm:h-4 sm:w-4"></i>
                </span>
                <span class="text-center text-[8px] font-bold uppercase leading-tight tracking-wide text-slate-600 sm:text-[9px]">Flood risk</span>
            </div>
            <p class="mt-1 flex flex-1 items-center justify-center px-0.5 text-center text-[12px] font-extrabold leading-tight text-slate-900 sm:text-sm">{{ $snapshotFlood }}</p>
        </article>

        <article class="flex min-h-0 min-w-0 flex-col rounded-xl border border-sky-100 bg-gradient-to-br from-sky-50/90 to-white p-1.5 shadow-sm sm:p-2">
            <div class="flex flex-col items-center gap-0.5 text-sky-900 sm:flex-row sm:justify-center sm:gap-1">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-sky-500/15 sm:h-7 sm:w-7" aria-hidden="true">
                    <i data-lucide="cloud-sun" class="h-3.5 w-3.5 sm:h-4 sm:w-4"></i>
                </span>
                <span class="text-center text-[8px] font-bold uppercase leading-tight tracking-wide sm:text-[9px]">Outlook</span>
            </div>
            <p class="mt-1 line-clamp-4 flex-1 text-[9px] font-medium leading-snug text-slate-800 sm:line-clamp-5 sm:text-[10px]">{{ $snapshotOutlook }}</p>
        </article>
    </div>

    @if ($showRecommended && $snapshotReco !== '')
        <div class="mt-2 flex gap-2 rounded-xl border border-emerald-100 bg-gradient-to-r from-emerald-50/90 to-teal-50/50 p-2 ring-1 ring-emerald-100/80">
            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center self-start rounded-lg bg-emerald-500/15 text-emerald-800 ring-1 ring-emerald-200/50" aria-hidden="true">
                <i data-lucide="shield-check" class="h-4 w-4"></i>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-[9px] font-bold uppercase tracking-wide text-emerald-900/85">Recommended action</p>
                <p class="mt-0.5 text-[12px] font-semibold leading-snug text-emerald-950">{{ $snapshotReco }}</p>
            </div>
        </div>
    @endif

    @if ($snapshotDisclaimer !== '')
        <div class="mt-3 flex gap-2.5 border-t border-slate-100 pt-3" role="note">
            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center self-start rounded-full bg-slate-200/70 text-slate-600 ring-1 ring-slate-300/40" aria-hidden="true">
                <i data-lucide="info" class="h-3.5 w-3.5"></i>
            </span>
            <span class="min-w-0 flex-1 pt-0.5 text-[10px] leading-relaxed text-slate-600">{{ $snapshotDisclaimer }}</span>
        </div>
    @endif
</section>
