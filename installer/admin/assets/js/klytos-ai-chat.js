/**
 * Klytos — AI chat (manifest entry 12, template `conversation`).
 *
 * Built in Phase 4 Step 4, stage 6 against `SPEC/screens/template-conversation.md`
 * and `SPEC/accessibility.md`.
 *
 * WHAT THIS FILE DOES NOT DO, ON PURPOSE (D-104, `roadmap.md` §0c): there is no
 * streaming, no Stop, no running tool call, no inline permission confirm and no
 * "Load earlier messages". `admin/api/ai-chat.php` answers once with the whole
 * turn and `core/ai/chat-engine.php` has no streaming path, so every one of
 * those is a state of a partial turn that cannot exist here. The transcript is
 * still `role="log" aria-live="polite" aria-relevant="additions"`, which gives
 * the outcome §2 wants from streaming: the finished turn is announced, never
 * each token.
 *
 * @package Klytos
 * @since   0.9.0
 */

( function () {
    'use strict';

    var cfgNode = document.getElementById( 'ai-chat-config' );
    if ( ! cfgNode ) {
        return;
    }

    var CFG = JSON.parse( cfgNode.textContent || '{}' );
    var S   = CFG.strings || {};

    var el = {
        screen:        document.querySelector( '.k-conv' ),
        transcript:    document.getElementById( 'ai-chat-transcript' ),
        starters:      document.getElementById( 'ai-chat-starters' ),
        status:        document.getElementById( 'ai-chat-status' ),
        composer:      document.getElementById( 'ai-chat-composer' ),
        input:         document.getElementById( 'ai-chat-input' ),
        send:          document.querySelector( '.k-conv-send' ),
        model:         document.getElementById( 'ai-chat-model' ),
        newBtn:        document.getElementById( 'ai-chat-new' ),
        historyToggle: document.getElementById( 'ai-chat-history-toggle' ),
        history:       document.getElementById( 'ai-chat-history' ),
        historyClose:  document.getElementById( 'ai-chat-history-close' ),
        historySearch: document.getElementById( 'ai-chat-history-search' ),
        historyList:   document.getElementById( 'ai-chat-history-list' ),
        historyEmpty:  document.getElementById( 'ai-chat-history-empty' )
    };

    var state = {
        chatId: null,
        sending: false,
        lastUserMessage: '',
        jumpLink: null
    };

    // ─── Small helpers ──────────────────────────────────────────────

    function say( text ) {
        if ( el.status ) {
            el.status.textContent = text || '';
        }
    }

    function glyph( id, cls ) {
        var svg = document.createElementNS( 'http://www.w3.org/2000/svg', 'svg' );
        svg.setAttribute( 'class', cls || 'k-conv-glyph' );
        svg.setAttribute( 'aria-hidden', 'true' );
        svg.setAttribute( 'focusable', 'false' );
        var use = document.createElementNS( 'http://www.w3.org/2000/svg', 'use' );
        use.setAttribute( 'href', CFG.sprite + '#' + id );
        svg.appendChild( use );
        return svg;
    }

    /**
     * The turn's accessible name — §2: "You, 14:02" / "Klytos AI, 14:02".
     * The time is the browser's local rendering of a real timestamp; the
     * project stores UTC and displays local, and this is the display half.
     */
    function turnName( who, date ) {
        var time = ( date || new Date() ).toLocaleTimeString( document.documentElement.lang || 'en', {
            hour: '2-digit',
            minute: '2-digit'
        } );
        return who + ', ' + time;
    }

    function textOf( node ) {
        var body = node.querySelector( '.k-turn-body' );
        return body ? body.textContent.trim() : '';
    }

    async function copyText( text ) {
        try {
            await navigator.clipboard.writeText( text );
            say( S.copied );
        } catch ( err ) {
            say( S.copyFailed );
        }
    }

    // ─── Scroll behaviour (§2 Streaming: auto-scroll follows the bottom
    //     only while the user is already at the bottom) ────────────────

    function atBottom() {
        if ( ! el.transcript ) {
            return true;
        }
        return el.transcript.scrollHeight - el.transcript.scrollTop - el.transcript.clientHeight < 24;
    }

    function scrollToBottom() {
        if ( el.transcript ) {
            el.transcript.scrollTop = el.transcript.scrollHeight;
        }
        hideJump();
    }

    function showJump() {
        if ( state.jumpLink || ! el.screen ) {
            return;
        }
        var btn = document.createElement( 'button' );
        btn.type = 'button';
        btn.className = 'k-btn k-btn--secondary k-btn--sm k-conv-jump';
        btn.textContent = S.jumpToLatest;
        btn.setAttribute( 'data-testid', 'ai_chat.jump' );
        btn.addEventListener( 'click', function () {
            scrollToBottom();
            if ( el.input ) {
                el.input.focus();
            }
        } );
        el.screen.insertBefore( btn, el.screen.querySelector( '.k-conv-chips' ) );
        state.jumpLink = btn;
    }

    function hideJump() {
        if ( state.jumpLink ) {
            state.jumpLink.remove();
            state.jumpLink = null;
        }
    }

    // ─── Turn rendering ─────────────────────────────────────────────

    function hideStarters() {
        if ( el.starters && ! el.starters.hidden ) {
            el.starters.hidden = true;
        }
    }

    /**
     * One turn. §2 Focus: "Each turn is a focusable role=article with a name",
     * and §2 Hover: the actions "are in the DOM at all times and focusable" —
     * which is why they are rendered here and only their VISIBILITY is a hover
     * or focus concern of the stylesheet.
     */
    function makeTurn( who, kind ) {
        var art = document.createElement( 'article' );
        art.className = 'k-turn k-turn--' + kind;
        art.tabIndex = 0;
        art.setAttribute( 'aria-label', turnName( who ) );
        art.setAttribute( 'data-testid', 'ai_chat.turn.' + kind );
        return art;
    }

    function makeActions( turn, opts ) {
        var wrap = document.createElement( 'div' );
        wrap.className = 'k-turn-actions';

        var copy = document.createElement( 'button' );
        copy.type = 'button';
        copy.className = 'k-btn k-btn--secondary k-btn--sm';
        copy.setAttribute( 'data-testid', 'ai_chat.turn_copy' );
        copy.appendChild( glyph( 'ks-content_copy' ) );
        var copyLabel = document.createElement( 'span' );
        copyLabel.className = 'k-sr';
        copyLabel.textContent = S.copy;
        copy.appendChild( copyLabel );
        copy.addEventListener( 'click', function () {
            copyText( textOf( turn ) );
        } );
        wrap.appendChild( copy );

        if ( opts && opts.retry ) {
            var retry = document.createElement( 'button' );
            retry.type = 'button';
            retry.className = 'k-btn k-btn--secondary k-btn--sm';
            retry.textContent = S.retry;
            retry.setAttribute( 'data-testid', 'ai_chat.turn_retry' );
            retry.addEventListener( 'click', function () {
                if ( state.lastUserMessage ) {
                    send( state.lastUserMessage );
                }
            } );
            wrap.appendChild( retry );
        }

        return wrap;
    }

    function appendUserTurn( text ) {
        hideStarters();
        var art  = makeTurn( S.you, 'user' );
        var body = document.createElement( 'div' );
        body.className = 'k-turn-body';
        body.textContent = text;
        art.appendChild( body );
        art.appendChild( makeActions( art, { retry: false } ) );
        el.transcript.appendChild( art );
        return art;
    }

    /**
     * A finished tool call. §2 gives two reachable states — done and failed —
     * and two that are states of a partial turn (running, needs permission) and
     * are therefore not built here.
     *
     * §5.10: the rows are `<li>` in an `<ol aria-label="Tool calls">` "with
     * their status as text", never a glyph alone.
     */
    function makeToolList( executions ) {
        var ol = document.createElement( 'ol' );
        ol.className = 'k-toolcalls';
        ol.setAttribute( 'aria-label', S.toolCalls );

        executions.forEach( function ( exec ) {
            var ok = exec.success !== false;
            var li = document.createElement( 'li' );
            li.className = 'k-toolcall k-toolcall--' + ( ok ? 'done' : 'failed' );
            li.setAttribute( 'data-testid', 'ai_chat.toolcall.' + ( ok ? 'done' : 'failed' ) );

            var details = document.createElement( 'details' );
            var summary = document.createElement( 'summary' );
            summary.appendChild( glyph( ok ? 'ks-check_circle' : 'ks-error', 'k-toolcall-glyph' ) );

            var status = document.createElement( 'span' );
            status.className = 'k-toolcall-status';
            status.textContent = ( ok ? S.toolRan : S.toolFailed ).replace( '%s', exec.tool || '' );
            summary.appendChild( status );
            details.appendChild( summary );

            var body = document.createElement( 'div' );
            body.className = 'k-toolcall-body';
            body.appendChild( payload( S.toolInput, exec.input ) );
            body.appendChild( payload( S.toolOutput, exec.output ) );
            details.appendChild( body );

            li.appendChild( details );

            /*
             * §2 "Tool call — failed" offers a retry. "Open the page" is not
             * built: it names a record only the tool's own output could
             * identify, and inventing that link is inventing product behaviour.
             */
            if ( ! ok ) {
                var retry = document.createElement( 'button' );
                retry.type = 'button';
                retry.className = 'k-btn k-btn--secondary k-btn--sm';
                retry.textContent = S.retry;
                retry.setAttribute( 'data-testid', 'ai_chat.toolcall_retry' );
                retry.addEventListener( 'click', function () {
                    if ( state.lastUserMessage ) {
                        send( state.lastUserMessage );
                    }
                } );
                li.appendChild( retry );
            }

            ol.appendChild( li );
        } );

        return ol;
    }

    /**
     * §5.12: "Code/payload block = <pre><code> with tabindex=0 and an
     * aria-label naming what it is, because it scrolls."
     */
    function payload( label, value ) {
        var wrap = document.createElement( 'div' );
        var pre  = document.createElement( 'pre' );
        pre.className = 'k-code';
        pre.tabIndex = 0;
        pre.setAttribute( 'aria-label', label );
        var code = document.createElement( 'code' );
        code.textContent = JSON.stringify( value || {}, null, 2 );
        pre.appendChild( code );
        wrap.appendChild( pre );
        return wrap;
    }

    function appendAssistantTurn( text, executions ) {
        hideStarters();
        var art = makeTurn( S.assistant, 'agent' );

        if ( executions && executions.length ) {
            art.appendChild( makeToolList( executions ) );
        }

        var body = document.createElement( 'div' );
        body.className = 'k-turn-body';

        if ( text ) {
            if ( typeof marked !== 'undefined' && typeof DOMPurify !== 'undefined' ) {
                body.innerHTML = DOMPurify.sanitize( marked.parse( text ) );
                if ( typeof hljs !== 'undefined' ) {
                    body.querySelectorAll( 'pre code' ).forEach( function ( block ) {
                        hljs.highlightElement( block );
                    } );
                }
            } else {
                body.textContent = text;
            }
        }

        art.appendChild( body );
        art.appendChild( makeActions( art, { retry: true } ) );
        el.transcript.appendChild( art );
        return art;
    }

    /**
     * §2 "Error — the model is unreachable": a role=alert row in the
     * transcript, with Retry and Open Settings, and the composer stays usable.
     */
    function appendError( message ) {
        hideStarters();
        var row = document.createElement( 'div' );
        row.className = 'k-error k-conv-error';
        row.setAttribute( 'role', 'alert' );
        row.setAttribute( 'data-testid', 'ai_chat.error' );

        var text = document.createElement( 'span' );
        text.textContent = S.unreachable.replace( '%s', message || '' );
        row.appendChild( text );

        var retry = document.createElement( 'button' );
        retry.type = 'button';
        retry.className = 'k-btn k-btn--secondary k-btn--sm';
        retry.textContent = S.retry;
        retry.setAttribute( 'data-testid', 'ai_chat.error_retry' );
        retry.addEventListener( 'click', function () {
            if ( state.lastUserMessage ) {
                send( state.lastUserMessage );
            }
        } );
        row.appendChild( retry );

        var settings = document.createElement( 'a' );
        settings.href = CFG.settingsUrl;
        settings.textContent = S.openSettings;
        settings.setAttribute( 'data-testid', 'ai_chat.error_settings' );
        row.appendChild( settings );

        el.transcript.appendChild( row );
    }

    // ─── API ────────────────────────────────────────────────────────

    async function api( action, data, method ) {
        data   = data || {};
        method = method || 'POST';

        var url  = CFG.apiUrl;
        var opts = { method: method, headers: {} };

        if ( method === 'POST' ) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify( Object.assign( {}, data, { action: action, csrf: CFG.csrf } ) );
        } else {
            url = CFG.apiUrl + '?action=' + encodeURIComponent( action ) + '&' + new URLSearchParams( data );
        }

        var res = await fetch( url, opts );
        return res.json();
    }

    // ─── Sending ────────────────────────────────────────────────────

    /**
     * §2 Sending: "the send button goes aria-busy=true; the composer stays
     * enabled so the next message can be typed." The button is not disabled —
     * that is the difference, and it is deliberate.
     */
    async function send( text ) {
        if ( ! text || state.sending ) {
            return;
        }

        state.sending = true;
        state.lastUserMessage = text;

        if ( el.send ) {
            el.send.setAttribute( 'aria-busy', 'true' );
        }
        if ( el.input ) {
            el.input.value = '';
            autoGrow( el.input );
        }

        var wasAtBottom = atBottom();
        appendUserTurn( text );
        if ( wasAtBottom ) {
            scrollToBottom();
        }
        say( S.sending );

        var selected = el.model ? el.model.value : '';
        var parts    = selected ? selected.split( '|' ) : [ '', '' ];

        try {
            var result = await api( 'send_message', {
                chat_id: state.chatId || '',
                message: text,
                provider: parts[0] || undefined,
                model: parts[1] || undefined
            } );

            if ( result.success && result.result ) {
                state.chatId = result.chat_id;
                if ( result.result.status === 'error' ) {
                    appendError( result.result.error || '' );
                } else {
                    appendAssistantTurn( result.result.assistant_message, result.result.tool_executions );
                }
                /*
                 * Deliberately CLEARED rather than filled. The transcript is
                 * itself `role="log" aria-live="polite"`, so the finished turn
                 * is already announced in full; a second region saying "answer
                 * received" announces the same event twice, and §5's rule is
                 * that the copilot does not interrupt. The SENDING message
                 * stays, because nothing else reports that.
                 */
                say( '' );
            } else {
                appendError( result.error || '' );
                say( '' );
            }
        } catch ( err ) {
            appendError( S.networkError );
            say( '' );
        }

        state.sending = false;
        if ( el.send ) {
            el.send.setAttribute( 'aria-busy', 'false' );
        }

        if ( atBottom() ) {
            scrollToBottom();
        } else {
            showJump();
        }
    }

    /** §1: the composer auto-grows 34 → 120px. */
    function autoGrow( node ) {
        node.style.height = 'auto';
        node.style.height = Math.min( Math.max( node.scrollHeight, 34 ), 120 ) + 'px';
    }

    // ─── Conversation history ───────────────────────────────────────

    function openHistory() {
        el.history.hidden = false;
        el.historyToggle.setAttribute( 'aria-expanded', 'true' );
        loadHistory();
        if ( el.historySearch ) {
            el.historySearch.focus();
        }
    }

    function closeHistory() {
        el.history.hidden = true;
        el.historyToggle.setAttribute( 'aria-expanded', 'false' );
        el.historyToggle.focus();
    }

    async function loadHistory( query ) {
        var result = query
            ? await api( 'search_chats', { q: query }, 'GET' )
            : await api( 'list_chats', { limit: 50 }, 'GET' );

        if ( ! result.success ) {
            return;
        }

        var chats = result.chats || [];
        el.historyList.textContent = '';
        el.historyEmpty.hidden = chats.length > 0;

        chats.forEach( function ( chat ) {
            el.historyList.appendChild( historyRow( chat ) );
        } );

        say( S.historyCount.replace( '%s', String( chats.length ) ) );
    }

    /**
     * One conversation. Delete is a TWO-STEP confirm rather than the shipped
     * `window.confirm()`: a native dialog blocks the page for everyone and is
     * unreachable to a driven test, and the admin already has an armed-confirm
     * pattern (`.k-confirm-wrap`).
     */
    function historyRow( chat ) {
        var li = document.createElement( 'li' );
        li.className = 'k-conv-history-item';

        var open = document.createElement( 'button' );
        open.type = 'button';
        open.className = 'k-conv-history-open';
        open.textContent = chat.title || S.untitled;
        open.setAttribute( 'data-testid', 'ai_chat.history_open' );
        open.addEventListener( 'click', function () {
            loadConversation( chat.id );
        } );
        li.appendChild( open );

        var wrap = document.createElement( 'span' );
        wrap.className = 'k-confirm-wrap';

        var del = document.createElement( 'button' );
        del.type = 'button';
        del.className = 'k-btn k-btn--destructive k-btn--sm';
        del.textContent = S.deleteConversation;
        del.setAttribute( 'data-testid', 'ai_chat.history_delete' );

        del.addEventListener( 'click', async function () {
            if ( del.dataset.armed !== 'true' ) {
                del.dataset.armed = 'true';
                del.textContent = S.deleteConfirm;
                return;
            }
            await api( 'delete_chat', { chat_id: chat.id } );
            li.remove();
            if ( state.chatId === chat.id ) {
                newConversation();
            }
            el.historyEmpty.hidden = el.historyList.children.length > 0;
        } );

        wrap.appendChild( del );
        li.appendChild( wrap );
        return li;
    }

    async function loadConversation( chatId ) {
        var result = await api( 'get_chat', { chat_id: chatId }, 'GET' );
        if ( ! result.success || ! result.chat ) {
            return;
        }

        state.chatId = chatId;
        resetTranscript( false );

        ( result.chat.messages || [] ).forEach( function ( msg ) {
            if ( msg.role === 'user' ) {
                state.lastUserMessage = msg.content;
                appendUserTurn( msg.content );
            } else if ( msg.role === 'assistant' ) {
                appendAssistantTurn( msg.content, msg.tool_executions );
            }
        } );

        closeHistory();
        scrollToBottom();
    }

    function resetTranscript( showStarters ) {
        var starters = el.starters;
        el.transcript.textContent = '';
        if ( starters ) {
            starters.hidden = ! showStarters;
            el.transcript.appendChild( starters );
        }
        hideJump();
    }

    function newConversation() {
        state.chatId = null;
        state.lastUserMessage = '';
        resetTranscript( true );
        say( '' );
        if ( el.input ) {
            el.input.focus();
        }
    }

    // ─── Wiring ─────────────────────────────────────────────────────

    if ( el.composer ) {
        el.composer.addEventListener( 'submit', function ( e ) {
            e.preventDefault();
            send( el.input.value.trim() );
        } );
    }

    if ( el.input ) {
        el.input.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Enter' && ! e.shiftKey ) {
                e.preventDefault();
                send( el.input.value.trim() );
            }
        } );
        el.input.addEventListener( 'input', function () {
            autoGrow( el.input );
        } );
        autoGrow( el.input );

        /*
         * §2 Default/idle: "composer focused in the full-screen chat, not
         * focused in the dock". §5's "nothing auto-focuses" governs the
         * conversation once it is running — it is the rule that keeps focus
         * still DURING a turn — and §2 is the specific statement about this
         * screen's initial state. There is no composer at all when no provider
         * is configured, which is what makes the shipped defect unrepeatable.
         */
        el.input.focus();
    }

    if ( el.transcript ) {
        el.transcript.addEventListener( 'scroll', function () {
            if ( atBottom() ) {
                hideJump();
            }
        } );
    }

    document.querySelectorAll( '.k-conv-starter' ).forEach( function ( btn ) {
        btn.addEventListener( 'click', function () {
            if ( el.input ) {
                el.input.value = btn.textContent.trim();
                autoGrow( el.input );
                el.input.focus();
            }
        } );
    } );

    if ( el.newBtn ) {
        el.newBtn.addEventListener( 'click', newConversation );
    }

    if ( el.historyToggle ) {
        el.historyToggle.addEventListener( 'click', function () {
            if ( el.history.hidden ) {
                openHistory();
            } else {
                closeHistory();
            }
        } );
    }

    if ( el.historyClose ) {
        el.historyClose.addEventListener( 'click', closeHistory );
    }

    if ( el.history ) {
        el.history.addEventListener( 'keydown', function ( e ) {
            if ( e.key === 'Escape' ) {
                closeHistory();
            }
        } );
    }

    if ( el.historySearch ) {
        var debounce = null;
        el.historySearch.addEventListener( 'input', function () {
            clearTimeout( debounce );
            debounce = setTimeout( function () {
                loadHistory( el.historySearch.value.trim() );
            }, 300 );
        } );
    }

    if ( el.model ) {
        el.model.addEventListener( 'change', async function () {
            if ( ! state.chatId ) {
                return;
            }
            var parts = ( el.model.value || '' ).split( '|' );
            await api( 'switch_provider', {
                chat_id: state.chatId,
                provider: parts[0],
                model: parts[1]
            } );
            say( S.providerChanged );
        } );
    }
} )();
