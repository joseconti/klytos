/**
 * Klytos — AI Chat (Vanilla JS)
 * Manages the integrated AI chat interface in the admin panel.
 *
 * @package Klytos
 * @since   0.9.0
 */

(function () {
    'use strict';

    const Chat = {
        // DOM elements
        el: {},
        // State
        chatId: null,
        sending: false,
        csrf: '',
        apiUrl: '',
        inWelcome: true,

        init(container) {
            this.el.container = container;
            this.csrf = container.dataset.csrf || '';
            this.apiUrl = container.dataset.apiUrl || 'api/ai-chat.php';

            // Cache DOM elements — Sidebar
            this.el.chatList = container.querySelector('.ai-chat-list');
            this.el.newBtn = container.querySelector('.ai-chat-new-btn');
            this.el.searchToggle = document.getElementById('ai-chat-search-toggle');
            this.el.chatsToggle = document.getElementById('ai-chat-chats-toggle');
            this.el.searchBox = document.getElementById('ai-chat-search-box');
            this.el.searchInput = document.getElementById('ai-chat-search-input');
            this.el.sidebarLabel = document.getElementById('ai-chat-sidebar-label');

            // Cache DOM elements — Welcome
            this.el.welcome = document.getElementById('ai-chat-welcome');
            this.el.greeting = document.getElementById('ai-chat-greeting');
            this.el.welcomeTextarea = document.getElementById('ai-chat-welcome-textarea');
            this.el.welcomeSendBtn = document.getElementById('ai-chat-welcome-send');

            // Cache DOM elements — Chat View
            this.el.chatView = document.getElementById('ai-chat-view');
            this.el.messages = container.querySelector('.ai-chat-messages');
            this.el.textarea = container.querySelector('.ai-chat-input textarea');
            this.el.sendBtn = container.querySelector('.ai-chat-input .ai-chat-send-btn');
            this.el.providerSelect = container.querySelector('#ai-provider-select');
            this.el.providerSelectWelcome = container.querySelector('#ai-provider-select-welcome');
            this.el.usage = container.querySelector('.ai-chat-usage');

            // Cache DOM elements — Chats Browser
            this.el.browser = document.getElementById('ai-chat-browser');
            this.el.browserList = document.getElementById('ai-chat-browser-list');
            this.el.browserNewBtn = document.getElementById('ai-chat-browser-new');
            this.el.browserSearchInput = document.getElementById('ai-chat-browser-search-input');

            // Events — Chat View input
            if (this.el.sendBtn) {
                this.el.sendBtn.addEventListener('click', () => this.sendMessage());
            }
            if (this.el.textarea) {
                this.el.textarea.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });
                this.el.textarea.addEventListener('input', () => this.autoResizeEl(this.el.textarea));
            }

            // Events — Welcome input
            if (this.el.welcomeSendBtn) {
                this.el.welcomeSendBtn.addEventListener('click', () => this.sendFromWelcome());
            }
            if (this.el.welcomeTextarea) {
                this.el.welcomeTextarea.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendFromWelcome();
                    }
                });
                this.el.welcomeTextarea.addEventListener('input', () => this.autoResizeEl(this.el.welcomeTextarea));
            }

            // Events — Sidebar
            if (this.el.newBtn) {
                this.el.newBtn.addEventListener('click', () => this.newConversation());
            }
            if (this.el.searchToggle) {
                this.el.searchToggle.addEventListener('click', () => this.openSearch());
            }
            if (this.el.chatsToggle) {
                this.el.chatsToggle.addEventListener('click', () => this.showBrowser());
            }
            if (this.el.searchInput) {
                let debounce = null;
                this.el.searchInput.addEventListener('input', () => {
                    clearTimeout(debounce);
                    debounce = setTimeout(() => this.performSearch(), 300);
                });
            }

            // Events — Chats Browser
            if (this.el.browserNewBtn) {
                this.el.browserNewBtn.addEventListener('click', () => this.newConversation());
            }
            if (this.el.browserSearchInput) {
                let browserDebounce = null;
                this.el.browserSearchInput.addEventListener('input', () => {
                    clearTimeout(browserDebounce);
                    browserDebounce = setTimeout(() => this.browserSearch(), 300);
                });
            }

            // Sync provider selects
            if (this.el.providerSelect && this.el.providerSelectWelcome) {
                this.el.providerSelectWelcome.addEventListener('change', () => {
                    this.el.providerSelect.value = this.el.providerSelectWelcome.value;
                });
                this.el.providerSelect.addEventListener('change', () => {
                    this.el.providerSelectWelcome.value = this.el.providerSelect.value;
                    this.switchProvider();
                });
            }

            // Build greeting and show welcome
            this.buildGreeting();
            this.showWelcome();

            // Load conversations
            this.loadConversations();
        },

        // ─── View State ─────────────────────────────────────────

        showWelcome() {
            this.inWelcome = true;
            if (this.el.welcome) this.el.welcome.style.display = '';
            if (this.el.chatView) this.el.chatView.style.display = 'none';
            if (this.el.browser) this.el.browser.style.display = 'none';
            if (this.el.welcomeTextarea) {
                this.el.welcomeTextarea.value = '';
                this.el.welcomeTextarea.focus();
            }
            // Deselect sidebar items
            if (this.el.chatList) {
                this.el.chatList.querySelectorAll('.ai-chat-item').forEach(el => {
                    el.classList.remove('active');
                });
            }
        },

        showChat() {
            this.inWelcome = false;
            if (this.el.welcome) this.el.welcome.style.display = 'none';
            if (this.el.chatView) this.el.chatView.style.display = '';
            if (this.el.browser) this.el.browser.style.display = 'none';
            if (this.el.textarea) this.el.textarea.focus();
        },

        showBrowser() {
            this.inWelcome = false;
            if (this.el.welcome) this.el.welcome.style.display = 'none';
            if (this.el.chatView) this.el.chatView.style.display = 'none';
            if (this.el.browser) this.el.browser.style.display = '';
            if (this.el.browserSearchInput) this.el.browserSearchInput.value = '';
            this.loadBrowser();
        },

        buildGreeting() {
            if (!this.el.greeting) return;

            const hour = new Date().getHours();
            const lang = document.documentElement.lang || 'en';
            const name = this.el.container.dataset.username || '';

            let greeting;
            if (lang.startsWith('es')) {
                greeting = hour < 12 ? 'Buenos días' : hour < 20 ? 'Buenas tardes' : 'Buenas noches';
            } else {
                greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
            }

            this.el.greeting.textContent = greeting + ', ' + name;
        },

        // ─── API Calls ──────────────────────────────────────────

        async api(action, data = {}, method = 'POST') {
            const opts = { method, headers: {} };

            if (method === 'POST') {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify({ ...data, action, csrf: this.csrf });
            }

            const url = method === 'GET'
                ? `${this.apiUrl}?action=${action}&${new URLSearchParams(data)}`
                : this.apiUrl;

            const res = await fetch(url, opts);
            return res.json();
        },

        // ─── Conversations ──────────────────────────────────────

        async loadConversations() {
            const result = await this.api('list_chats', {}, 'GET');
            if (!result.success) return;

            this.el.chatList.innerHTML = '';
            for (const chat of result.chats || []) {
                this.renderChatItem(chat);
            }
        },

        renderChatItem(chat) {
            const item = document.createElement('div');
            item.className = 'ai-chat-item' + (chat.id === this.chatId ? ' active' : '');
            item.dataset.chatId = chat.id;

            const title = document.createElement('span');
            title.className = 'ai-chat-item-title';
            title.textContent = chat.title || 'New conversation';

            const del = document.createElement('span');
            del.className = 'ai-chat-item-delete';
            del.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
            del.addEventListener('click', (e) => {
                e.stopPropagation();
                this.deleteConversation(chat.id, item);
            });

            item.appendChild(title);
            item.appendChild(del);
            item.addEventListener('click', () => this.loadConversation(chat.id));

            this.el.chatList.appendChild(item);
        },

        async loadConversation(chatId) {
            const result = await this.api('get_chat', { chat_id: chatId }, 'GET');
            if (!result.success || !result.chat) return;

            this.chatId = chatId;

            // Switch to chat view
            this.showChat();

            this.el.messages.innerHTML = '';

            // Mark active in sidebar
            this.el.chatList.querySelectorAll('.ai-chat-item').forEach(el => {
                el.classList.toggle('active', el.dataset.chatId === chatId);
            });

            // Render messages
            for (const msg of result.chat.messages || []) {
                if (msg.role === 'user') {
                    this.appendUserMessage(msg.content);
                } else if (msg.role === 'assistant') {
                    this.appendAssistantMessage(msg.content, msg.tool_executions);
                } else if (msg.role === 'system' && msg.message_type === 'provider_change') {
                    this.appendProviderChange(msg.content);
                }
            }

            this.scrollToBottom();
        },

        async newConversation() {
            this.chatId = null;
            this.showWelcome();
        },

        // ─── Search ─────────────────────────────────────────────

        openSearch() {
            if (this.el.searchBox) this.el.searchBox.style.display = '';
            if (this.el.sidebarLabel) this.el.sidebarLabel.style.display = 'none';
            if (this.el.searchToggle) this.el.searchToggle.classList.add('active');
            if (this.el.chatsToggle) this.el.chatsToggle.classList.remove('active');
            if (this.el.searchInput) {
                this.el.searchInput.value = '';
                this.el.searchInput.focus();
            }
            this.el.chatList.innerHTML = '';
        },

        closeSearch() {
            if (this.el.searchBox) this.el.searchBox.style.display = 'none';
            if (this.el.sidebarLabel) this.el.sidebarLabel.style.display = '';
            if (this.el.searchToggle) this.el.searchToggle.classList.remove('active');
            if (this.el.chatsToggle) this.el.chatsToggle.classList.add('active');
            if (this.el.searchInput) this.el.searchInput.value = '';
            this.loadConversations();
        },

        async performSearch() {
            const query = this.el.searchInput ? this.el.searchInput.value.trim() : '';
            if (!query) {
                this.el.chatList.innerHTML = '';
                return;
            }

            const result = await this.api('search_chats', { q: query }, 'GET');
            if (!result.success) return;

            this.el.chatList.innerHTML = '';
            const chats = result.chats || [];
            if (chats.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'ai-chat-search-empty';
                empty.textContent = this.el.container.dataset.noResults || 'No results found';
                this.el.chatList.appendChild(empty);
                return;
            }
            for (const chat of chats) {
                this.renderChatItem(chat);
            }
        },

        // ─── Chats Browser ──────────────────────────────────────

        async loadBrowser() {
            const result = await this.api('list_chats', { limit: 100 }, 'GET');
            if (!result.success) return;
            this.renderBrowserList(result.chats || []);
        },

        async browserSearch() {
            const query = this.el.browserSearchInput ? this.el.browserSearchInput.value.trim() : '';
            if (!query) {
                this.loadBrowser();
                return;
            }
            const result = await this.api('search_chats', { q: query }, 'GET');
            if (!result.success) return;
            this.renderBrowserList(result.chats || []);
        },

        renderBrowserList(chats) {
            if (!this.el.browserList) return;
            this.el.browserList.innerHTML = '';

            if (chats.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'ai-chat-search-empty';
                empty.textContent = this.el.container.dataset.noResults || 'No results found';
                this.el.browserList.appendChild(empty);
                return;
            }

            for (const chat of chats) {
                this.renderBrowserItem(chat);
            }
        },

        renderBrowserItem(chat) {
            const item = document.createElement('div');
            item.className = 'ai-chat-browser-item';
            item.addEventListener('click', () => this.loadConversation(chat.id));

            const title = document.createElement('div');
            title.className = 'ai-chat-browser-item-title';
            title.textContent = chat.title || 'New conversation';

            const meta = document.createElement('div');
            meta.className = 'ai-chat-browser-item-meta';
            meta.textContent = this.formatRelativeTime(chat.updated_at);

            item.appendChild(title);
            item.appendChild(meta);
            this.el.browserList.appendChild(item);
        },

        formatRelativeTime(isoDate) {
            if (!isoDate) return '';
            const now = new Date();
            const date = new Date(isoDate);
            const diffMs = now - date;
            const diffMin = Math.floor(diffMs / 60000);
            const diffH = Math.floor(diffMin / 60);
            const diffD = Math.floor(diffH / 24);

            const lang = document.documentElement.lang || 'en';
            const isEs = lang.startsWith('es');

            if (diffMin < 1) return isEs ? 'Ahora mismo' : 'Just now';
            if (diffMin < 60) return isEs ? `Hace ${diffMin} min` : `${diffMin}m ago`;
            if (diffH < 24) return isEs ? `Hace ${diffH}h` : `${diffH}h ago`;
            if (diffD === 1) return isEs ? 'Ayer' : 'Yesterday';
            if (diffD < 7) return isEs ? `Hace ${diffD} días` : `${diffD}d ago`;

            return date.toLocaleDateString(lang, { day: 'numeric', month: 'short' });
        },

        async deleteConversation(chatId, itemEl) {
            if (!confirm('Delete this conversation?')) return;

            await this.api('delete_chat', { chat_id: chatId });
            itemEl.remove();

            if (this.chatId === chatId) {
                this.chatId = null;
                this.showWelcome();
            }
        },

        // ─── Sending Messages ───────────────────────────────────

        sendFromWelcome() {
            const text = this.el.welcomeTextarea ? this.el.welcomeTextarea.value.trim() : '';
            if (!text || this.sending) return;

            // Switch to chat view
            this.showChat();
            this.el.messages.innerHTML = '';

            // Set the text in the chat textarea and send
            if (this.el.textarea) {
                this.el.textarea.value = text;
            }
            this.sendMessage();
        },

        async sendMessage() {
            const text = this.el.textarea ? this.el.textarea.value.trim() : '';
            if (!text || this.sending) return;

            this.sending = true;
            if (this.el.sendBtn) this.el.sendBtn.disabled = true;
            if (this.el.textarea) {
                this.el.textarea.value = '';
                this.autoResizeEl(this.el.textarea);
            }

            // Show user message immediately
            this.appendUserMessage(text);
            this.scrollToBottom();

            // Show typing indicator
            const typing = this.showTyping();

            try {
                const providerSelect = this.el.providerSelect;
                const selectedValue = providerSelect ? providerSelect.value : '';
                const [provider, model] = selectedValue ? selectedValue.split('|') : ['', ''];

                const result = await this.api('send_message', {
                    chat_id: this.chatId || '',
                    message: text,
                    provider: provider || undefined,
                    model: model || undefined,
                });

                typing.remove();

                if (result.success && result.result) {
                    this.chatId = result.chat_id;
                    this.appendAssistantMessage(
                        result.result.assistant_message,
                        result.result.tool_executions
                    );

                    // Update usage display
                    if (result.result.usage && this.el.usage) {
                        const u = result.result.usage;
                        this.el.usage.textContent = `${u.total_tokens.toLocaleString()} tokens`;
                    }

                    // Refresh sidebar
                    this.loadConversations();
                } else {
                    this.appendError(result.error || 'Unknown error');
                }
            } catch (err) {
                typing.remove();
                this.appendError(err.message || 'Network error');
            }

            this.sending = false;
            if (this.el.sendBtn) this.el.sendBtn.disabled = false;
            this.scrollToBottom();
        },

        // ─── Provider Switching ─────────────────────────────────

        async switchProvider() {
            if (!this.chatId) return;

            const val = this.el.providerSelect.value;
            const [provider, model] = val.split('|');

            await this.api('switch_provider', {
                chat_id: this.chatId,
                provider,
                model,
            });

            const select = this.el.providerSelect;
            const label = select.options[select.selectedIndex].text;
            this.appendProviderChange('Switched to ' + label);
        },

        // ─── Rendering ──────────────────────────────────────────

        appendUserMessage(text) {
            const div = document.createElement('div');
            div.className = 'ai-msg ai-msg-user';
            div.textContent = text;
            this.el.messages.appendChild(div);
        },

        appendAssistantMessage(text, toolExecutions) {
            const div = document.createElement('div');
            div.className = 'ai-msg ai-msg-assistant';

            // Render tool calls first
            if (toolExecutions && toolExecutions.length > 0) {
                for (const exec of toolExecutions) {
                    div.appendChild(this.renderToolCall(exec));
                }
            }

            // Render markdown text
            if (text) {
                const content = document.createElement('div');
                if (typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined') {
                    content.innerHTML = DOMPurify.sanitize(marked.parse(text));
                } else {
                    content.textContent = text;
                }

                // Apply syntax highlighting
                if (typeof hljs !== 'undefined') {
                    content.querySelectorAll('pre code').forEach(block => {
                        hljs.highlightElement(block);
                    });
                }

                div.appendChild(content);
            }

            this.el.messages.appendChild(div);
        },

        renderToolCall(exec) {
            const wrap = document.createElement('div');
            wrap.className = 'ai-tool-call';

            const header = document.createElement('div');
            header.className = 'ai-tool-call-header';

            const status = document.createElement('span');
            status.className = 'ai-tool-call-status ' + (exec.success !== false ? 'success' : 'error');
            status.innerHTML = exec.success !== false
                ? '<i class="fa-solid fa-circle-check"></i>'
                : '<i class="fa-solid fa-circle-xmark"></i>';

            const name = document.createElement('span');
            name.className = 'ai-tool-call-name';
            name.textContent = exec.tool || 'unknown';

            const chevron = document.createElement('span');
            chevron.className = 'ai-tool-call-chevron';
            chevron.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';

            header.appendChild(status);
            header.appendChild(name);
            header.appendChild(chevron);

            const body = document.createElement('div');
            body.className = 'ai-tool-call-body';

            const input = document.createElement('div');
            input.innerHTML = '<strong>Input:</strong>';
            const inputPre = document.createElement('pre');
            inputPre.textContent = JSON.stringify(exec.input || {}, null, 2);
            input.appendChild(inputPre);

            const output = document.createElement('div');
            output.style.marginTop = '0.5rem';
            output.innerHTML = '<strong>Output:</strong>';
            const outputPre = document.createElement('pre');
            outputPre.textContent = JSON.stringify(exec.output || {}, null, 2);
            output.appendChild(outputPre);

            body.appendChild(input);
            body.appendChild(output);

            header.addEventListener('click', () => {
                wrap.classList.toggle('open');
            });

            wrap.appendChild(header);
            wrap.appendChild(body);

            return wrap;
        },

        appendProviderChange(text) {
            const div = document.createElement('div');
            div.className = 'ai-provider-change';
            div.textContent = text;
            this.el.messages.appendChild(div);
        },

        appendError(text) {
            const div = document.createElement('div');
            div.className = 'ai-msg ai-msg-error';
            div.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                (typeof DOMPurify !== 'undefined' ? DOMPurify.sanitize(text) : this.escapeHtml(text));
            this.el.messages.appendChild(div);
        },

        showTyping() {
            const div = document.createElement('div');
            div.className = 'ai-typing';
            div.innerHTML = '<div class="ai-typing-dot"></div><div class="ai-typing-dot"></div><div class="ai-typing-dot"></div>';
            this.el.messages.appendChild(div);
            this.scrollToBottom();
            return div;
        },

        // ─── Helpers ────────────────────────────────────────────

        scrollToBottom() {
            if (this.el.messages) {
                this.el.messages.scrollTop = this.el.messages.scrollHeight;
            }
        },

        autoResizeEl(el) {
            if (!el) return;
            el.style.height = 'auto';
            el.style.height = Math.min(el.scrollHeight, 200) + 'px';
        },

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        },
    };

    // ─── Popup Menu ────────────────────────────────────────
    function initPopupMenu() {
        const footer = document.getElementById('ai-chat-footer-toggle');
        const popup = document.getElementById('ai-chat-popup-menu');
        if (!footer || !popup) return;

        footer.addEventListener('click', function (e) {
            // Don't toggle if clicking a link inside the popup
            if (e.target.closest('.ai-chat-popup-menu-item')) return;
            e.stopPropagation();
            popup.classList.toggle('open');
        });

        // Close popup when clicking outside
        document.addEventListener('click', function (e) {
            if (!footer.contains(e.target)) {
                popup.classList.remove('open');
            }
        });
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        // Init popup menu first (works on all pages including panels)
        initPopupMenu();

        const container = document.getElementById('ai-chat-app');
        if (!container) return;

        if (container.querySelector('.ai-chat-panel')) {
            // Panel view — sidebar buttons navigate back to chat
            const chatUrl = location.pathname.split('?')[0];
            const newBtn = container.querySelector('.ai-chat-new-btn');
            if (newBtn) {
                newBtn.addEventListener('click', () => { location.href = chatUrl; });
            }
            const chatsLink = container.querySelector('.ai-chat-nav-item[href="#"]');
            if (chatsLink) {
                chatsLink.addEventListener('click', (e) => { e.preventDefault(); location.href = chatUrl; });
            }
        } else {
            Chat.init(container);
        }
    });
})();
