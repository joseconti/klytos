<?php
/**
 * Klytos Admin -- Terminal Page
 * Integrated pseudo-terminal for executing Klytos CLI commands from the browser.
 * Requires 2FA active + terminal.access permission (owner only).
 *
 * Uses xterm.js for terminal rendering. All commands execute via pure PHP
 * through the TerminalExecutor class -- no exec/shell_exec.
 *
 * @package Klytos
 * @since   0.12.0
 *
 * @license    Elastic License 2.0 (ELv2) -- https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 Jose Conti -- https://plugins.joseconti.com -- https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;

// Gate: must have terminal.access permission + 2FA active.
$currentUser = klytos_current_user();
if ( ! klytos_has_permission( 'terminal.access' ) || empty( $currentUser['two_factor']['enabled'] ) ) {
    header( 'Location: ' . Helpers::getBasePath() . 'admin/' );
    exit;
}

$pageTitle = 'Terminal';

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';

$csrfToken = $_SESSION['klytos_csrf'] ?? '';
$apiBase   = klytos_esc_url( Helpers::getBasePath() . 'admin/' );
$termVersion = klytos_version();
?>

<!-- xterm.js CSS -->
<link rel="stylesheet" href="<?php echo klytos_esc_url( $basePath . 'admin/assets/vendor/xterm/xterm.min.css' ); ?>">

<style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
    .klytos-terminal-wrapper {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 120px);
        background: #1e1e2e;
        border-radius: 8px;
        overflow: hidden;
        position: relative;
    }

    .klytos-terminal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 16px;
        background: #181825;
        border-bottom: 1px solid #313244;
        color: #cdd6f4;
        font-family: 'SF Mono', 'Fira Code', 'Cascadia Code', monospace;
        font-size: 13px;
    }

    .klytos-terminal-header .title {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .klytos-terminal-header .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #a6e3a1;
    }

    .klytos-terminal-header .dot.inactive {
        background: #f38ba8;
    }

    .klytos-terminal-header .help-btn {
        background: #313244;
        border: none;
        color: #cdd6f4;
        padding: 4px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
    }

    .klytos-terminal-header .help-btn:hover {
        background: #45475a;
    }

    #klytos-terminal {
        flex: 1;
        padding: 8px;
    }

    /* 2FA revalidation modal */
    .klytos-2fa-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }

    .klytos-2fa-modal.active {
        display: flex;
    }

    .klytos-2fa-modal-content {
        background: #1e1e2e;
        border: 1px solid #313244;
        border-radius: 12px;
        padding: 32px;
        max-width: 400px;
        width: 90%;
        color: #cdd6f4;
    }

    .klytos-2fa-modal-content h3 {
        margin: 0 0 16px;
        font-size: 18px;
    }

    .klytos-2fa-modal-content input {
        width: 100%;
        padding: 10px 14px;
        background: #313244;
        border: 1px solid #45475a;
        border-radius: 6px;
        color: #cdd6f4;
        font-size: 18px;
        letter-spacing: 4px;
        text-align: center;
        margin-bottom: 16px;
        box-sizing: border-box;
    }

    .klytos-2fa-modal-content button {
        width: 100%;
        padding: 10px;
        background: #89b4fa;
        border: none;
        border-radius: 6px;
        color: #1e1e2e;
        font-weight: 600;
        cursor: pointer;
    }

    /* Command reference panel */
    .klytos-cmd-panel {
        display: none;
        position: absolute;
        top: 0;
        right: 0;
        width: 320px;
        height: 100%;
        background: #181825;
        border-left: 1px solid #313244;
        color: #cdd6f4;
        overflow-y: auto;
        padding: 16px;
        font-family: 'SF Mono', monospace;
        font-size: 12px;
        z-index: 100;
    }

    .klytos-cmd-panel.active {
        display: block;
    }

    .klytos-cmd-panel h4 {
        color: #89b4fa;
        margin: 16px 0 8px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .klytos-cmd-panel .cmd-item {
        padding: 4px 0;
        display: flex;
        gap: 12px;
    }

    .klytos-cmd-panel .cmd-name {
        color: #a6e3a1;
        min-width: 140px;
    }

    .klytos-cmd-panel .cmd-desc {
        color: #6c7086;
    }
</style>

<div class="klytos-terminal-wrapper">
    <div class="klytos-terminal-header">
        <div class="title">
            <span class="dot" id="terminal-status-dot"></span>
            <span>Klytos Terminal</span>
            <span style="color: #6c7086;">v<?php echo klytos_esc_html( $termVersion ); ?></span>
        </div>
        <div>
            <button class="help-btn" id="toggle-cmd-panel">Comandos</button>
        </div>
    </div>

    <div id="klytos-terminal"></div>

    <!-- Command reference side panel -->
    <div class="klytos-cmd-panel" id="cmd-panel">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <strong>Referencia rapida</strong>
            <button class="help-btn" id="close-cmd-panel" style="font-size:14px;">X</button>
        </div>
        <p style="color:#6c7086; margin:8px 0;">
            Escribe <span style="color:#a6e3a1;">help &lt;comando&gt;</span> para mas detalles.
        </p>
        <div id="cmd-panel-list">
            <!-- Populated dynamically via JS -->
        </div>
    </div>
</div>

<!-- 2FA revalidation modal -->
<div class="klytos-2fa-modal" id="revalidation-modal">
    <div class="klytos-2fa-modal-content">
        <h3>Sesion de terminal expirada</h3>
        <p style="color:#6c7086; margin-bottom:16px;">
            Han pasado mas de 10 minutos de inactividad.
            Introduce tu codigo 2FA para continuar.
        </p>
        <input type="text"
               id="revalidation-code"
               maxlength="6"
               placeholder="000000"
               autocomplete="one-time-code"
               inputmode="numeric"
               pattern="[0-9]*" />
        <button id="revalidation-submit">Verificar</button>
    </div>
</div>

<!-- xterm.js (bundled locally) -->
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"
        src="<?php echo klytos_esc_url( $basePath . 'admin/assets/vendor/xterm/xterm.min.js' ); ?>"></script>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"
        src="<?php echo klytos_esc_url( $basePath . 'admin/assets/vendor/xterm/addon-fit.min.js' ); ?>"></script>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function() {
    'use strict';

    var CSRF_TOKEN = '<?php echo klytos_esc_attr( $csrfToken ); ?>';
    var API_BASE = '<?php echo $apiBase; ?>';
    var PROMPT = '\x1b[32mklytos\x1b[0m \x1b[90m>\x1b[0m ';

    // State.
    var currentInput = '';
    var cursorPos = 0;
    var commandHistory = [];
    var historyIndex = -1;
    var isExecuting = false;
    var suggestions = [];
    var pendingCommand = null;

    // Initialize xterm.
    var term = new Terminal({
        cursorBlink: true,
        cursorStyle: 'bar',
        fontSize: 14,
        fontFamily: "'SF Mono', 'Fira Code', 'Cascadia Code', 'Consolas', monospace",
        theme: {
            background: '#1e1e2e',
            foreground: '#cdd6f4',
            cursor: '#f5e0dc',
            selectionBackground: '#45475a',
            black: '#45475a',
            red: '#f38ba8',
            green: '#a6e3a1',
            yellow: '#f9e2af',
            blue: '#89b4fa',
            magenta: '#cba6f7',
            cyan: '#94e2d5',
            white: '#bac2de',
            brightBlack: '#585b70',
            brightRed: '#f38ba8',
            brightGreen: '#a6e3a1',
            brightYellow: '#f9e2af',
            brightBlue: '#89b4fa',
            brightMagenta: '#cba6f7',
            brightCyan: '#94e2d5',
            brightWhite: '#a6adc8'
        },
        allowProposedApi: true
    });

    // Fit addon for auto-sizing.
    var fitAddon = new FitAddon.FitAddon();
    term.loadAddon(fitAddon);

    // Mount terminal.
    var container = document.getElementById('klytos-terminal');
    term.open(container);
    fitAddon.fit();

    // Resize with window.
    window.addEventListener('resize', function() { fitAddon.fit(); });

    // Welcome banner.
    term.writeln('\x1b[34m' +
        '  _  ___       _            ' + '\r\n' +
        ' | |/ / |_   _| |_ ___  ___ ' + '\r\n' +
        ' | \' /| | | | | __/ _ \\/ __|' + '\r\n' +
        ' | . \\| | |_| | || (_) \\__ \\' + '\r\n' +
        ' |_|\\_\\_|\\__, |\\__\\___/|___/' + '\r\n' +
        '         |___/              ' +
        '\x1b[0m'
    );
    term.writeln('');
    term.writeln('\x1b[90m Terminal integrado. Escribe \x1b[32mhelp\x1b[90m para ver los comandos disponibles.\x1b[0m');
    term.writeln('\x1b[90m Pulsa \x1b[33mTab\x1b[90m para autocompletar. Usa las flechas para navegar el historial.\x1b[0m');
    term.writeln('');
    writePrompt();

    // Load command list for autocomplete.
    loadCommandList();

    // --- Input handling ---

    term.onKey(function(e) {
        if (isExecuting) return;

        var key = e.key;
        var domEvent = e.domEvent;
        var code = domEvent.keyCode;
        var ctrlKey = domEvent.ctrlKey;

        // Ctrl+C: cancel current input.
        if (ctrlKey && code === 67) {
            term.write('^C\r\n');
            currentInput = '';
            cursorPos = 0;
            historyIndex = -1;
            writePrompt();
            return;
        }

        // Ctrl+L: clear screen.
        if (ctrlKey && code === 76) {
            term.clear();
            writePrompt();
            return;
        }

        // Enter: execute command.
        if (code === 13) {
            term.write('\r\n');
            var cmd = currentInput.trim();
            currentInput = '';
            cursorPos = 0;
            historyIndex = -1;

            if (cmd !== '') {
                // Add to history (avoid consecutive duplicates).
                if (commandHistory.length === 0 || commandHistory[commandHistory.length - 1] !== cmd) {
                    commandHistory.push(cmd);
                }
                executeCommand(cmd);
            } else {
                writePrompt();
            }
            return;
        }

        // Backspace.
        if (code === 8) {
            if (cursorPos > 0) {
                currentInput = currentInput.slice(0, cursorPos - 1) + currentInput.slice(cursorPos);
                cursorPos--;
                refreshLine();
            }
            return;
        }

        // Delete.
        if (code === 46) {
            if (cursorPos < currentInput.length) {
                currentInput = currentInput.slice(0, cursorPos) + currentInput.slice(cursorPos + 1);
                refreshLine();
            }
            return;
        }

        // Tab: autocomplete.
        if (code === 9) {
            domEvent.preventDefault();
            autocomplete();
            return;
        }

        // Arrow up: previous history.
        if (code === 38) {
            if (commandHistory.length > 0) {
                if (historyIndex === -1) {
                    historyIndex = commandHistory.length - 1;
                } else if (historyIndex > 0) {
                    historyIndex--;
                }
                currentInput = commandHistory[historyIndex];
                cursorPos = currentInput.length;
                refreshLine();
            }
            return;
        }

        // Arrow down: next history.
        if (code === 40) {
            if (historyIndex !== -1) {
                if (historyIndex < commandHistory.length - 1) {
                    historyIndex++;
                    currentInput = commandHistory[historyIndex];
                } else {
                    historyIndex = -1;
                    currentInput = '';
                }
                cursorPos = currentInput.length;
                refreshLine();
            }
            return;
        }

        // Arrow left.
        if (code === 37) {
            if (cursorPos > 0) {
                cursorPos--;
                term.write('\x1b[D');
            }
            return;
        }

        // Arrow right.
        if (code === 39) {
            if (cursorPos < currentInput.length) {
                cursorPos++;
                term.write('\x1b[C');
            }
            return;
        }

        // Home.
        if (code === 36) {
            while (cursorPos > 0) {
                cursorPos--;
                term.write('\x1b[D');
            }
            return;
        }

        // End.
        if (code === 35) {
            while (cursorPos < currentInput.length) {
                cursorPos++;
                term.write('\x1b[C');
            }
            return;
        }

        // Printable characters.
        if (key.length === 1 && !ctrlKey) {
            currentInput = currentInput.slice(0, cursorPos) + key + currentInput.slice(cursorPos);
            cursorPos++;
            refreshLine();
        }
    });

    // --- Functions ---

    function writePrompt() {
        term.write(PROMPT);
    }

    function refreshLine() {
        // Clear current line and rewrite.
        term.write('\r\x1b[K');
        term.write(PROMPT + currentInput);
        // Move cursor to correct position.
        var diff = currentInput.length - cursorPos;
        if (diff > 0) {
            term.write('\x1b[' + diff + 'D');
        }
    }

    function executeCommand(cmd) {
        // Handle clear locally.
        if (cmd.toLowerCase() === 'clear') {
            term.clear();
            writePrompt();
            return;
        }

        isExecuting = true;
        document.getElementById('terminal-status-dot').classList.add('inactive');

        fetch(API_BASE + 'api/terminal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ command: cmd })
        })
        .then(function(response) {
            if (!response.ok) {
                return response.json().catch(function() { return {}; }).then(function(errorData) {
                    term.writeln('\x1b[31mError: ' + (errorData.error || 'Error del servidor') + '\x1b[0m');
                });
            }
            return response.json().then(function(data) {
                // Requires 2FA revalidation.
                if (data.requires_2fa) {
                    pendingCommand = cmd;
                    showRevalidationModal();
                    return;
                }

                // Show output.
                if (data.output && data.output !== '__CLEAR__') {
                    var lines = data.output.split('\n');
                    lines.forEach(function(line) {
                        if (data.success) {
                            term.writeln(line);
                        } else {
                            term.writeln('\x1b[31m' + line + '\x1b[0m');
                        }
                    });
                }

                if (data.output === '__CLEAR__') {
                    term.clear();
                }
            });
        })
        .catch(function(err) {
            term.writeln('\x1b[31mError de conexion: ' + err.message + '\x1b[0m');
        })
        .finally(function() {
            isExecuting = false;
            document.getElementById('terminal-status-dot').classList.remove('inactive');
            writePrompt();
        });
    }

    function loadCommandList() {
        fetch(API_BASE + 'api/terminal-autocomplete.php?q=', {
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            suggestions = data.suggestions || [];
            populateCommandPanel();
        })
        .catch(function() {
            // Silently fail -- autocomplete just won't work.
        });
    }

    function autocomplete() {
        var input = currentInput.toLowerCase();
        if (!input) return;

        var matches = suggestions.filter(function(s) { return s.indexOf(input) === 0; });

        if (matches.length === 0) return;

        if (matches.length === 1) {
            // Autocomplete directly.
            currentInput = matches[0] + ' ';
            cursorPos = currentInput.length;
            refreshLine();
        } else {
            // Show options.
            term.write('\r\n');
            term.writeln(matches.map(function(m) { return '\x1b[32m' + m + '\x1b[0m'; }).join('   '));
            writePrompt();
            term.write(currentInput);
            // Autocomplete to common prefix.
            var common = commonPrefix(matches);
            if (common.length > input.length) {
                currentInput = common;
                cursorPos = currentInput.length;
                refreshLine();
            }
        }
    }

    function commonPrefix(strings) {
        if (strings.length === 0) return '';
        var prefix = strings[0];
        for (var i = 1; i < strings.length; i++) {
            while (strings[i].indexOf(prefix) !== 0) {
                prefix = prefix.slice(0, -1);
            }
        }
        return prefix;
    }

    function populateCommandPanel() {
        var panel = document.getElementById('cmd-panel-list');
        if (!panel) return;

        var categories = {};
        suggestions.forEach(function(cmd) {
            var cat = cmd.indexOf(':') !== -1 ? cmd.split(':')[0] : 'general';
            if (!categories[cat]) categories[cat] = [];
            categories[cat].push(cmd);
        });

        var labels = {
            general: 'General',
            build: 'Build',
            pages: 'Contenido',
            tasks: 'Contenido',
            cache: 'Sistema',
            cron: 'Sistema',
            plugins: 'Plugins'
        };

        var html = '';
        Object.keys(categories).forEach(function(cat) {
            var label = labels[cat] || cat.charAt(0).toUpperCase() + cat.slice(1);
            html += '<h4>' + label + '</h4>';
            categories[cat].forEach(function(cmd) {
                html += '<div class="cmd-item"><span class="cmd-name">' + cmd + '</span></div>';
            });
        });

        panel.innerHTML = html;
    }

    // --- 2FA Revalidation ---

    function showRevalidationModal() {
        var modal = document.getElementById('revalidation-modal');
        modal.classList.add('active');
        var codeInput = document.getElementById('revalidation-code');
        codeInput.value = '';
        codeInput.style.borderColor = '#45475a';
        codeInput.focus();
    }

    document.getElementById('revalidation-submit').addEventListener('click', function() {
        var codeValue = document.getElementById('revalidation-code').value.trim();
        if (!codeValue) return;

        fetch(API_BASE + 'api/terminal-revalidate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ code: codeValue, method: 'totp' })
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                document.getElementById('revalidation-modal').classList.remove('active');
                // Retry the pending command.
                if (pendingCommand) {
                    var cmd = pendingCommand;
                    pendingCommand = null;
                    executeCommand(cmd);
                }
            } else {
                document.getElementById('revalidation-code').value = '';
                document.getElementById('revalidation-code').style.borderColor = '#f38ba8';
                document.getElementById('revalidation-code').focus();
            }
        })
        .catch(function() {
            term.writeln('\x1b[31mError de conexion durante revalidacion.\x1b[0m');
            document.getElementById('revalidation-modal').classList.remove('active');
            isExecuting = false;
            writePrompt();
        });
    });

    // Enter in 2FA code field.
    document.getElementById('revalidation-code').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('revalidation-submit').click();
        }
    });

    // --- Command panel toggle ---

    document.getElementById('toggle-cmd-panel').addEventListener('click', function() {
        document.getElementById('cmd-panel').classList.toggle('active');
    });

    document.getElementById('close-cmd-panel').addEventListener('click', function() {
        document.getElementById('cmd-panel').classList.remove('active');
    });

})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
