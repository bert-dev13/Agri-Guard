{{-- Public navbar: landing + auth guest pages. Styles: resources/css/public/navbar.css, scripts: resources/js/public/navbar.js --}}
<header id="navbar" class="navbar-modern">
    <div class="navbar-container max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="navbar-inner flex items-center justify-between h-16 lg:h-18">
            <a href="{{ url('/') }}" class="navbar-logo flex items-center gap-2 shrink-0">
                <span class="navbar-logo-img-wrap">
                    <img src="{{ asset('images/agriguard-logo.png') }}" alt="AGRIGUARD" class="h-9 w-auto object-contain sm:h-10" />
                </span>
                <span class="navbar-logo-text text-xl font-bold text-[#00809D] tracking-tight hidden sm:inline">AGRIGUARD</span>
            </a>
            <nav class="navbar-nav hidden lg:flex items-center gap-6">
                <a href="{{ url('/') }}" class="nav-link-modern" data-nav="home">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5.5v-5.25h-3V21H5a1 1 0 0 1-1-1v-9.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Home</span>
                </a>
                <a href="{{ url('/#about') }}" class="nav-link-modern" data-nav="about">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="8" r="3" stroke="currentColor" stroke-width="1.7"/>
                            <path d="M6 19.5c0-3.038 2.239-5.5 6-5.5s6 2.462 6 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <span>About</span>
                </a>
                <a href="{{ url('/#how-it-works') }}" class="nav-link-modern" data-nav="how-it-works">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M4.5 8.5 9 5.5l4 3 6.5-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4.5 14.5 9 11.5l4 3 6.5-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>How It Works</span>
                </a>
                <a href="{{ url('/#features') }}" class="nav-link-modern" data-nav="features">
                    <span class="nav-link-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 4.5h10L19.5 9 12 19.5 4.5 9 7 4.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <path d="M9.5 9.5 12 6.5l2.5 3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span>Features</span>
                </a>
            </nav>
            <div class="navbar-right flex items-center gap-3">
                <div class="navbar-auth hidden lg:flex items-center gap-2">
                    <a href="{{ url('/login') }}" class="navbar-login">Login</a>
                    <a href="{{ url('/register') }}" class="navbar-cta">Register</a>
                </div>
                <button type="button" id="mobile-menu-btn" class="navbar-mobile-btn lg:hidden flex items-center justify-center w-10 h-10 rounded-lg text-slate-600 hover:bg-slate-100 transition-colors" aria-label="Open menu" aria-expanded="false">
                    <svg id="menu-icon" class="navbar-menu-icon w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg id="close-icon" class="navbar-close-icon w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>
    <div id="mobile-menu" class="navbar-mobile overflow-hidden lg:hidden border-t border-slate-100 bg-white">
        <div class="navbar-mobile-inner max-w-7xl mx-auto px-4 py-4 space-y-1">
            <a href="{{ url('/') }}" class="nav-mobile-link">Home</a>
            <a href="{{ url('/#about') }}" class="nav-mobile-link">About</a>
            <a href="{{ url('/#how-it-works') }}" class="nav-mobile-link">How It Works</a>
            <a href="{{ url('/#features') }}" class="nav-mobile-link">Features</a>
            <a href="{{ url('/login') }}" class="nav-mobile-link">Login</a>
            <a href="{{ url('/register') }}" class="nav-mobile-link nav-mobile-cta">Register</a>
        </div>
    </div>
</header>
