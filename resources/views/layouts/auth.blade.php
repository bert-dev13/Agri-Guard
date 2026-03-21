<!DOCTYPE html>
<html lang="en" class="auth-html">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AGRIGUARD')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class', 'min-h-screen flex flex-col bg-[#F8FAFC] overflow-x-clip auth-layout')">
    @hasSection('auth-shell')
        @yield('auth-shell')
    @else
        <x-public-navbar />

        <main class="main-content flex flex-1 flex-col min-h-0 w-full">
            @yield('content')
        </main>

        <x-footer />
    @endif

    @stack('scripts')
</body>
</html>
