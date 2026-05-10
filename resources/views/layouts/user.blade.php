<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#00809D">
    <title>@yield('title', 'AGRIGUARD')</title>
    <link rel="dns-prefetch" href="//unpkg.com">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class')">
    <x-user-navbar />

    <main class="@yield('main-class', 'user-main')">
        @yield('content')
    </main>

    @stack('scripts')
    {{-- Lucide loaded deferred so it never blocks the dashboard's first paint. Re-renders icons after page mounts and on partial updates via window.refreshLucideIcons(). --}}
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js" defer></script>
    <script defer>
        (function () {
            function paintIcons() {
                if (typeof window.lucide !== 'undefined' && typeof window.lucide.createIcons === 'function') {
                    window.lucide.createIcons();
                }
            }
            window.refreshLucideIcons = paintIcons;
            window.addEventListener('load', paintIcons);
        })();
    </script>
</body>
</html>
