@props([
    'day',
    'icon' => 'sun',
    'condition' => 'Clear',
    'high' => '--',
    'low' => '--',
    'rain' => '0%',
    'rainy' => false,
])

<article {{ $attributes->merge(['class' => 'forecast-row ' . ($rainy ? 'forecast-row--rainy' : '')]) }}>
    <p class="forecast-row-day">{{ $day }}</p>
    <span class="forecast-row-icon" aria-hidden="true">
        <i data-lucide="{{ $icon }}" class="w-4 h-4"></i>
    </span>
    <div class="forecast-row-meta">
        <p class="forecast-row-condition">{{ $condition }}</p>
        <p class="forecast-row-temp">{{ $high }}° / {{ $low }}°</p>
    </div>
    <p class="forecast-row-rain">Rain {{ $rain }}</p>
</article>
