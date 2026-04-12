import axios from 'axios';
import { createIcons, icons } from 'lucide';

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

window.lucide = {
    icons,
    createIcons: (options) => createIcons(options ?? { icons }),
};
