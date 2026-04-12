/**
 * Admin dashboard — Lucide refresh
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    ready(function () {
        if (!document.body.classList.contains('admin-dashboard-page')) {
            return;
        }
        if (typeof window.lucide !== 'undefined') {
            window.lucide.createIcons();
        }
    });
})();
