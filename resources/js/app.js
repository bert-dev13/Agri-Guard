import './bootstrap';

// Tiny shared modules that touch the public navbar / landing fold are eager so
// links and scroll behavior work the moment the HTML lands. Everything else is
// dynamically imported on demand below — this keeps the initial bundle small.
import './public/navbar';

// `requestIdle` defers a callback until the browser is free of higher-priority
// work. We use it to schedule per-page module loads after the first paint, so
// pages render their structural HTML/CSS instantly and only then pull JS.
function requestIdle(callback) {
    if (typeof window.requestIdleCallback === 'function') {
        window.requestIdleCallback(callback, { timeout: 1500 });
    } else {
        setTimeout(callback, 200);
    }
}

function loadIf(selector, importer) {
    if (document.querySelector(selector)) {
        requestIdle(() => importer());
    }
}

// Public landing
loadIf('.landing-section-hero', () => import('./public/landing'));

// Auth pages
loadIf('form[action$="/login"]', () => import('./auth/login'));
loadIf('form[action$="/register"]', () => import('./auth/register'));
loadIf('form[action$="/verify-email"]', () => import('./auth/verify-email'));
loadIf('form[action$="/forgot-password"], form[action$="/reset-password"]', () => import('./auth/forgot-password'));

// Authenticated user shell + per-page modules
//  - `user-navbar.js` handles the dropdown / mobile menu, so it must load on every authenticated page.
//  - Per-page modules use unique class hooks added by `@section('body-class', ...)` so each
//    feature module only ships on the page that actually needs it.
loadIf('.user-navbar', () => import('./user/user-navbar'));
loadIf('.dashboard-home-page-hero', () => import('./user/dashboard'));
loadIf('.weather-page', () => import('./user/weather'));
loadIf('.weather-page', () => import('./user/weather-details'));
loadIf('.rainfall-page', () => import('./user/rainfall-trends'));
loadIf('.crop-progress-page', () => import('./user/crop-progress'));
loadIf('.settings-page', () => import('./user/settings'));
loadIf('.assistant-page', () => import('./user/assistant'));

// Heavy / large modules (Leaflet / Turf / Lucide on map) — kept dynamic so unused pages never download them.
loadIf('#farm-map-page', () => import('./user/farm-map'));
loadIf('#structures-page', () => import('./user/structures'));

// Admin shell + per-page modules
loadIf('.admin-panel-body', () => import('./admin/admin-navbar'));
loadIf('.admin-dashboard-page', () => import('./admin/dashboard'));
loadIf('.admin-users-page', () => import('./admin/user-management'));
loadIf('.admin-farms-page', () => import('./admin/farm-monitoring'));
loadIf('.admin-analytics-page', () => import('./admin/analytics'));
