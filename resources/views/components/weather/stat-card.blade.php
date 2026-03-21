@props([
    'label',
    'value',
    'icon' => 'circle',
    'tone' => 'blue',
])

<article {{ $attributes->merge(['class' => 'metric-card metric-card--' . $tone]) }}>
    <span class="metric-card-icon" aria-hidden="true">
        <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
    </span>
    <p class="metric-card-label">{{ $label }}</p>
    <p class="metric-card-value">{{ $value }}</p>
</article>
