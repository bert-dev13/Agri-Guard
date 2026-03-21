<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AGRIGUARD')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="@yield('body-class')">
    <x-user-navbar />

    <main class="@yield('main-class', 'user-main')">
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>
