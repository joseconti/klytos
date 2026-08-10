/**
 * Klytos Admin — editor-split chrome.
 *
 * Phase 4 Step 4, stage 6 of 6. The behaviour `template-editor-split.md`
 * specifies AROUND the canvas: the toolbar's three autosave readings (§2), the
 * inspector's disclosure sections (§4) and its sheet modes (§3).
 *
 * It deliberately knows nothing about blocks. The canvas interior is
 * Gutenberg's or TinyMCE's own DOM and is deferred product (`roadmap.md` §0c,
 * D-104); this file would be wrong to reach into it.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

( function () {
    'use strict';

    var strings = window.KLYTOS_EDITOR_STRINGS || {};

    var wrapper = document.getElementById( 'k-save-state' );
    var text = document.getElementById( 'k-editor-save-text' );
    var alertPanel = document.getElementById( 'k-editor-autosave-alert' );
    var alertMessage = document.getElementById( 'k-editor-autosave-message' );
    var live = document.getElementById( 'k-live-status' );

    /* Consecutive failures. §2 turns the state into a role="alert" only on the
       SECOND one: a single failed autosave is a retry, not an incident. */
    var failures = 0;

    /**
     * Announce once through the shell's polite region.
     *
     * §2: "Saved 14:03 … written once to the aria-live="polite" region. It
     * does not re-announce on every idle tick." The caller decides when; this
     * only writes.
     *
     * @param {string} message Text to announce.
     */
    function announce( message ) {
        if ( live ) {
            live.textContent = message;
        }
    }

    /**
     * Replace the save-state reading.
     *
     * @param {string}  reading The text to show.
     * @param {boolean} busy    True while a save is in flight.
     * @param {boolean} failed  True for the "Not saved — retrying" reading.
     */
    function setState( reading, busy, failed ) {
        if ( text ) {
            text.textContent = reading;
        }
        if ( wrapper ) {
            wrapper.setAttribute( 'aria-busy', busy ? 'true' : 'false' );
            wrapper.classList.toggle( 'k-save-state--failed', !! failed );
        }
    }

    /**
     * Format a time the way the server formats it, so the two readings of the
     * same fact cannot disagree: the resting state is rendered by PHP and this
     * one replaces it after an autosave.
     *
     * @return {string} HH:MM in the browser's own locale-independent form.
     */
    function nowHhMm() {
        var d = new Date();
        return String( d.getHours() ).padStart( 2, '0' ) + ':' + String( d.getMinutes() ).padStart( 2, '0' );
    }

    var api = {

        /** §2 Autosave — in flight. */
        saving: function () {
            setState( strings.saving || '', true, false );
        },

        /** §2 Autosave — saved. Announced once, not on every idle tick. */
        saved: function () {
            failures = 0;
            if ( alertPanel ) {
                alertPanel.hidden = true;
            }
            var reading = ( strings.savedAt || '' ).replace( '{time}', nowHhMm() );
            setState( reading, false, false );
            announce( reading );
        },

        /** Content changed and is not yet saved. */
        dirty: function () {
            setState( strings.unsaved || '', false, false );
        },

        /**
         * §2 Autosave — failed. The buffer is never discarded and the editor
         * never navigates away by itself; this only reports.
         *
         * @param {Error|object} err The failure the engine reported.
         */
        failed: function ( err ) {
            failures += 1;
            setState( strings.notSaved || '', false, true );

            if ( failures < 2 ) {
                return;
            }

            var status = ( err && err.message ) ? String( err.message ) : '';
            if ( alertMessage && alertPanel ) {
                alertMessage.textContent = ( alertPanel.getAttribute( 'data-message' ) || '' )
                    .replace( '{status}', status );
                alertPanel.hidden = false;
            }
        }
    };

    window.KlytosPageEditor = api;

    /* The engine calls save() itself on its own timer; this wraps it so the
       in-flight reading exists at all. Without it the state would jump from
       "Unsaved changes" straight to "Saved", and §2's first reading would be
       specified and never rendered. */
    if ( window.KlytosEditor && typeof window.KlytosEditor.save === 'function' ) {
        var originalSave = window.KlytosEditor.save;
        window.KlytosEditor.save = function () {
            api.saving();
            return originalSave.apply( window.KlytosEditor, arguments );
        };
    }

    // ─── §2 Autosave — failed: Retry now / Copy the content ───────────

    var retry = document.getElementById( 'k-editor-autosave-retry' );
    if ( retry ) {
        retry.addEventListener( 'click', function () {
            if ( window.KlytosEditor && typeof window.KlytosEditor.save === 'function' ) {
                window.KlytosEditor.save( { autosave: '1' } ).catch( function () {} );
            }
        } );
    }

    var copy = document.getElementById( 'k-editor-autosave-copy' );
    if ( copy ) {
        copy.addEventListener( 'click', function () {
            if ( ! window.KlytosEditor || ! navigator.clipboard ) {
                return;
            }
            navigator.clipboard.writeText( window.KlytosEditor.getContent() ).then( function () {
                announce( strings.copied || '' );
            } ).catch( function () {} );
        } );
    }

    // ─── §4 Inspector — disclosure sections ──────────────────────────

    var toggles = document.querySelectorAll( '.k-inspector-toggle' );
    Array.prototype.forEach.call( toggles, function ( toggle ) {
        toggle.addEventListener( 'click', function () {
            var panel = document.getElementById( toggle.getAttribute( 'aria-controls' ) );
            if ( ! panel ) {
                return;
            }
            var open = toggle.getAttribute( 'aria-expanded' ) === 'true';
            toggle.setAttribute( 'aria-expanded', open ? 'false' : 'true' );
            panel.hidden = open;
        } );
    } );

    // ─── §3 Inspector — sheet below 1200 ─────────────────────────────

    var inspector = document.getElementById( 'k-inspector' );
    var trigger = document.getElementById( 'k-inspector-trigger' );

    if ( inspector && trigger ) {
        var sheet = window.matchMedia( '(max-width: 1199px)' );
        var opener = null;

        /**
         * Close the sheet and return focus to whatever opened it (§3, "Esc
         * closes and returns focus").
         */
        function closeSheet() {
            inspector.hidden = true;
            trigger.setAttribute( 'aria-expanded', 'false' );
            if ( opener ) {
                opener.focus();
                opener = null;
            }
        }

        /**
         * Apply the mode the viewport is in. Above 1199 the inspector is a
         * column and is never hidden, so the attribute is removed rather than
         * left behind — a `hidden` node the CSS un-hides is a lie to the
         * accessibility tree, and the resize is exactly where that happens.
         *
         * @param {MediaQueryList|MediaQueryListEvent} mq The query state.
         */
        function applyMode( mq ) {
            if ( mq.matches ) {
                inspector.setAttribute( 'role', 'dialog' );
                inspector.setAttribute( 'aria-modal', 'false' );
                trigger.hidden = false;
                if ( trigger.getAttribute( 'aria-expanded' ) !== 'true' ) {
                    inspector.hidden = true;
                }
            } else {
                inspector.removeAttribute( 'role' );
                inspector.removeAttribute( 'aria-modal' );
                inspector.hidden = false;
                trigger.hidden = true;
                trigger.setAttribute( 'aria-expanded', 'false' );
            }
        }

        trigger.addEventListener( 'click', function () {
            if ( trigger.getAttribute( 'aria-expanded' ) === 'true' ) {
                closeSheet();
                return;
            }
            opener = trigger;
            inspector.hidden = false;
            trigger.setAttribute( 'aria-expanded', 'true' );
            /* §3: "focus moved in on open". The region itself takes focus, not
               its first control: a sheet that lands the caret in a text field
               makes the first keystroke edit something. */
            inspector.setAttribute( 'tabindex', '-1' );
            inspector.focus();
        } );

        document.addEventListener( 'keydown', function ( event ) {
            if ( event.key === 'Escape' && trigger.getAttribute( 'aria-expanded' ) === 'true' ) {
                closeSheet();
            }
        } );

        applyMode( sheet );
        if ( typeof sheet.addEventListener === 'function' ) {
            sheet.addEventListener( 'change', applyMode );
        }
    }

} )();
