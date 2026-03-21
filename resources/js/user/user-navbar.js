/**
 * AGRIGUARD User Navbar – dropdown, mobile menu, scroll shadow
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

        /* Scroll shadow */
        function onScroll() {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        /* User dropdown */
        var userBtn = document.getElementById('user-menu-btn');
        var userDropdown = document.getElementById('user-dropdown');
        if (userBtn && userDropdown) {
            function setDropdownOpen(open) {
                userBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                userDropdown.classList.toggle('is-open', open);
            }
            userBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                setDropdownOpen(!userDropdown.classList.contains('is-open'));
            });
            document.addEventListener('click', function () {
                setDropdownOpen(false);
            });
            userDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        /* Mobile menu */
        var mobileBtn = document.getElementById('mobile-menu-btn');
        var mobileMenu = document.getElementById('mobile-menu');
        if (mobileBtn && mobileMenu) {
            function setMenuOpen(open) {
                navbar.classList.toggle('menu-open', open);
            }
            mobileBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                setMenuOpen(!navbar.classList.contains('menu-open'));
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
    });
})();

