/**
 * Klytos Password Generator
 * Secure password generation, copy-to-clipboard, show/hide toggle, and strength meter.
 *
 * Usage: Add data-klytos-pwgen to any <input type="password"> element.
 * Optional attributes:
 *   data-klytos-pwgen-confirm="#selector"  — also fills confirmation field
 *   data-klytos-pwgen-style="ai-panel"     — uses ai-panel-btn classes
 *
 * @package Klytos
 * @since   0.10.0
 *
 * @license    Elastic License 2.0 (ELv2)
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

var KlytosPassword = ( function() {
    'use strict';

    var UPPER   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    var LOWER   = 'abcdefghijklmnopqrstuvwxyz';
    var DIGITS  = '0123456789';
    var SPECIAL = '!@#$%^&*()-_=+[]{}|;:,.<>?';
    var ALL     = UPPER + LOWER + DIGITS + SPECIAL;

    var DEFAULT_LENGTH = 20;
    var STYLES_INJECTED = false;

    /* ── Crypto-safe random integer in [0, max) ── */
    function randomInt( max ) {
        var arr = new Uint32Array( 1 );
        crypto.getRandomValues( arr );
        return arr[0] % max;
    }

    /* ── Pick one random char from a string ── */
    function randomFrom( pool ) {
        return pool.charAt( randomInt( pool.length ) );
    }

    /* ── Fisher-Yates shuffle (crypto) ── */
    function shuffle( arr ) {
        for ( var i = arr.length - 1; i > 0; i-- ) {
            var j = randomInt( i + 1 );
            var tmp = arr[i];
            arr[i] = arr[j];
            arr[j] = tmp;
        }
        return arr;
    }

    /* ── Generate a secure password ── */
    function generate( length ) {
        length = length || DEFAULT_LENGTH;
        if ( length < 12 ) length = 12;

        var chars = [
            randomFrom( UPPER ),
            randomFrom( LOWER ),
            randomFrom( DIGITS ),
            randomFrom( SPECIAL )
        ];

        for ( var i = 0; i < length - 4; i++ ) {
            chars.push( randomFrom( ALL ) );
        }

        return shuffle( chars ).join( '' );
    }

    /* ── Copy to clipboard ── */
    function copyToClipboard( text, callback ) {
        if ( navigator.clipboard && navigator.clipboard.writeText ) {
            navigator.clipboard.writeText( text ).then( function() {
                if ( callback ) callback( true );
            }).catch( function() {
                fallbackCopy( text, callback );
            });
        } else {
            fallbackCopy( text, callback );
        }
    }

    function fallbackCopy( text, callback ) {
        var ta = document.createElement( 'textarea' );
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild( ta );
        ta.select();
        try {
            document.execCommand( 'copy' );
            if ( callback ) callback( true );
        } catch ( e ) {
            if ( callback ) callback( false );
        }
        document.body.removeChild( ta );
    }

    /* ── Strength calculation ── */
    function getStrength( password ) {
        if ( !password ) return { level: 0, label: '', cls: '' };

        var len = password.length;
        var classes = 0;
        if ( /[A-Z]/.test( password ) ) classes++;
        if ( /[a-z]/.test( password ) ) classes++;
        if ( /[0-9]/.test( password ) ) classes++;
        if ( /[^A-Za-z0-9]/.test( password ) ) classes++;

        if ( len < 8 || classes <= 1 ) {
            return { level: 1, label: 'Weak', cls: 'klytos-pw-weak' };
        }
        if ( len < 12 || classes <= 2 ) {
            return { level: 2, label: 'Fair', cls: 'klytos-pw-fair' };
        }
        if ( len < 16 || classes <= 3 ) {
            return { level: 3, label: 'Strong', cls: 'klytos-pw-strong' };
        }
        return { level: 4, label: 'Very Strong', cls: 'klytos-pw-very-strong' };
    }

    /* ── Detect Font Awesome availability ── */
    function hasFontAwesome() {
        return !! document.querySelector(
            'link[href*="fontawesome"], link[href*="font-awesome"], link[href*="all.min.css"]'
        );
    }

    /* ── Create a button element ── */
    function createBtn( opts ) {
        var btn = document.createElement( 'button' );
        btn.type = 'button';
        btn.className = opts.className;
        btn.title = opts.title || '';
        if ( opts.icon && hasFontAwesome() ) {
            btn.innerHTML = '<i class="' + opts.icon + '"></i>';
        } else {
            btn.textContent = opts.text || '';
        }
        return btn;
    }

    /* ── Inject styles once ── */
    function injectStyles() {
        if ( STYLES_INJECTED ) return;
        STYLES_INJECTED = true;

        var css = [
            '.klytos-pw-toolbar { display: flex; gap: 0.35rem; margin-top: 0.35rem; align-items: center; flex-wrap: wrap; }',
            '.klytos-pw-toolbar button { flex-shrink: 0; }',
            '.klytos-pw-strength { height: 4px; border-radius: 2px; margin-top: 0.35rem; background: #e2e8f0; overflow: hidden; }',
            '.klytos-pw-strength-bar { height: 100%; width: 0; border-radius: 2px; transition: width 0.3s, background 0.3s; }',
            '.klytos-pw-weak .klytos-pw-strength-bar   { width: 25%;  background: #ef4444; }',
            '.klytos-pw-fair .klytos-pw-strength-bar   { width: 50%;  background: #f59e0b; }',
            '.klytos-pw-strong .klytos-pw-strength-bar  { width: 75%;  background: #2563eb; }',
            '.klytos-pw-very-strong .klytos-pw-strength-bar { width: 100%; background: #22c55e; }',
            '.klytos-pw-strength-label { font-size: 0.75rem; margin-top: 0.15rem; color: #64748b; }',
            '.klytos-pw-weak + .klytos-pw-strength-label   { color: #ef4444; }',
            '.klytos-pw-fair + .klytos-pw-strength-label   { color: #f59e0b; }',
            '.klytos-pw-strong + .klytos-pw-strength-label  { color: #2563eb; }',
            '.klytos-pw-very-strong + .klytos-pw-strength-label { color: #22c55e; }'
        ].join( '\n' );

        var style = document.createElement( 'style' );
        style.textContent = css;
        document.head.appendChild( style );
    }

    /* ── Update strength meter ── */
    function updateStrength( input, meterEl, labelEl ) {
        var s = getStrength( input.value );
        meterEl.className = 'klytos-pw-strength';
        if ( s.cls ) meterEl.classList.add( s.cls );
        labelEl.textContent = s.label;
        labelEl.className = 'klytos-pw-strength-label';
    }

    /* ── Initialize one password field ── */
    function initField( input ) {
        var isAiPanel = input.getAttribute( 'data-klytos-pwgen-style' ) === 'ai-panel';
        var confirmSel = input.getAttribute( 'data-klytos-pwgen-confirm' );
        var btnClass = isAiPanel ? 'ai-panel-btn ai-panel-btn-outline' : 'btn btn-sm btn-outline';

        // Toolbar container.
        var toolbar = document.createElement( 'div' );
        toolbar.className = 'klytos-pw-toolbar';

        // Generate button.
        var btnGenerate = createBtn({
            className: btnClass,
            icon: 'fa-solid fa-key',
            text: 'Generate',
            title: 'Generate secure password'
        });

        // Copy button (hidden until password exists).
        var btnCopy = createBtn({
            className: btnClass,
            icon: 'fa-solid fa-copy',
            text: 'Copy',
            title: 'Copy password'
        });
        btnCopy.style.display = 'none';

        // Show/Hide toggle.
        var btnToggle = createBtn({
            className: btnClass,
            icon: 'fa-solid fa-eye',
            text: 'Show',
            title: 'Show password'
        });

        toolbar.appendChild( btnGenerate );
        toolbar.appendChild( btnCopy );
        toolbar.appendChild( btnToggle );

        // Strength meter.
        var meter = document.createElement( 'div' );
        meter.className = 'klytos-pw-strength';
        meter.innerHTML = '<div class="klytos-pw-strength-bar"></div>';

        var meterLabel = document.createElement( 'div' );
        meterLabel.className = 'klytos-pw-strength-label';

        // Insert after the input.
        var parent = input.parentNode;
        var next = input.nextSibling;
        if ( next ) {
            parent.insertBefore( toolbar, next );
            parent.insertBefore( meter, toolbar.nextSibling );
            parent.insertBefore( meterLabel, meter.nextSibling );
        } else {
            parent.appendChild( toolbar );
            parent.appendChild( meter );
            parent.appendChild( meterLabel );
        }

        // ── Event: Generate ──
        btnGenerate.addEventListener( 'click', function() {
            var pw = generate( DEFAULT_LENGTH );
            input.value = pw;
            input.type = 'text';
            updateToggleBtn( btnToggle, true );
            btnCopy.style.display = '';

            // Fill confirmation field if specified.
            if ( confirmSel ) {
                var confirmInput = document.querySelector( confirmSel );
                if ( confirmInput ) {
                    confirmInput.value = pw;
                    confirmInput.type = 'text';
                }
            }

            updateStrength( input, meter, meterLabel );
            input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
        });

        // ── Event: Copy ──
        btnCopy.addEventListener( 'click', function() {
            copyToClipboard( input.value, function( ok ) {
                if ( !ok ) return;
                var origIcon = hasFontAwesome() ? '<i class="fa-solid fa-copy"></i>' : 'Copy';
                if ( hasFontAwesome() ) {
                    btnCopy.innerHTML = '<i class="fa-solid fa-check"></i>';
                } else {
                    btnCopy.textContent = 'Copied!';
                }
                setTimeout( function() {
                    if ( hasFontAwesome() ) {
                        btnCopy.innerHTML = origIcon;
                    } else {
                        btnCopy.textContent = 'Copy';
                    }
                }, 1500 );
            });
        });

        // ── Event: Show/Hide ──
        btnToggle.addEventListener( 'click', function() {
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            updateToggleBtn( btnToggle, !showing );

            // Also toggle confirmation field.
            if ( confirmSel ) {
                var confirmInput = document.querySelector( confirmSel );
                if ( confirmInput ) {
                    confirmInput.type = input.type;
                }
            }
        });

        // ── Event: Input (strength meter) ──
        input.addEventListener( 'input', function() {
            updateStrength( input, meter, meterLabel );
            btnCopy.style.display = input.value ? '' : 'none';
        });

        // Initial state.
        updateStrength( input, meter, meterLabel );
        if ( input.value ) btnCopy.style.display = '';
    }

    /* ── Update toggle button icon/text ── */
    function updateToggleBtn( btn, isVisible ) {
        if ( hasFontAwesome() ) {
            btn.innerHTML = isVisible
                ? '<i class="fa-solid fa-eye-slash"></i>'
                : '<i class="fa-solid fa-eye"></i>';
        } else {
            btn.textContent = isVisible ? 'Hide' : 'Show';
        }
        btn.title = isVisible ? 'Hide password' : 'Show password';
    }

    /* ── Init all password fields on the page ── */
    function init() {
        var inputs = document.querySelectorAll( '[data-klytos-pwgen]' );
        if ( !inputs.length ) return;

        injectStyles();

        for ( var i = 0; i < inputs.length; i++ ) {
            initField( inputs[i] );
        }
    }

    // Auto-initialize.
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

    // Public API.
    return {
        generate: generate,
        init: init,
        copyToClipboard: copyToClipboard
    };

})();
