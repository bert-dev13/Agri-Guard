{{-- Admin shell: flex sidebar (lg+) + mobile drawer — resources/css/admin/admin-navbar.css, resources/js/admin/admin-navbar.js --}}

<div id="admin-sidebar-backdrop" class="admin-sidebar-backdrop" aria-hidden="true"></div>

<aside id="admin-sidebar" class="admin-sidebar shrink-0" aria-label="Admin navigation">
    <div class="admin-sidebar__header">
        <a href="{{ route('admin.dashboard') }}" class="admin-sidebar__brand" aria-label="AGRIGUARD Admin home">
            <img src="{{ asset('images/agriguard-logo.png') }}" alt="" width="40" height="40" class="admin-sidebar__logo" />
            <span class="admin-sidebar__brand-text">
                <span class="admin-sidebar__brand-name">AGRIGUARD</span>
                <span class="admin-sidebar__brand-badge">Admin</span>
            </span>
        </a>
        <button type="button" id="admin-sidebar-collapse" class="admin-sidebar__collapse-btn" aria-pressed="false" aria-label="Collapse sidebar" title="Collapse sidebar">
            <i data-lucide="panel-left-close" class="admin-sidebar__collapse-icon admin-sidebar__collapse-icon--expand"></i>
            <i data-lucide="panel-left-open" class="admin-sidebar__collapse-icon admin-sidebar__collapse-icon--collapse"></i>
        </button>
    </div>

    <nav class="admin-sidebar__nav" aria-label="Primary">
        <a href="{{ route('admin.dashboard') }}"
           class="admin-sidebar__link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}"
           title="Dashboard"
           data-tooltip="Dashboard">
            <span class="admin-sidebar__link-icon" aria-hidden="true"><i data-lucide="layout-dashboard"></i></span>
            <span class="admin-sidebar__link-label">Dashboard</span>
        </a>
        <a href="{{ route('admin.users.index') }}"
           class="admin-sidebar__link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}"
           title="User management"
           data-tooltip="Users">
            <span class="admin-sidebar__link-icon" aria-hidden="true"><i data-lucide="users"></i></span>
            <span class="admin-sidebar__link-label">Users</span>
        </a>
        <a href="{{ route('admin.farms.index') }}"
           class="admin-sidebar__link {{ request()->routeIs('admin.farms.*') ? 'is-active' : '' }}"
           title="Farm monitoring"
           data-tooltip="Farms">
            <span class="admin-sidebar__link-icon" aria-hidden="true"><i data-lucide="tractor"></i></span>
            <span class="admin-sidebar__link-label">Farms</span>
        </a>
        <a href="{{ route('admin.analytics.index') }}"
           class="admin-sidebar__link {{ request()->routeIs('admin.analytics.*') ? 'is-active' : '' }}"
           title="Analytics"
           data-tooltip="Analytics">
            <span class="admin-sidebar__link-icon" aria-hidden="true"><i data-lucide="chart-column"></i></span>
            <span class="admin-sidebar__link-label">Analytics</span>
        </a>
        <a href="{{ route('admin.account-settings.index') }}"
           class="admin-sidebar__link {{ request()->routeIs('admin.account-settings.*') ? 'is-active' : '' }}"
           title="Account settings"
           data-tooltip="Account Settings">
            <span class="admin-sidebar__link-icon" aria-hidden="true"><i data-lucide="settings-2"></i></span>
            <span class="admin-sidebar__link-label">Account Settings</span>
        </a>
    </nav>

    <div class="admin-sidebar__footer">
        <form method="POST" action="{{ route('logout') }}" class="admin-sidebar__logout-form">
            @csrf
            <button type="submit" class="admin-sidebar__logout-btn" title="Log out" data-tooltip="Log out">
                <span class="admin-sidebar__logout-icon" aria-hidden="true"><i data-lucide="log-out"></i></span>
                <span class="admin-sidebar__logout-label">Log out</span>
            </button>
        </form>
    </div>
</aside>
