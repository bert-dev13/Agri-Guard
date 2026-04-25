/**
 * AGRIGUARD user top bar — dropdown, mobile panel, scroll state, Escape to close
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
        var navbar = document.getElementById('navbar');
        if (!navbar) return;

        function onScroll() {
            navbar.classList.toggle('scrolled', window.scrollY > 8);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        var userBtn = document.getElementById('user-menu-btn');
        var userDropdown = document.getElementById('user-dropdown');
        var mobileBtn = document.getElementById('mobile-menu-btn');
        var mobileMenu = document.getElementById('mobile-menu');

        function setDropdownOpen(open) {
            if (!userBtn || !userDropdown) return;
            userBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            userDropdown.classList.toggle('is-open', open);
        }

        function setMenuOpen(open) {
            navbar.classList.toggle('menu-open', open);
            if (mobileBtn) {
                mobileBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            if (mobileMenu) {
                mobileMenu.setAttribute('aria-hidden', open ? 'false' : 'true');
            }
        }

        if (userBtn && userDropdown) {
            userBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var next = !userDropdown.classList.contains('is-open');
                setDropdownOpen(next);
                if (next) setMenuOpen(false);
            });
            document.addEventListener('click', function () {
                setDropdownOpen(false);
            });
            userDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        if (mobileBtn && mobileMenu) {
            mobileMenu.setAttribute('aria-hidden', 'true');
            mobileBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var next = !navbar.classList.contains('menu-open');
                setMenuOpen(next);
                if (next) setDropdownOpen(false);
            });
            mobileMenu.querySelectorAll('.user-navbar-mobile-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    setMenuOpen(false);
                });
            });
            mobileMenu.querySelectorAll('.user-navbar-mobile-logout').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    setMenuOpen(false);
                });
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            setDropdownOpen(false);
            setMenuOpen(false);
        });

        var advisoryControls = Array.prototype.slice.call(document.querySelectorAll('[data-ai-advisory-toggle]'));
        advisoryControls.forEach(function (toggleBtn) {
            var sectionId = toggleBtn.getAttribute('data-target');
            if (!sectionId) return;
            var advisorySection = document.getElementById(sectionId);
            if (!advisorySection) return;

            advisorySection.classList.add('ag-advisory-section');
            var storageKey = toggleBtn.getAttribute('data-storage-key') || ('advisory_visibility_' + sectionId);

            function loadPreference() {
                try {
                    return localStorage.getItem(storageKey) !== '0';
                } catch (err) {
                    return true;
                }
            }

            function savePreference(isVisible) {
                try {
                    localStorage.setItem(storageKey, isVisible ? '1' : '0');
                } catch (err) {
                    // Ignore storage errors and keep current-page behavior.
                }
            }

            function renderToggleLabel(isVisible) {
                toggleBtn.textContent = isVisible ? 'Hide AI Smart Advisory' : 'Show AI Smart Advisory';
                toggleBtn.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
                toggleBtn.setAttribute('data-state', isVisible ? 'visible' : 'hidden');
            }

            function applyVisibility(isVisible) {
                if (isVisible) {
                    advisorySection.classList.remove('is-hidden');
                    advisorySection.removeAttribute('aria-hidden');
                    advisorySection.style.maxHeight = (advisorySection.scrollHeight || 2000) + 'px';
                    window.setTimeout(function () {
                        if (!advisorySection.classList.contains('is-hidden')) {
                            advisorySection.style.maxHeight = '';
                        }
                    }, 320);
                } else {
                    advisorySection.style.maxHeight = (advisorySection.scrollHeight || 2000) + 'px';
                    window.requestAnimationFrame(function () {
                        advisorySection.classList.add('is-hidden');
                        advisorySection.setAttribute('aria-hidden', 'true');
                    });
                }
                renderToggleLabel(isVisible);
            }

            var isVisible = loadPreference();
            applyVisibility(isVisible);

            toggleBtn.addEventListener('click', function () {
                isVisible = !isVisible;
                applyVisibility(isVisible);
                savePreference(isVisible);
            });

            window.addEventListener('storage', function (event) {
                if (event.key !== storageKey) return;
                isVisible = event.newValue !== '0';
                applyVisibility(isVisible);
            });
        });
    });
})();
