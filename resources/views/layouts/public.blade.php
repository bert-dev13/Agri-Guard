<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#00809D">
    <meta name="description" content="AGRIGUARD — AI-driven smart agriculture system using predictive weather analytics and machine learning.">
    <title>@yield('title', 'AGRIGUARD')</title>
    {{-- Preload the LCP hero image so it starts downloading before the JS bundle is parsed. --}}
    <link rel="preload" as="image" href="{{ asset('images/hero_image.png') }}" fetchpriority="high">
    <link rel="dns-prefetch" href="//unpkg.com">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class', 'min-h-screen flex flex-col bg-[#F8FAFC]')">
    <x-public-navbar />

    <div class="main-content flex flex-1 flex-col min-h-0 w-full">
        @yield('content')
    </div>

    <x-footer />

    @stack('scripts')
</body>
</html>
