<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AGRIGUARD')</title>
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
