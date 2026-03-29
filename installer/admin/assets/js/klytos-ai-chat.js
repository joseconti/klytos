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

        init(container) {
            this.el.container = container;
            this.csrf = container.dataset.csrf || '';
            this.apiUrl = container.dataset.apiUrl || 'api/ai-chat.php';

            // Cache DOM elements
            this.el.chatList = container.querySelector('.ai-chat-list');
            this.el.messages = container.querySelector('.ai-chat-messages');
            this.el.textarea = container.querySelector('.ai-chat-input textarea');
            this.el.sendBtn = container.querySelector('.ai-chat-send-btn');
            this.el.newBtn = container.querySelector('.ai-chat-new-btn');
            this.el.providerSelect = container.querySelector('#ai-provider-select');
            this.el.usage = container.querySelector('.ai-chat-usage');

            // Events
            this.el.sendBtn.addEventListener('click', () => this.sendMessage());
            this.el.textarea.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
            this.el.textarea.addEventListener('input', () => this.autoResize());
            this.el.newBtn.addEventListener('click', () => this.newConversation());

            if (this.el.providerSelect) {
                this.el.providerSelect.addEventListener('change', () => this.switchProvider());
            }

            // Load conversations
            this.loadConversations();
        },

        // ─── API Calls ───────────────────────────────────────────

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

        // ���── Conversations ────────────────────────────────────────

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
            this.el.messages.innerHTML = '';
            this.el.chatList.querySelectorAll('.ai-chat-item').forEach(el => {
                el.classList.remove('active');
            });
            this.el.textarea.focus();
        },

        async deleteConversation(chatId, itemEl) {
            if (!confirm('Delete this conversation?')) return;

            await this.api('delete_chat', { chat_id: chatId });
            itemEl.remove();

            if (this.chatId === chatId) {
                this.chatId = null;
                this.el.messages.innerHTML = '';
            }
        },

        // ─── Sending Messages ─────────────────────────────────────

        async sendMessage() {
            const text = this.el.textarea.value.trim();
            if (!text || this.sending) return;

            this.sending = true;
            this.el.sendBtn.disabled = true;
            this.el.textarea.value = '';
            this.autoResize();

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
            this.el.sendBtn.disabled = false;
            this.scrollToBottom();
        },

        // ─── Provider Switching ───────────────────────────────────

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

        // ─── Rendering ─────────���─────────────────────────────���───

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
            div.className = 'ai-msg ai-msg-assistant';
            div.style.borderColor = 'var(--admin-error)';
            div.style.color = 'var(--admin-error)';
            div.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                (typeof DOMPurify !== 'undefined' ? DOMPurify.sanitize(text) : text);
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

        // ─── Helpers ─────────────────���────────────────────────────

        scrollToBottom() {
            this.el.messages.scrollTop = this.el.messages.scrollHeight;
        },

        autoResize() {
            const ta = this.el.textarea;
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 200) + 'px';
        },
    };

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('ai-chat-app');
        if (container) {
            Chat.init(container);
        }
    });
})();
