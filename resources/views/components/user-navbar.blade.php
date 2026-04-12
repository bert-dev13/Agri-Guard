{{-- Authenticated app top bar — resources/css/user/user-navbar.css, resources/js/user/user-navbar.js --}}
@php
    $displayName = trim((string) (Auth::user()->name ?? ''));
    $parts = $displayName !== '' ? preg_split('/\s+/', $displayName, -1, PREG_SPLIT_NO_EMPTY) : [];
    if (count($parts) >= 2) {
        $initials = strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    } elseif (count($parts) === 1) {
        $initials = strtoupper(mb_substr($parts[0], 0, min(2, mb_strlen($parts[0]))));
    } else {
        $initials = 'U';
    }
@endphp

<header id="navbar" class="user-navbar">
    <div class="user-navbar-inner">
        <div class="user-navbar-start">
            <a href="{{ route('dashboard') }}" class="user-navbar-logo" aria-label="AGRIGUARD — Home">
                <img src="{{ asset('images/agriguard-logo.png') }}" alt="" width="36" height="36" class="user-navbar-logo-img" />
                <span class="user-navbar-logo-text">AGRIGUARD</span>
            </a>
        </div>

        <nav class="user-navbar-nav" aria-label="Primary">
            <a href="{{ route('dashboard') }}" class="user-navbar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" aria-label="Dashboard">
                <span class="user-navbar-link-icon" aria-hidden="true"><i data-lucide="layout-dashboard"></i></span>
                <span class="user-navbar-link-text">Dashboard</span>
            </a>
            <a href="{{ route('weather-details') }}" class="user-navbar-link {{ request()->routeIs('weather-details') ? 'active' : '' }}" aria-label="Weather">
                <span class="user-navbar-link-icon" aria-hidden="true"><i data-lucide="cloud-sun"></i></span>
                <span class="user-navbar-link-text">Weather</span>
            </a>
            <a href="{{ route('rainfall-trends') }}" class="user-navbar-link {{ request()->routeIs('rainfall-trends') ? 'active' : '' }}" aria-label="Rainfall Trends">
                <span class="user-navbar-link-icon" aria-hidden="true"><i data-lucide="cloud-rain-wind"></i></span>
                <span class="user-navbar-link-text">Rainfall Trends</span>
            </a>
            <a href="{{ route('crop-progress.index') }}" class="user-navbar-link {{ request()->routeIs('crop-progress.*') ? 'active' : '' }}" aria-label="Crop">
                <span class="user-navbar-link-icon" aria-hidden="true"><i data-lucide="sprout"></i></span>
                <span class="user-navbar-link-text">Crop</span>
            </a>
            <a href="{{ route('map.index') }}" class="user-navbar-link {{ request()->routeIs('map.*') ? 'active' : '' }}" aria-label="Map">
                <span class="user-navbar-link-icon" aria-hidden="true"><i data-lucide="map"></i></span>
                <span class="user-navbar-link-text">Map</span>
            </a>
            <a href="{{ route('assistant.index') }}" class="user-navbar-link {{ request()->routeIs('assistant.*') ? 'active' : '' }}" aria-label="Assistant">
                <span class="user-navbar-link-icon" aria-hidden="true"><i data-lucide="bot"></i></span>
                <span class="user-navbar-link-text">Assistant</span>
            </a>
        </nav>

        <div class="user-navbar-end">
            <span class="user-navbar-divider" aria-hidden="true"></span>

            <div class="user-navbar-user">
                <div class="user-navbar-menu-wrap">
                    <button type="button" id="user-menu-btn" class="user-navbar-btn" aria-expanded="false" aria-haspopup="menu" aria-controls="user-dropdown" aria-label="Account menu for {{ e($displayName ?: 'user') }}">
                        <span class="user-navbar-avatar" aria-hidden="true">{{ $initials }}</span>
                        <span class="user-navbar-btn-name">{{ $displayName ?: 'Account' }}</span>
                        <span class="user-navbar-btn-chevron" aria-hidden="true"><i data-lucide="chevron-down"></i></span>
                    </button>
                    <div id="user-dropdown" class="user-navbar-dropdown" role="menu" aria-labelledby="user-menu-btn">
                        <div class="user-navbar-dropdown-header">
                            <p class="user-navbar-dropdown-header-name">{{ $displayName ?: 'User' }}</p>
                            <p class="user-navbar-dropdown-header-email">{{ Auth::user()->email }}</p>
                        </div>
                        <a href="{{ route('settings') }}" class="user-navbar-dropdown-item" role="menuitem">
                            <i data-lucide="settings"></i>
                            Settings
                        </a>
                        <div class="user-navbar-dropdown-sep" role="separator"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="user-navbar-dropdown-item user-navbar-dropdown-item--danger" role="menuitem">
                                <i data-lucide="log-out"></i>
                                Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <button type="button" id="mobile-menu-btn" class="user-navbar-mobile-btn" aria-label="Open navigation" aria-expanded="false" aria-controls="mobile-menu">
                <svg class="icon-menu" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg class="icon-close" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div id="mobile-menu" class="user-navbar-mobile">
        <div class="user-navbar-mobile-inner">
            <div class="user-navbar-mobile-user-row">
                <span class="user-navbar-avatar user-navbar-avatar--lg" aria-hidden="true">{{ $initials }}</span>
                <div class="user-navbar-mobile-user-meta">
                    <p class="user-navbar-mobile-user-name">{{ $displayName ?: 'Account' }}</p>
                    <p class="user-navbar-mobile-user-email">{{ Auth::user()->email }}</p>
                </div>
            </div>
            <a href="{{ route('dashboard') }}" class="user-navbar-mobile-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon" aria-hidden="true"><i data-lucide="layout-dashboard"></i></span>
                Dashboard
            </a>
            <a href="{{ route('weather-details') }}" class="user-navbar-mobile-link {{ request()->routeIs('weather-details') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon" aria-hidden="true"><i data-lucide="cloud-sun"></i></span>
                Weather
            </a>
            <a href="{{ route('rainfall-trends') }}" class="user-navbar-mobile-link {{ request()->routeIs('rainfall-trends') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon" aria-hidden="true"><i data-lucide="cloud-rain-wind"></i></span>
                Rainfall Trends
            </a>
            <a href="{{ route('crop-progress.index') }}" class="user-navbar-mobile-link {{ request()->routeIs('crop-progress.*') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon" aria-hidden="true"><i data-lucide="sprout"></i></span>
                Crop progress
            </a>
            <a href="{{ route('map.index') }}" class="user-navbar-mobile-link {{ request()->routeIs('map.*') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon" aria-hidden="true"><i data-lucide="map"></i></span>
                Map
            </a>
            <a href="{{ route('assistant.index') }}" class="user-navbar-mobile-link {{ request()->routeIs('assistant.*') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon" aria-hidden="true"><i data-lucide="bot"></i></span>
                Assistant
            </a>
            <div class="user-navbar-mobile-divider" role="presentation"></div>
            <a href="{{ route('settings') }}" class="user-navbar-mobile-link {{ request()->routeIs('settings') ? 'active' : '' }}">
                <span class="user-navbar-mobile-link-icon" aria-hidden="true"><i data-lucide="settings"></i></span>
                Settings
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="user-navbar-mobile-logout">
                    <i data-lucide="log-out"></i>
                    Log out
                </button>
            </form>
        </div>
    </div>
</header>
