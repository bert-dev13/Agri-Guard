@props([
    'recommendation' => [],
    'failed' => false,
])

@php
    $safe = is_array($recommendation) ? $recommendation : [];

    $risk = strtolower((string) ($safe['risk'] ?? 'moderate'));
    $riskLabel = $risk === 'high' ? 'High' : ($risk === 'low' ? 'Low' : 'Moderate');

    $riskBadge = match ($risk) {
        'low' => 'dash-smart__badge--risk-low',
        'high' => 'dash-smart__badge--risk-high',
        default => 'dash-smart__badge--risk-mid',
    };

    $confDisplay = (string) ($safe['confidence'] ?? 'Low');
    $conf = strtolower($confDisplay);
    $confBadge = match ($conf) {
        'high' => 'dash-smart__badge--conf-high',
        'medium' => 'dash-smart__badge--conf-mid',
        default => 'dash-smart__badge--conf-low',
    };

    $plan = is_array($safe['plan'] ?? null) ? $safe['plan'] : [];
    $morning = is_array($plan['morning'] ?? null) ? $plan['morning'] : [];
    $afternoon = is_array($plan['afternoon'] ?? null) ? $plan['afternoon'] : [];
    $evening = is_array($plan['evening'] ?? null) ? $plan['evening'] : [];
    $avoid = is_array($safe['avoid'] ?? null) ? $safe['avoid'] : [];

    $shorten = function (array $lines, int $max = 120): string {
        if ($lines === []) {
            return 'All clear.';
        }
        $text = implode(' ', array_map(static fn ($s) => trim((string) $s), $lines));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return strlen($text) > $max ? substr($text, 0, $max - 1) . '…' : $text;
    };

    $aiStatus = strtolower((string) ($safe['ai_status'] ?? 'failed'));
    $aiError = trim((string) ($safe['ai_error'] ?? ''));
@endphp

<article class="ag-card dash-smart" data-dash-card aria-label="Today's smart recommendation">
    <div class="dash-smart__debug">
        <p class="text-xs font-semibold text-slate-700">
            @if ($aiStatus === 'success')
                <span class="text-emerald-700">AI Smart Advisory: Active</span>
            @else
                <span class="text-rose-700">AI Smart Advisory: Unavailable</span>
            @endif
        </p>
        @if ($aiStatus !== 'success' && $aiError !== '')
            <p class="text-xs text-slate-600 mt-1">Error: {{ $aiError }}</p>
        @endif
    </div>

    @if ($failed)
        <div class="dash-smart__notice" role="status">
            <i data-lucide="wifi-off" class="dash-smart__notice-icon" aria-hidden="true"></i>
            <p>Tip unavailable — showing safe defaults.</p>
        </div>
    @endif

    <div class="dash-smart__head">
        <span class="dash-smart__chip" aria-hidden="true"><span class="dash-smart__chip-emoji">💡</span> Smart action</span>
        <div class="dash-smart__badges">
            <span class="dash-smart__badge {{ $confBadge }}">
                <i data-lucide="sparkles" class="dash-smart__badge-ic" aria-hidden="true"></i>
                {{ $confDisplay }}
            </span>
            <span class="dash-smart__badge {{ $riskBadge }}">{{ $riskLabel }} risk</span>
        </div>
    </div>

    <p class="dash-smart__action">{{ $safe['action'] ?? 'Check weather before field work today.' }}</p>

    <p class="dash-smart__plan-label">Today&rsquo;s plan</p>
    <div class="dash-plan-row" role="list">
        <div class="dash-plan-mini" role="listitem">
            <span class="dash-plan-mini__ico" aria-hidden="true">🌅</span>
            <span class="dash-plan-mini__title">Morning</span>
            <p class="dash-plan-mini__txt">{{ $shorten($morning) }}</p>
        </div>
        <div class="dash-plan-mini" role="listitem">
            <span class="dash-plan-mini__ico" aria-hidden="true">☀️</span>
            <span class="dash-plan-mini__title">Afternoon</span>
            <p class="dash-plan-mini__txt">{{ $shorten($afternoon) }}</p>
        </div>
        <div class="dash-plan-mini" role="listitem">
            <span class="dash-plan-mini__ico" aria-hidden="true">🌙</span>
            <span class="dash-plan-mini__title">Evening</span>
            <p class="dash-plan-mini__txt">{{ $shorten($evening) }}</p>
        </div>
    </div>

    <div class="dash-split">
        <div class="dash-split__card dash-split__card--avoid">
            <div class="dash-split__head">
                <i data-lucide="shield-alert" class="dash-split__ic" aria-hidden="true"></i>
                Avoid
            </div>
            <ul class="dash-split__list">
                @forelse ($avoid as $item)
                    <li>{{ $item }}</li>
                @empty
                    <li>Nothing critical today.</li>
                @endforelse
            </ul>
        </div>
        <div class="dash-split__card dash-split__card--water">
            <div class="dash-split__head">
                <i data-lucide="droplets" class="dash-split__ic" aria-hidden="true"></i>
                Water
            </div>
            <p class="dash-split__body">{{ $safe['water'] ?? 'Maintain normal irrigation and watch rain.' }}</p>
        </div>
    </div>

    <details class="dash-why">
        <summary>Why this tip?</summary>
        <p>{{ $safe['why'] ?? 'Based on your crop and today’s weather.' }}</p>
    </details>
</article>
