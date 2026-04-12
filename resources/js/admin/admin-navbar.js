/**
 * Admin sidebar — collapse (desktop), drawer (mobile), Escape to close
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'ag_admin_sidebar_collapsed';
    var MQ_DESKTOP = '(min-width: 1024px)';

    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }

    function isDesktop() {
        return window.matchMedia(MQ_DESKTOP).matches;
    }

    function refreshLucide() {
        if (typeof window.lucide !== 'undefined') {
            window.lucide.createIcons();
        }
    }

    ready(function () {
        var app = document.getElementById('admin-app');
        var sidebar = document.getElementById('admin-sidebar');
        var backdrop = document.getElementById('admin-sidebar-backdrop');
        if (!app || !sidebar) return;

        var collapseBtn = document.getElementById('admin-sidebar-collapse');
        var mobileBtn = document.getElementById('admin-mobile-menu-btn');

        function setCollapsed(collapsed) {
            app.classList.toggle('admin-sidebar-collapsed', collapsed);
            if (collapseBtn) {
                collapseBtn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
                collapseBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                collapseBtn.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            }
            try {
                localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
            } catch (e) {
                /* ignore */
            }
            refreshLucide();
        }

        function loadCollapsedPreference() {
            if (!isDesktop()) return;
            try {
                var v = localStorage.getItem(STORAGE_KEY);
                setCollapsed(v === '1');
            } catch (e) {
                /* ignore */
            }
        }

        function setDrawerOpen(open) {
            app.classList.toggle('admin-drawer-open', open);
            document.body.classList.toggle('admin-drawer-scroll-lock', open);
            if (backdrop) {
                backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
            if (mobileBtn) {
                mobileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                mobileBtn.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
            }
        }

        loadCollapsedPreference();

        if (collapseBtn) {
            collapseBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (!isDesktop()) return;
                setCollapsed(!app.classList.contains('admin-sidebar-collapsed'));
                setDrawerOpen(false);
            });
        }

        if (mobileBtn) {
            mobileBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var next = !app.classList.contains('admin-drawer-open');
                setDrawerOpen(next);
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                setDrawerOpen(false);
            });
        }

        sidebar.querySelectorAll('.admin-sidebar__link').forEach(function (link) {
            link.addEventListener('click', function () {
                if (!isDesktop()) {
                    setDrawerOpen(false);
                }
            });
        });

        window.addEventListener(
            'resize',
            function () {
                if (isDesktop()) {
                    setDrawerOpen(false);
                    loadCollapsedPreference();
                } else {
                    app.classList.remove('admin-sidebar-collapsed');
                }
            },
            { passive: true }
        );

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            setDrawerOpen(false);
        });

        refreshLucide();
    });
})();
