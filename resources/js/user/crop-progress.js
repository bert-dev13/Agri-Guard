/**
 * AGRIGUARD Crop Progress page — progress bar entrance animation + reality check form (AJAX).
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var reduceMotion =
            'matchMedia' in window &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        var fills = document.querySelectorAll('.cp-progress-line-fill, .cp-progress-block__fill');
        fills.forEach(function (el) {
            var targetWidth = el.style.width || '0%';
            el.style.width = '0%';
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    el.style.width = targetWidth;
                });
            });
        });

        if (!reduceMotion) {
            var trackerNodes = document.querySelectorAll('.cp-tracker-node');
            trackerNodes.forEach(function (el, i) {
                el.style.opacity = '0';
                el.style.transform = 'translateY(6px)';
                window.setTimeout(function () {
                    el.style.transition = 'opacity 0.28s ease, transform 0.28s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, 40 + i * 45);
            });
        }

        cpRealityCheckInit();
    });

    function cpRealityCheckInit() {
        var wrap = document.getElementById('cp-reality-wrap');
        var realityUrl = wrap && wrap.getAttribute('data-reality-url');
        if (!wrap || !realityUrl) {
            return;
        }

        var form = document.getElementById('cp-reality-form');

        function csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && meta.getAttribute('content')) {
                return meta.getAttribute('content');
            }
            if (form) {
                var t = form.querySelector('[name="_token"]');
                if (t) {
                    return t.value;
                }
            }
            return '';
        }

        function setRealityWrapVisible(visible) {
            if (wrap) {
                wrap.hidden = !visible;
            }
        }

        function postRealityForm(fd) {
            return fetch(realityUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                body: fd,
            }).then(function (r) {
                if (!r.ok) {
                    throw new Error('bad');
                }
                return r.json();
            });
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var submitter = e.submitter;
                var fd = new FormData(form);
                if (submitter && submitter.getAttribute('name') === 'response') {
                    fd.set('response', submitter.value);
                }
                postRealityForm(fd)
                    .then(function (data) {
                        setRealityWrapVisible(!!data.show_form);
                    })
                    .catch(function () {
                        form.submit();
                    });
            });
        }
    }
})();
