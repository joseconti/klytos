/*
 * Klytos Admin — entry 23 (Terminal), the chrome's behaviour.
 *
 * Phase 4 Step 4, stage 6 of 6. This file drives everything AROUND the stream:
 * the copy control, the command reference disclosure, the polite status region,
 * and the revalidation dialog. The stream itself is the deferred engine
 * interior (D-104, `roadmap.md` §0c) — xterm.js owns it, and the reasons are in
 * `installer/admin/terminal.php`'s own header.
 *
 * It replaces a 500-line inline block in that PHP file. Three things changed
 * beyond moving it, and none is cosmetic:
 *
 *   1. **Every user-facing string comes from the catalogue** (NEW-33). The
 *      welcome banner, the two error paths and the status region were Spanish
 *      literals, several unaccented.
 *   2. **The command reference is a real disclosure** — `aria-expanded` and
 *      `hidden`, not `classList.toggle( 'active' )` on a `display:none` rule.
 *   3. **The revalidation overlay is a real dialog** — focus moved in, trapped
 *      (through the shell's own `trapFocus`, not a second copy of it), `Esc`
 *      out, focus returned to whatever had it. It previously had no role, no
 *      trap, no Esc and no cancel: the only way out was succeeding.
 *
 * Loaded with the page's CSP nonce. No inline handlers, anywhere.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

( function () {
    'use strict';

    var mount = document.getElementById( 'klytos-terminal' );
    var raw   = document.getElementById( 'terminal-strings' );

    if ( ! mount || ! raw || typeof Terminal !== 'function' ) {
        return;
    }

    var strings = JSON.parse( raw.textContent );
    var status  = document.getElementById( 'terminal-status' );

    var PROMPT = '\x1b[32mklytos\x1b[0m \x1b[90m>\x1b[0m ';

    // State.
    var currentInput     = '';
    var cursorPos        = 0;
    var commandHistory   = [];
    var historyIndex     = -1;
    var isExecuting      = false;
    var suggestions      = [];
    var commandsMetadata = {};
    var pendingCommand   = null;

    /*
     * Everything the terminal has printed, kept as plain text so **Copy all**
     * can name and copy it. xterm's own buffer is reachable only by walking
     * the renderer's rows, which returns the DRAWN grid — padded to the
     * terminal's width and stripped of anything scrolled past. This is what
     * "copies what the stream currently shows" (§2) means when the stream is a
     * canvas.
     */
    var transcript = [];

    function say( message ) {
        if ( status ) {
            status.textContent = message;
        }
    }

    function record( line ) {
        transcript.push( line );
    }

    /* ── The stream (deferred interior — xterm.js) ─────────────────────── */

    var term = new Terminal( {
        cursorBlink: true,
        cursorStyle: 'bar',
        fontSize: 14,
        fontFamily: "'SF Mono', 'Fira Code', 'Cascadia Code', 'Consolas', monospace",
        allowProposedApi: true,
        /*
         * xterm's own accessibility layer: it mirrors the canvas into a live
         * region for assistive technology. It is not a substitute for the
         * delivery's `<pre>` line model — that is the deferred interior — but
         * leaving it off would mean the canvas said nothing at all, which is a
         * choice this chrome does not have to make.
         */
        screenReaderMode: true,
    } );

    var fitAddon = new FitAddon.FitAddon();
    term.loadAddon( fitAddon );
    term.open( mount );
    fitAddon.fit();

    window.addEventListener( 'resize', function () { fitAddon.fit(); } );

    term.writeln( strings.welcomeIntro );
    term.writeln( strings.welcomeKeys );
    term.writeln( '' );
    record( strings.welcomeIntro );
    record( strings.welcomeKeys );
    writePrompt();

    loadCommandList();

    /* ── Input ─────────────────────────────────────────────────────────── */

    term.onKey( function ( e ) {
        if ( isExecuting ) {
            return;
        }

        var key      = e.key;
        var domEvent = e.domEvent;
        var code     = domEvent.keyCode;
        var ctrlKey  = domEvent.ctrlKey;

        // Ctrl+C: clears the unsent input line.
        //
        // It does NOT cancel a running command, and the guard above is why:
        // `isExecuting` covers the whole fetch. That is not a wiring bug to be
        // fixed here — `dispatch()` runs handlers synchronously in-process
        // with no interrupt point, so there is nothing to cancel. It is part
        // of the deferred interior (D-104), recorded rather than papered over.
        if ( ctrlKey && code === 67 ) {
            term.write( '^C\r\n' );
            currentInput = '';
            cursorPos    = 0;
            historyIndex = -1;
            writePrompt();
            return;
        }

        if ( ctrlKey && code === 76 ) {
            term.clear();
            transcript = [];
            writePrompt();
            return;
        }

        if ( code === 13 ) {
            term.write( '\r\n' );
            var cmd      = currentInput.trim();
            currentInput = '';
            cursorPos    = 0;
            historyIndex = -1;

            if ( cmd !== '' ) {
                if ( commandHistory.length === 0 || commandHistory[ commandHistory.length - 1 ] !== cmd ) {
                    commandHistory.push( cmd );
                }
                executeCommand( cmd );
            } else {
                writePrompt();
            }
            return;
        }

        if ( code === 8 ) {
            if ( cursorPos > 0 ) {
                currentInput = currentInput.slice( 0, cursorPos - 1 ) + currentInput.slice( cursorPos );
                cursorPos--;
                refreshLine();
            }
            return;
        }

        if ( code === 46 ) {
            if ( cursorPos < currentInput.length ) {
                currentInput = currentInput.slice( 0, cursorPos ) + currentInput.slice( cursorPos + 1 );
                refreshLine();
            }
            return;
        }

        if ( code === 9 ) {
            domEvent.preventDefault();
            autocomplete();
            return;
        }

        if ( code === 38 ) {
            if ( commandHistory.length > 0 ) {
                if ( historyIndex === -1 ) {
                    historyIndex = commandHistory.length - 1;
                } else if ( historyIndex > 0 ) {
                    historyIndex--;
                }
                currentInput = commandHistory[ historyIndex ];
                cursorPos    = currentInput.length;
                refreshLine();
            }
            return;
        }

        if ( code === 40 ) {
            if ( historyIndex !== -1 ) {
                if ( historyIndex < commandHistory.length - 1 ) {
                    historyIndex++;
                    currentInput = commandHistory[ historyIndex ];
                } else {
                    historyIndex = -1;
                    currentInput = '';
                }
                cursorPos = currentInput.length;
                refreshLine();
            }
            return;
        }

        if ( code === 37 ) {
            if ( cursorPos > 0 ) {
                cursorPos--;
                term.write( '\x1b[D' );
            }
            return;
        }

        if ( code === 39 ) {
            if ( cursorPos < currentInput.length ) {
                cursorPos++;
                term.write( '\x1b[C' );
            }
            return;
        }

        if ( code === 36 ) {
            while ( cursorPos > 0 ) {
                cursorPos--;
                term.write( '\x1b[D' );
            }
            return;
        }

        if ( code === 35 ) {
            while ( cursorPos < currentInput.length ) {
                cursorPos++;
                term.write( '\x1b[C' );
            }
            return;
        }

        if ( key.length === 1 && ! ctrlKey ) {
            currentInput = currentInput.slice( 0, cursorPos ) + key + currentInput.slice( cursorPos );
            cursorPos++;
            refreshLine();
        }
    } );

    function writePrompt() {
        term.write( PROMPT );
    }

    function refreshLine() {
        term.write( '\r\x1b[K' );
        term.write( PROMPT + currentInput );
        var diff = currentInput.length - cursorPos;
        if ( diff > 0 ) {
            term.write( '\x1b[' + diff + 'D' );
        }
    }

    /* ── Execution ─────────────────────────────────────────────────────── */

    function executeCommand( cmd ) {
        if ( cmd.toLowerCase() === 'clear' ) {
            term.clear();
            transcript = [];
            writePrompt();
            return;
        }

        isExecuting = true;
        /*
         * The machine-readable half of "Running": `aria-busy` on the canvas
         * for exactly the duration of the request, beside the polite sentence
         * in the status region. §2's indeterminate progressbar and its ELAPSED
         * SECONDS are not here, and cannot be: nothing in the path measures a
         * command's duration (D-104). A spinner with no number behind it would
         * be a drawing of a fact nobody has.
         */
        mount.setAttribute( 'aria-busy', 'true' );
        say( strings.running.replace( '{command}', cmd ) );
        record( 'klytos > ' + cmd );

        fetch( strings.apiBase + 'api/terminal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': strings.csrf,
            },
            body: JSON.stringify( { command: cmd } ),
        } )
            .then( function ( response ) {
                if ( ! response.ok ) {
                    return response.json().catch( function () { return {}; } ).then( function ( errorData ) {
                        writeError( errorData.error || strings.serverError );
                    } );
                }
                return response.json().then( function ( data ) {
                    if ( data.requires_2fa ) {
                        pendingCommand = cmd;
                        openRevalidation();
                        return;
                    }

                    if ( data.output === '__CLEAR__' ) {
                        term.clear();
                        transcript = [];
                        return;
                    }

                    if ( data.output ) {
                        data.output.split( '\n' ).forEach( function ( line ) {
                            if ( data.success ) {
                                term.writeln( line );
                            } else {
                                term.writeln( '\x1b[31m' + line + '\x1b[0m' );
                            }
                            record( line );
                        } );
                    }
                } );
            } )
            .catch( function ( err ) {
                writeError( strings.connectionError.replace( '{message}', err.message ) );
            } )
            .finally( function () {
                isExecuting = false;
                mount.setAttribute( 'aria-busy', 'false' );
                say( strings.finished );
                writePrompt();
            } );
    }

    function writeError( message ) {
        term.writeln( '\x1b[31m' + message + '\x1b[0m' );
        record( message );
    }

    /* ── Copy all (§2) ─────────────────────────────────────────────────── */

    var copyAll = document.getElementById( 'terminal-copy-all' );

    if ( copyAll ) {
        copyAll.addEventListener( 'click', function () {
            if ( ! transcript.length ) {
                say( strings.copyEmpty );
                return;
            }
            if ( ! navigator.clipboard ) {
                say( strings.copyFailed );
                return;
            }
            navigator.clipboard.writeText( transcript.join( '\n' ) ).then(
                function () { say( strings.copied ); },
                function () { say( strings.copyFailed ); }
            );
        } );
    }

    /* ── Autocomplete + the command reference ──────────────────────────── */

    function loadCommandList() {
        fetch( strings.apiBase + 'api/terminal-autocomplete.php?q=', {
            headers: { 'X-CSRF-Token': strings.csrf },
        } )
            .then( function ( response ) { return response.json(); } )
            .then( function ( data ) {
                suggestions      = data.suggestions || [];
                commandsMetadata = data.commands || {};
                populateCommandPanel();
            } )
            .catch( function () {
                // Autocomplete simply does not work; the terminal still does.
            } );
    }

    function autocomplete() {
        var input = currentInput.toLowerCase();
        if ( ! input ) {
            return;
        }

        var matches = suggestions.filter( function ( s ) { return s.indexOf( input ) === 0; } );

        if ( matches.length === 0 ) {
            return;
        }

        if ( matches.length === 1 ) {
            currentInput = matches[ 0 ] + ' ';
            cursorPos    = currentInput.length;
            refreshLine();
            return;
        }

        term.write( '\r\n' );
        term.writeln( matches.map( function ( m ) { return '\x1b[32m' + m + '\x1b[0m'; } ).join( '   ' ) );
        record( matches.join( '   ' ) );
        writePrompt();
        term.write( currentInput );

        var common = commonPrefix( matches );
        if ( common.length > input.length ) {
            currentInput = common;
            cursorPos    = currentInput.length;
            refreshLine();
        }
    }

    function commonPrefix( list ) {
        if ( list.length === 0 ) {
            return '';
        }
        var prefix = list[ 0 ];
        for ( var i = 1; i < list.length; i++ ) {
            while ( list[ i ].indexOf( prefix ) !== 0 ) {
                prefix = prefix.slice( 0, -1 );
            }
        }
        return prefix;
    }

    function populateCommandPanel() {
        var panel = document.getElementById( 'cmd-panel-list' );
        var empty = document.getElementById( 'cmd-panel-empty' );
        if ( ! panel ) {
            return;
        }

        var categories = {};
        Object.keys( commandsMetadata ).forEach( function ( cmd ) {
            var meta = commandsMetadata[ cmd ];
            var cat  = meta.category || 'general';
            if ( ! categories[ cat ] ) {
                categories[ cat ] = [];
            }
            categories[ cat ].push( {
                name: cmd,
                description: meta.description || '',
                usage: meta.usage || cmd,
            } );
        } );

        /*
         * Built as DOM NODES, never as an HTML string.
         *
         * `commandsMetadata` is not ours: `api/terminal-autocomplete.php`
         * serves it from `TerminalExecutor::getCommandsMetadata()`, which
         * passes the command table through the `terminal.commands` filter — so
         * `name`, `description`, `usage` and `category` are all values an
         * installed PLUGIN can set. The original implementation concatenated
         * them into a string and assigned it to `panel.innerHTML`, which made a
         * command description a script-execution primitive in the owner's
         * admin (fixed 2026-08-10, `106f6a8`; reproduced first, red observed,
         * in tests/E2E/terminal.spec.js).
         *
         * `textContent` and `setAttribute` cannot produce an element or an
         * attribute from a value, whatever the value contains — which is why
         * the fix is a change of MECHANISM and not an escaping function. An
         * escaper has to be remembered at every interpolation; this cannot be
         * forgotten at one of them and still look right at the others.
         */
        panel.textContent = '';

        var names = Object.keys( categories );

        if ( empty ) {
            empty.hidden = names.length > 0;
        }

        names.forEach( function ( cat ) {
            var heading = document.createElement( 'h3' );
            heading.textContent = strings.categoryLabels[ cat ] || cat;
            panel.appendChild( heading );

            categories[ cat ].forEach( function ( cmd ) {
                var item = document.createElement( 'div' );
                item.className = 'k-pair';
                item.setAttribute( 'title', cmd.usage );

                var name = document.createElement( 'code' );
                name.className = 'k-code-key';
                name.textContent = cmd.name;

                var desc = document.createElement( 'span' );
                desc.textContent = cmd.description;

                item.appendChild( name );
                item.appendChild( desc );
                panel.appendChild( item );
            } );
        } );
    }

    var toggle = document.getElementById( 'toggle-cmd-panel' );
    var panel  = document.getElementById( 'cmd-panel' );
    var close  = document.getElementById( 'close-cmd-panel' );

    function setPanel( open ) {
        if ( ! toggle || ! panel ) {
            return;
        }
        panel.hidden = ! open;
        toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
    }

    if ( toggle ) {
        toggle.addEventListener( 'click', function () {
            setPanel( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
        } );
    }

    if ( close ) {
        close.addEventListener( 'click', function () {
            setPanel( false );
            // Focus goes back to what opened the panel, never to the top of
            // the document (accessibility.md §3.2).
            if ( toggle ) {
                toggle.focus();
            }
        } );
    }

    /* ── The revalidation dialog (accessibility.md §3.2) ───────────────── */

    var modal      = document.getElementById( 'revalidation-modal' );
    var codeInput  = document.getElementById( 'revalidation-code' );
    var codeError  = document.getElementById( 'revalidation-error' );
    var submit     = document.getElementById( 'revalidation-submit' );
    var cancel     = document.getElementById( 'revalidation-cancel' );
    var modalOpener = null;

    function openRevalidation() {
        if ( ! modal ) {
            return;
        }
        modalOpener  = document.activeElement;
        modal.hidden = false;
        if ( codeError ) {
            codeError.hidden      = true;
            codeError.textContent = '';
        }
        if ( codeInput ) {
            codeInput.value = '';
            codeInput.focus();
        }
    }

    function closeRevalidation() {
        if ( ! modal ) {
            return;
        }
        modal.hidden   = true;
        pendingCommand = null;
        // Focus returns to whatever had it, then the prompt is reachable again.
        if ( modalOpener && typeof modalOpener.focus === 'function' ) {
            modalOpener.focus();
        } else {
            mount.focus();
        }
        modalOpener = null;
    }

    function failRevalidation( message ) {
        if ( codeError ) {
            codeError.textContent = message;
            codeError.hidden      = false;
        }
        if ( codeInput ) {
            codeInput.value = '';
            codeInput.focus();
        }
    }

    function revalidate() {
        if ( ! codeInput ) {
            return;
        }
        var code = codeInput.value.trim();
        if ( ! code ) {
            return;
        }

        fetch( strings.apiBase + 'api/terminal-revalidate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': strings.csrf,
            },
            body: JSON.stringify( { code: code, method: 'totp' } ),
        } )
            .then( function ( response ) { return response.json(); } )
            .then( function ( data ) {
                if ( ! data.success ) {
                    failRevalidation( strings.revalidateBad );
                    return;
                }
                var retry = pendingCommand;
                closeRevalidation();
                if ( retry ) {
                    executeCommand( retry );
                }
            } )
            .catch( function () {
                failRevalidation( strings.revalidateError );
            } );
    }

    if ( submit ) {
        submit.addEventListener( 'click', revalidate );
    }

    if ( cancel ) {
        cancel.addEventListener( 'click', function () {
            closeRevalidation();
            // The command was never run; say so rather than leaving the last
            // "Running …" standing.
            say( strings.finished );
            writePrompt();
        } );
    }

    if ( modal ) {
        modal.addEventListener( 'keydown', function ( event ) {
            if ( event.key === 'Escape' ) {
                closeRevalidation();
                say( strings.finished );
                writePrompt();
                return;
            }
            if ( event.key === 'Enter' && document.activeElement === codeInput ) {
                event.preventDefault();
                revalidate();
                return;
            }
            if ( event.key === 'Tab' && window.KlytosShell && window.KlytosShell.trapFocus ) {
                window.KlytosShell.trapFocus( modal, event );
            }
        } );
    }
}() );
