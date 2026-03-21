/**
 * AGRIGUARD Dashboard interactions
 * - icon rendering
 * - subtle reveal animation for cards
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

        var cards = document.querySelectorAll('.dashboard-container .ag-card');
        cards.forEach(function (card, index) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(8px)';
            card.style.transition = 'opacity 260ms ease, transform 260ms ease';
            window.setTimeout(function () {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 40 + (index * 35));
        });
    });
})();
