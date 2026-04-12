(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    function parseJson(raw, fallback) {
        try {
            return JSON.parse(raw || '');
        } catch (_) {
            return fallback;
        }
    }

    function splitParagraphs(text) {
        return String(text || '')
            .split(/\n{2,}/)
            .map(function (part) { return part.trim(); })
            .filter(Boolean);
    }

    function extractGuideSteps(text) {
        var rows = String(text || '')
            .split('\n')
            .map(function (line) { return line.trim(); })
            .filter(Boolean);

        if (rows.length < 4) return [];
        var stepPattern = /^(\d+[\).\s-]|[-*•]\s+|(?:step)\s*\d*[:.\-\s]*)/i;
        var steps = rows.filter(function (line) { return stepPattern.test(line); }).map(function (line) {
            return line.replace(stepPattern, '').trim();
        });

        return steps.length >= 3 ? steps : [];
    }

    ready(function () {
        var root = document.getElementById('assistant-page');
        if (!root) return;

        var chatUrl = root.dataset.chatUrl || '';
        var clearUrl = root.dataset.clearUrl || '';
        var context = parseJson(root.dataset.context, {});
        var history = parseJson(root.dataset.history, []);
        var canChat = !!(context && context.has_gps);

        var formEl = document.getElementById('assistant-form');
        var inputEl = document.getElementById('assistant-input');
        var sendBtn = document.getElementById('assistant-send-btn');
        var clearBtn = document.getElementById('assistant-clear-btn');
        var messagesEl = document.getElementById('assistant-messages');
        var typingEl = document.getElementById('assistant-typing');
        var fallbackBadge = document.getElementById('assistant-fallback-badge');
        var introEl = document.getElementById('assistant-intro-text');
        var quickButtons = Array.prototype.slice.call(document.querySelectorAll('.assistant-quick-chip'));

        var state = { isLoading: false, messages: [], requestController: null };

        function normalizePayload(payload) {
            var p = payload || {};
            return {
                message: String(p.message || p.direct_answer || '').trim(),
                meta: p.meta || {},
            };
        }

        function createTextContainer(text) {
            var wrap = document.createElement('div');
            wrap.className = 'assistant-message-text';
            var paragraphs = splitParagraphs(text);

            if (paragraphs.length <= 1) {
                var p = document.createElement('p');
                p.textContent = text;
                wrap.appendChild(p);
                return wrap;
            }

            paragraphs.forEach(function (part) {
                var p = document.createElement('p');
                p.textContent = part;
                wrap.appendChild(p);
            });
            return wrap;
        }

        function createGuideCard(steps) {
            var card = document.createElement('div');
            card.className = 'assistant-guide-card';

            var title = document.createElement('p');
            title.className = 'assistant-guide-title';
            title.textContent = '🌱 Planting Guide Today';
            card.appendChild(title);

            var list = document.createElement('div');
            list.className = 'assistant-guide-steps';
            steps.forEach(function (step, index) {
                var row = document.createElement('div');
                row.className = 'assistant-guide-step';

                var idx = document.createElement('span');
                idx.className = 'assistant-guide-index';
                idx.textContent = String(index + 1);

                var text = document.createElement('span');
                text.textContent = step;

                row.appendChild(idx);
                row.appendChild(text);
                list.appendChild(row);
            });

            card.appendChild(list);
            return card;
        }

        function messageBubble(role, text) {
            var row = document.createElement('div');
            row.className = role === 'user' ? 'assistant-row assistant-row--user' : 'assistant-row assistant-row--assistant';

            var wrap = document.createElement('div');
            wrap.className = 'assistant-bubble-wrap';

            if (role === 'assistant') {
                var avatar = document.createElement('span');
                avatar.className = 'assistant-avatar';
                avatar.textContent = '🌾';
                wrap.appendChild(avatar);
            }

            var bubble = document.createElement('div');
            bubble.className = role === 'user' ? 'assistant-bubble assistant-bubble--user' : 'assistant-bubble assistant-bubble--assistant';

            bubble.appendChild(createTextContainer(text));

            if (role === 'assistant') {
                var steps = extractGuideSteps(text);
                if (steps.length) bubble.appendChild(createGuideCard(steps));
            }

            if (role === 'user') {
                var userAvatar = document.createElement('span');
                userAvatar.className = 'assistant-avatar assistant-avatar--user';
                userAvatar.textContent = '👨';
                wrap.appendChild(bubble);
                wrap.appendChild(userAvatar);
            } else {
                wrap.appendChild(bubble);
            }

            row.appendChild(wrap);
            return row;
        }

        function render() {
            messagesEl.innerHTML = '';
            state.messages.forEach(function (m) {
                if (m.role === 'user') {
                    messagesEl.appendChild(messageBubble('user', String(m.text || '')));
                } else {
                    var data = normalizePayload(m.payload || {});
                    messagesEl.appendChild(messageBubble('assistant', data.message || 'I can help with your farm decisions.'));
                }
            });
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function setLoading(flag) {
            state.isLoading = !!flag;
            if (typingEl) typingEl.classList.toggle('hidden', !state.isLoading);
            if (inputEl) inputEl.disabled = !canChat || state.isLoading;
            if (sendBtn) sendBtn.disabled = !canChat || state.isLoading;
            quickButtons.forEach(function (btn) { btn.disabled = !canChat || state.isLoading; });
        }

        function setFallbackBadge(payload) {
            if (!fallbackBadge) return;
            var data = normalizePayload(payload);
            var on = !!(data.meta && data.meta.fallback_mode);
            fallbackBadge.classList.toggle('hidden', !on);
            fallbackBadge.textContent = on && data.meta.fallback_note ? String(data.meta.fallback_note) : 'Using basic farm guidance';
        }

        function resizeInput() {
            if (!inputEl) return;
            inputEl.style.height = 'auto';
            inputEl.style.height = Math.min(inputEl.scrollHeight, 110) + 'px';
        }

        function typeIntroText() {
            if (!introEl) return;
            var full = introEl.textContent || '';
            if (!full) return;
            introEl.textContent = '';
            var i = 0;
            var timer = setInterval(function () {
                introEl.textContent += full.charAt(i);
                i += 1;
                if (i >= full.length) clearInterval(timer);
            }, 12);
        }

        function sendMessage(text) {
            var message = String(text || '').trim();
            if (!message || !canChat || !chatUrl || state.isLoading) return;

            if (state.requestController) state.requestController.abort();
            state.requestController = new AbortController();

            state.messages.push({ role: 'user', text: message });
            render();
            setLoading(true);

            fetch(chatUrl, {
                method: 'POST',
                signal: state.requestController.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                },
                body: JSON.stringify({ message: message }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || data.ok !== true) {
                        state.messages.push({
                            role: 'assistant',
                            payload: {
                                message: (data && data.message) || 'I cannot answer right now, but please check your field and drainage first while weather is uncertain.',
                                meta: { fallback_mode: true, fallback_note: 'Using basic farm guidance' },
                            },
                        });
                        setFallbackBadge(state.messages[state.messages.length - 1].payload);
                        render();
                        return;
                    }

                    state.messages.push({ role: 'assistant', payload: data });
                    setFallbackBadge(data);
                    render();
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    state.messages.push({
                        role: 'assistant',
                        payload: {
                            message: 'I hit a connection issue. For now, start with a quick field check, inspect drainage, and hold risky operations if rain looks likely.',
                            meta: { fallback_mode: true, fallback_note: 'Using basic farm guidance' },
                        },
                    });
                    setFallbackBadge(state.messages[state.messages.length - 1].payload);
                    render();
                })
                .finally(function () {
                    state.requestController = null;
                    setLoading(false);
                });
        }

        function hydrate() {
            var rows = Array.isArray(history) ? history : [];
            state.messages = rows
                .filter(function (row) { return row && typeof row === 'object'; })
                .map(function (row) {
                    if (row.role === 'user') {
                        return {
                            role: 'user',
                            text: String(row.text || row.content || ''),
                        };
                    }
                    if (row.role === 'assistant') {
                        return { role: 'assistant', payload: row.payload || {} };
                    }
                    return null;
                })
                .filter(Boolean);
        }

        if (formEl) {
            formEl.addEventListener('submit', function (e) {
                e.preventDefault();
                var text = inputEl ? inputEl.value : '';
                sendMessage(text);
                if (inputEl) {
                    inputEl.value = '';
                    resizeInput();
                }
            });
        }

        if (inputEl) {
            inputEl.addEventListener('input', resizeInput);
            inputEl.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (formEl) formEl.dispatchEvent(new Event('submit', { cancelable: true }));
                }
            });
        }

        quickButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!inputEl || state.isLoading || !canChat) return;
                var prompt = btn.getAttribute('data-assistant-prompt') || '';
                inputEl.value = prompt;
                resizeInput();
                sendMessage(prompt);
                inputEl.value = '';
                resizeInput();
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (!clearUrl || state.isLoading) return;
                fetch(clearUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                    },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        history = data && data.ok && Array.isArray(data.history) ? data.history : [];
                        hydrate();
                        render();
                        setFallbackBadge({});
                    });
            });
        }

        hydrate();
        render();
        setFallbackBadge(state.messages[state.messages.length - 1] ? state.messages[state.messages.length - 1].payload : {});
        setLoading(false);
        resizeInput();
        typeIntroText();
        if (typeof window.lucide !== 'undefined') window.lucide.createIcons();
    });
})();
