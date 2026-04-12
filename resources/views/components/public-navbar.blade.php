{{-- Public navbar: landing + auth guest pages. Styles: resources/css/public/navbar.css, scripts: resources/js/public/navbar.js --}}
@php
    $navLinkClass = 'nav-link-modern touch-manipulation';
@endphp
<header id="navbar" class="navbar-modern w-full max-w-full min-w-0 overflow-x-clip">
    <div class="navbar-container max-w-7xl mx-auto px-3 min-[375px]:px-3.5 sm:px-6 lg:px-8 min-w-0">
        <div class="navbar-inner flex w-full min-w-0 items-center justify-between gap-2 sm:gap-3 lg:gap-4 lg:h-[4.5rem]">
            <a href="{{ url('/') }}" class="navbar-logo touch-manipulation flex min-w-0 items-center gap-1.5 sm:gap-2 lg:gap-2.5">
                <span class="navbar-logo-img-wrap shrink-0">
                    <img
                        src="{{ asset('images/agriguard-logo.png') }}"
                        alt=""
                        aria-hidden="true"
                        class="navbar-logo-mark h-8 w-auto max-h-8 max-w-full object-contain sm:h-9 sm:max-h-9 lg:h-10 lg:max-h-10"
                        decoding="async"
                    />
                </span>
                <span class="navbar-logo-text font-bold text-[#00809D] tracking-tight max-sm:truncate">AGRIGUARD</span>
            </a>

            {{-- Large screens (≥1024px): horizontal nav — phones & tablets use hamburger --}}
            <nav class="navbar-nav navbar-nav-desktop hidden lg:flex flex-wrap items-center justify-center gap-2 sm:gap-3 lg:gap-4 xl:gap-6 shrink-0 min-w-0" aria-label="Primary">
                <a href="{{ url('/') }}" class="{{ $navLinkClass }}" data-nav="home">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5.5v-5.25h-3V21H5a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Home</span>
                </a>
                <a href="{{ url('/#about') }}" class="{{ $navLinkClass }}" data-nav="about">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M6 19.5c0-3.038 2.239-5.5 6-5.5s6 2.462 6 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span>About</span>
                </a>
                <a href="{{ url('/#how-it-works') }}" class="{{ $navLinkClass }}" data-nav="how-it-works">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4.5 8.5 9 5.5l4 3 6.5-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4.5 14.5 9 11.5l4 3 6.5-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>How It Works</span>
                </a>
                <a href="{{ url('/#features') }}" class="{{ $navLinkClass }}" data-nav="features">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 4.5h10L19.5 9 12 19.5 4.5 9 7 4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <path d="M9.5 9.5 12 6.5l2.5 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Features</span>
                </a>
            </nav>

            <div class="navbar-right navbar-right-desktop hidden lg:flex min-w-0 shrink-0 items-center justify-end gap-1.5 sm:gap-2 lg:gap-3">
                <div class="navbar-auth flex items-center gap-1.5 min-[400px]:gap-2 sm:gap-2.5">
                    <a href="{{ url('/login') }}" class="navbar-login touch-manipulation whitespace-nowrap">Login</a>
                    <a href="{{ url('/register') }}" class="navbar-cta touch-manipulation whitespace-nowrap">Get Started</a>
                </div>
            </div>

            {{-- Mobile (≤767px): hamburger --}}
            <button
                type="button"
                id="public-navbar-menu-btn"
                class="navbar-mobile-toggle lg:hidden touch-manipulation"
                aria-expanded="false"
                aria-controls="public-navbar-mobile-panel"
                aria-label="Open menu"
            >
                <span class="navbar-mobile-toggle-icons" aria-hidden="true">
                    <span class="navbar-mobile-toggle-layer navbar-mobile-toggle-layer--menu">
                        <i data-lucide="menu" class="navbar-lucide-icon"></i>
                    </span>
                    <span class="navbar-mobile-toggle-layer navbar-mobile-toggle-layer--close">
                        <i data-lucide="x" class="navbar-lucide-icon"></i>
                    </span>
                </span>
            </button>
        </div>

        {{-- Mobile slide-down panel --}}
        <div
            id="public-navbar-mobile-panel"
            class="navbar-mobile-panel lg:hidden"
            aria-hidden="true"
            inert
        >
            <div class="navbar-mobile-panel-inner">
                <nav class="navbar-mobile-nav" aria-label="Primary">
                    <a href="{{ url('/') }}" class="{{ $navLinkClass }} nav-link-modern--mobile" data-nav="home">
                        <span class="nav-link-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5.5v-5.25h-3V21H5a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Home</span>
                    </a>
                    <a href="{{ url('/#about') }}" class="{{ $navLinkClass }} nav-link-modern--mobile" data-nav="about">
                        <span class="nav-link-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M6 19.5c0-3.038 2.239-5.5 6-5.5s6 2.462 6 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <span>About</span>
                    </a>
                    <a href="{{ url('/#how-it-works') }}" class="{{ $navLinkClass }} nav-link-modern--mobile" data-nav="how-it-works">
                        <span class="nav-link-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M4.5 8.5 9 5.5l4 3 6.5-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M4.5 14.5 9 11.5l4 3 6.5-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>How It Works</span>
                    </a>
                    <a href="{{ url('/#features') }}" class="{{ $navLinkClass }} nav-link-modern--mobile" data-nav="features">
                        <span class="nav-link-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none">
                                <path d="M7 4.5h10L19.5 9 12 19.5 4.5 9 7 4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                <path d="M9.5 9.5 12 6.5l2.5 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>Features</span>
                    </a>
                </nav>
                <div class="navbar-mobile-actions">
                    <a href="{{ url('/login') }}" class="navbar-login navbar-login--mobile touch-manipulation">Login</a>
                    <a href="{{ url('/register') }}" class="navbar-cta navbar-cta--mobile touch-manipulation">Get Started</a>
                </div>
            </div>
        </div>
    </div>
</header>
