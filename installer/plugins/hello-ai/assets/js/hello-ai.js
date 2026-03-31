/**
 * Hello AI — Chat Modal Logic
 * Demo plugin for Klytos CMS.
 *
 * Translations are injected by PHP into window.helloAiTranslations.
 * This demonstrates the i18n pattern for plugin JavaScript.
 */
(function () {
    'use strict';

    var overlay  = document.getElementById('helloAiOverlay');
    var toggle   = document.getElementById('hello-ai-toggle');
    var closeBtn = document.getElementById('helloAiClose');
    var input    = document.getElementById('helloAiInput');
    var sendBtn  = document.getElementById('helloAiSend');
    var messages = document.getElementById('helloAiMessages');

    if (!overlay || !toggle) return;

    // ── Load translations from PHP ─────────────────────────
    var t = window.helloAiTranslations || {};
    var responses      = t.responses || ['Hello!'];
    var smartResponses = t.smart || {};

    // ── Open / Close ────────────────────────────────────────

    function open() {
        overlay.classList.add('active');
        setTimeout(function () { input.focus(); }, 100);
    }

    function close() {
        overlay.classList.remove('active');
    }

    toggle.addEventListener('click', open);
    closeBtn.addEventListener('click', close);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) close();
    });

    // ── Messaging ───────────────────────────────────────────

    function addMessage(text, role) {
        var msgDiv = document.createElement('div');
        msgDiv.className = 'hello-ai-msg hello-ai-msg-' + role;

        var bubble = document.createElement('div');
        bubble.className = 'hello-ai-bubble';
        bubble.innerHTML = text;

        msgDiv.appendChild(bubble);
        messages.appendChild(msgDiv);
        messages.scrollTop = messages.scrollHeight;
    }

    function addTyping() {
        var msgDiv = document.createElement('div');
        msgDiv.className = 'hello-ai-msg hello-ai-msg-assistant hello-ai-typing';
        msgDiv.id = 'helloAiTyping';

        var bubble = document.createElement('div');
        bubble.className = 'hello-ai-bubble';
        bubble.innerHTML = '<span class="hello-ai-dot"></span>'
                         + '<span class="hello-ai-dot"></span>'
                         + '<span class="hello-ai-dot"></span>';

        msgDiv.appendChild(bubble);
        messages.appendChild(msgDiv);
        messages.scrollTop = messages.scrollHeight;
    }

    function removeTyping() {
        var typing = document.getElementById('helloAiTyping');
        if (typing) typing.remove();
    }

    function getResponse(userText) {
        var lower = userText.toLowerCase().trim();

        // Check smart responses (partial match).
        var keys = Object.keys(smartResponses);
        for (var i = 0; i < keys.length; i++) {
            if (lower.indexOf(keys[i]) !== -1) {
                return smartResponses[keys[i]];
            }
        }

        // Random response.
        return responses[Math.floor(Math.random() * responses.length)];
    }

    function send() {
        var text = input.value.trim();
        if (!text) return;

        addMessage(text, 'user');
        input.value = '';

        // Simulate AI thinking.
        addTyping();

        var delay = 600 + Math.random() * 800;
        setTimeout(function () {
            removeTyping();
            addMessage(getResponse(text), 'assistant');
        }, delay);
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            send();
        }
    });

})();
