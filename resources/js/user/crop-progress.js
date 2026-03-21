/**
 * AGRIGUARD Crop Progress page interactions.
 */
(function () {
    'use strict';

    function initLucide() {
        if (typeof window.lucide !== 'undefined') {
            window.lucide.createIcons();
        }
    }

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        initLucide();
        window.addEventListener('load', initLucide);

        var progressFill = document.querySelector('.cp-progress-line-fill');
        if (progressFill) {
            var targetWidth = progressFill.style.width || '0%';
            progressFill.style.width = '0%';
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    progressFill.style.width = targetWidth;
                });
            });
        }
    });
})();
