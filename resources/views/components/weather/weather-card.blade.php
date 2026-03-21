@props([
    'title',
    'icon' => 'cloud-sun',
    'subtitle' => null,
    'class' => '',
])

<section {{ $attributes->merge(['class' => 'weather-panel ' . $class]) }}>
    <header class="weather-panel-head">
        <h2 class="weather-panel-title">
            <i data-lucide="{{ $icon }}" class="w-4 h-4" aria-hidden="true"></i>
            {{ $title }}
        </h2>
        @if ($subtitle)
            <p class="weather-panel-subtitle">{{ $subtitle }}</p>
        @endif
    </header>
    {{ $slot }}
</section>
