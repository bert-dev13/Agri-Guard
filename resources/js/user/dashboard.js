/**
 * AGRIGUARD Dashboard — Lucide, staggered card reveal
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
        if (!document.body.classList.contains('dashboard-page')) {
            return;
        }

        if (typeof window.lucide !== 'undefined') {
            window.lucide.createIcons();
        }

        var reduced =
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var dashHero = document.querySelector('.dashboard-hero');
        if (dashHero && !reduced && typeof IntersectionObserver !== 'undefined') {
            var heroIo = new IntersectionObserver(
                function (entries) {
                    entries.forEach(function (entry) {
                        dashHero.classList.toggle('dashboard-hero--offscreen', !entry.isIntersecting);
                    });
                },
                { root: null, rootMargin: '0px 0px 80px 0px', threshold: 0 }
            );
            heroIo.observe(dashHero);
        }

        var scope = document.querySelector('.dashboard-container') || document;
        var cards = scope.querySelectorAll('.ag-card');

        cards.forEach(function (card, index) {
            /* Hero uses its own CSS entrance + layered animations */
            if (card.classList.contains('dashboard-hero')) {
                card.style.opacity = '';
                return;
            }
            if (reduced) {
                card.style.opacity = '1';
                return;
            }
            var delay = 45 + index * 50;
            card.style.opacity = '0';
            card.style.transform = 'translateY(10px)';
            card.style.transition = 'opacity 0.4s ease, transform 0.45s cubic-bezier(0.22, 1, 0.36, 1)';
            window.setTimeout(function () {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, delay);
            /* Drop inline transform after entrance so CSS :hover scale works */
            window.setTimeout(function () {
                card.style.transform = '';
                card.style.transition = '';
            }, delay + 480);
        });
    });
})();
