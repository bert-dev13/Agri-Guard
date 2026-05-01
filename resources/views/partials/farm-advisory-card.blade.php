{{--
    3-Day Weather Outlook — factual meteorology + neutral field advisory only.
    Expects $weather_outlook from ThreeDayWeatherOutlookService::build().
--}}
@php
    $wo = is_array($weather_outlook ?? null) ? $weather_outlook : [];
    $woSummary = (string) ($wo['day_summary'] ?? '');
    $woRain = (string) ($wo['rain_probability'] ?? '—');
    $woTempTrend = (string) ($wo['temperature_trend'] ?? '—');
    $woWind = (string) ($wo['wind_condition'] ?? '—');
    $woFieldLines = $wo['field_advisory_lines'] ?? [];
    if (! is_array($woFieldLines)) {
        $woFieldLines = [];
    }
    $woFieldLines = array_values(array_filter($woFieldLines, static fn ($l) => is_string($l) && trim($l) !== ''));
@endphp
<section class="ag-card weather-outlook-card border border-slate-200 bg-white p-3 shadow-sm sm:p-3.5 {{ $wrapperClass ?? '' }}" aria-label="Three day weather outlook">
    <div class="weather-outlook-card__head flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-2">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <h2 class="inline-flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-[0.1em] text-slate-800">
                <i data-lucide="cloud-sun" class="h-4 w-4 shrink-0 text-sky-600"></i>
                3-Day Weather Outlook
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

    <div class="mt-3 space-y-3">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Day 1–3 summary</p>
            <p class="mt-1 text-[13px] font-semibold leading-snug text-slate-900 sm:text-sm">{{ $woSummary !== '' ? $woSummary : 'Outlook summary unavailable.' }}</p>
        </div>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
            <article class="rounded-xl border border-sky-100 bg-sky-50/80 px-2.5 py-2">
                <div class="flex items-center gap-1.5 text-sky-900">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/80 shadow-sm ring-1 ring-black/5" aria-hidden="true">
                        <i data-lucide="cloud-rain" class="h-3.5 w-3.5 text-sky-700"></i>
                    </span>
                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-600">Rain probability</span>
                </div>
                <p class="mt-1.5 text-sm font-extrabold tabular-nums text-slate-900">{{ $woRain }}</p>
            </article>
            <article class="rounded-xl border border-amber-100 bg-amber-50/80 px-2.5 py-2">
                <div class="flex items-center gap-1.5 text-amber-950">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/80 shadow-sm ring-1 ring-black/5" aria-hidden="true">
                        <i data-lucide="thermometer" class="h-3.5 w-3.5 text-amber-700"></i>
                    </span>
                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-600">Temperature trend</span>
                </div>
                <p class="mt-1.5 text-sm font-extrabold text-slate-900">{{ $woTempTrend }}</p>
            </article>
            <article class="rounded-xl border border-violet-100 bg-violet-50/80 px-2.5 py-2">
                <div class="flex items-center gap-1.5 text-violet-950">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/80 shadow-sm ring-1 ring-black/5" aria-hidden="true">
                        <i data-lucide="wind" class="h-3.5 w-3.5 text-violet-700"></i>
                    </span>
                    <span class="text-[9px] font-bold uppercase tracking-wide text-slate-600">Wind condition</span>
                </div>
                <p class="mt-1.5 text-sm font-extrabold text-slate-900">{{ $woWind }}</p>
            </article>
        </div>

        <div class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50/90 to-teal-50/40 px-3 py-2.5 ring-1 ring-emerald-100/70">
            <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-900/85">Field advisory</p>
            @if ($woFieldLines !== [])
                <ul class="mt-1.5 list-disc space-y-1 pl-4 text-[12px] font-medium leading-snug text-emerald-950">
                    @foreach ($woFieldLines as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            @else
                <p class="mt-1.5 text-[12px] font-medium text-emerald-950/90">General field checks and clear drainage are good practices during any forecast period.</p>
            @endif
        </div>

        <p class="text-[10px] font-medium italic leading-relaxed text-slate-500">Informational forecast – For planning purposes only.</p>
    </div>
</section>
