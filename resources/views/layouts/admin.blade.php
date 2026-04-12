<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin – AGRIGUARD')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="admin-panel-body @yield('body-class')">
    <div id="admin-app" class="admin-app flex min-h-screen min-h-0 flex-col lg:flex-row lg:items-start">
        <x-admin-navbar />

        <div class="admin-main-wrap flex min-h-0 min-w-0 flex-1 flex-col">
            <header class="admin-mobile-bar">
                <button type="button" id="admin-mobile-menu-btn" class="admin-mobile-bar__menu" aria-label="Open navigation" aria-expanded="false" aria-controls="admin-sidebar">
                    <i data-lucide="menu" aria-hidden="true"></i>
                </button>
                <a href="{{ route('admin.dashboard') }}" class="admin-mobile-bar__title">AGRIGUARD</a>
                <span class="admin-mobile-bar__spacer" aria-hidden="true"></span>
            </header>
            <main class="@yield('main-class', 'admin-main')">
                <div class="admin-layout-content">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @stack('scripts')
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>
</body>
</html>
