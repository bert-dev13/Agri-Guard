{{-- Authenticated app navbar — resources/css/user/user-navbar.css, resources/js/user/user-navbar.js --}}

<header id="navbar" class="user-navbar navbar-modern">
    <div class="user-navbar-inner">
        <a href="{{ route('dashboard') }}" class="user-navbar-logo">
            <img src="{{ asset('images/agriguard-logo.png') }}" alt="AGRIGUARD" class="user-navbar-logo-img" />
            <span class="user-navbar-logo-text">AGRIGUARD</span>
        </a>

        <nav class="user-navbar-nav" aria-label="Main">
            <a href="{{ route('dashboard') }}" class="user-navbar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="user-navbar-link-icon"><i data-lucide="layout-dashboard" aria-hidden="true"></i></span>
                <span>Dashboard</span>
            </a>
            <a href="{{ route('weather-details') }}" class="user-navbar-link {{ request()->routeIs('weather-details') ? 'active' : '' }}">
                <span class="user-navbar-link-icon"><i data-lucide="cloud-sun" aria-hidden="true"></i></span>
                <span>Weather</span>
            </a>
            <a href="{{ route('crop-progress.index') }}" class="user-navbar-link {{ request()->routeIs('crop-progress.*') ? 'active' : '' }}">
                <span class="user-navbar-link-icon"><i data-lucide="sprout" aria-hidden="true"></i></span>
                <span>Crop Progress</span>
            </a>
        </nav>

        <div class="user-navbar-user">
            <div class="relative">
                <button type="button" id="user-menu-btn" class="user-navbar-btn" aria-expanded="false" aria-haspopup="true" aria-label="User menu">
                    <span class="user-navbar-btn-icon"><i data-lucide="user" class="w-4 h-4"></i></span>
                    <span class="user-navbar-btn-name">{{ Auth::user()->name }}</span>
                    <span class="user-navbar-btn-chevron"><i data-lucide="chevron-down" class="w-4 h-4"></i></span>
                </button>
                <div id="user-dropdown" class="user-navbar-dropdown">
                    <div class="user-navbar-dropdown-header">
                        <p class="user-navbar-dropdown-header-name">{{ Auth::user()->name }}</p>
                        <p class="user-navbar-dropdown-header-email">{{ Auth::user()->email }}</p>
                    </div>
                    <a href="{{ route('settings') }}" class="user-navbar-dropdown-item">
                        <i data-lucide="settings"></i>
                        Settings
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="user-navbar-dropdown-item">
                            <i data-lucide="log-out"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <button type="button" id="mobile-menu-btn" class="user-navbar-mobile-btn" aria-label="Open menu" aria-expanded="false">
            <svg class="icon-menu" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            <svg class="icon-close" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div id="mobile-menu" class="user-navbar-mobile">
        <div class="user-navbar-mobile-inner">
            <a href="{{ route('dashboard') }}" class="user-navbar-mobile-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon"><i data-lucide="layout-dashboard"></i></span>
                Dashboard
            </a>
            <a href="{{ route('weather-details') }}" class="user-navbar-mobile-link {{ request()->routeIs('weather-details') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon"><i data-lucide="cloud-sun"></i></span>
                Weather
            </a>
            <a href="{{ route('crop-progress.index') }}" class="user-navbar-mobile-link {{ request()->routeIs('crop-progress.*') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon"><i data-lucide="sprout"></i></span>
                Crop Progress
            </a>
            <a href="{{ route('settings') }}" class="user-navbar-mobile-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon"><i data-lucide="settings"></i></span>
                Settings
            </a>
            <div class="user-navbar-mobile-divider">
                <p class="user-navbar-mobile-user">{{ Auth::user()->name }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="user-navbar-mobile-logout">
                        <i data-lucide="log-out"></i>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
