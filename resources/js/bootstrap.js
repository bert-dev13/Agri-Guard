import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/** Align with Laravel CSRF for same-origin requests (session + XSRF cookie stay in sync). */
(function () {
    var prefix = 'XSRF-TOKEN=';
    var chunks = document.cookie.split(';');
    for (var i = 0; i < chunks.length; i += 1) {
        var c = chunks[i].trim();
        if (c.indexOf(prefix) !== 0) continue;
        var raw = c.slice(prefix.length);
        try {
            window.axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(raw);
        } catch (_) {
            window.axios.defaults.headers.common['X-XSRF-TOKEN'] = raw;
        }
        return;
    }
})();

/*
 * Lucide is loaded from the deferred CDN <script> in each layout
 * (resources/views/layouts/*.blade.php). It exposes `window.lucide` after
 * `load`, and re-renders icons whenever the page calls
 * `window.refreshLucideIcons()`. We deliberately do NOT bundle the lucide
 * package here: doing so adds ~375 kB to every page's initial JS payload
 * and duplicates what the CDN script already provides.
 *
 * If a feature module needs to render icons it just inserted dynamically,
 * call `window.refreshLucideIcons?.()` after DOM insertion.
 */
