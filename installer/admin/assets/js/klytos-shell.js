/*
 * Klytos Admin — the shell's behaviour.
 *
 * Phase 4 Step 4, stage 2 of 6. Implements the interactive half of
 * `SPEC/screens/template-shell.md`: the command palette (⌘K), the off-canvas
 * drawer below 900px, the 56px rail's "Expand navigation", and the status bar's
 * offline state.
 *
 * Loaded with the page's CSP nonce. No inline handlers anywhere — every
 * listener is attached here, which is the project's standing rule and what the
 * Content-Security-Policy actually enforces.
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

( function () {
    'use strict';

    var shell = document.getElementById( 'k-shell' );
    var data  = window.__KLYTOS_SHELL__ || { items: [], noResults: '' };

    if ( ! shell ) {
        return;
    }

    /* ==========================================================
       Focus trapping — shared by the palette and the drawer.
       "Nothing else on the page is reachable while an overlay is
       open (inert on the shell, with aria-hidden as the
       fallback)" — accessibility.md §3.2.
       ========================================================== */

    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function trapFocus( container, event ) {
        var nodes = Array.prototype.filter.call(
            container.querySelectorAll( FOCUSABLE ),
            function ( node ) { return node.offsetParent !== null || node === document.activeElement; }
        );
        if ( ! nodes.length ) {
            return;
        }
        var first = nodes[ 0 ];
        var last  = nodes[ nodes.length - 1 ];
        if ( event.shiftKey && document.activeElement === first ) {
            event.preventDefault();
            last.focus();
        } else if ( ! event.shiftKey && document.activeElement === last ) {
            event.preventDefault();
            first.focus();
        }
    }

    /* ==========================================================
       Command palette — ⌘K / Ctrl+K, the ONLY global shortcut.
       There are no bare-letter shortcuts anywhere, so 2.1.4 is
       satisfied by construction (accessibility.md §3.3).
       ========================================================== */

    var palette      = document.getElementById( 'k-palette' );
    var paletteInput = document.getElementById( 'k-palette-input' );
    var paletteList  = document.getElementById( 'k-palette-list' );
    var paletteEmpty = document.getElementById( 'k-palette-empty' );
    var liveStatus   = document.getElementById( 'k-live-status' );
    var searchInput  = document.getElementById( 'k-search-input' );
    var paletteOpener = null;
    var activeIndex   = -1;
    var matches       = [];

    /*
     * Set while closePalette() is handing focus back to whatever opened it.
     *
     * Without it the palette is a KEYBOARD TRAP, which is a WCAG 2.1.2 failure
     * and not a cosmetic one: the search field's own focus listener opens the
     * palette, and closePalette() returns focus to its opener — so closing a
     * palette that the search field opened re-focused the search field, which
     * re-opened the palette, forever. Escape did nothing, and there was no way
     * out with the keyboard alone. Found by driving it (2026-07-29); the
     * server-rendered half of the shell had been fully verified and this sat
     * underneath it, because reading the two listeners separately never puts
     * them in the same sentence.
     */
    var restoringFocus = false;

    function renderPalette( query ) {
        var q = ( query || '' ).trim().toLowerCase();
        matches = ( data.items || [] ).filter( function ( item ) {
            return ! q
                || item.label.toLowerCase().indexOf( q ) !== -1
                || item.group.toLowerCase().indexOf( q ) !== -1;
        } );

        paletteList.textContent = '';
        activeIndex = matches.length ? 0 : -1;

        matches.forEach( function ( item, index ) {
            var option = document.createElement( 'li' );
            option.className = 'k-palette-option';
            option.id = 'k-palette-option-' + index;
            option.setAttribute( 'role', 'option' );
            option.setAttribute( 'aria-selected', index === 0 ? 'true' : 'false' );
            option.dataset.url = item.url;

            var label = document.createElement( 'span' );
            label.textContent = item.label;
            var group = document.createElement( 'span' );
            group.className = 'k-palette-option-group';
            group.textContent = item.group;

            option.appendChild( label );
            option.appendChild( group );
            paletteList.appendChild( option );
        } );

        if ( matches.length ) {
            paletteEmpty.hidden = true;
            paletteInput.setAttribute( 'aria-activedescendant', 'k-palette-option-0' );
        } else {
            // "No command matches foo. Try a page title, a setting name, or help."
            paletteEmpty.hidden = false;
            paletteEmpty.textContent = ( data.noResults || '' ).replace( '{query}', query || '' );
            paletteInput.removeAttribute( 'aria-activedescendant' );
        }

        // The result count is announced politely (accessibility.md §5.11).
        if ( liveStatus ) {
            liveStatus.textContent = matches.length + '';
        }
    }

    function setActiveOption( index ) {
        var options = paletteList.children;
        if ( ! options.length ) {
            return;
        }
        activeIndex = ( index + options.length ) % options.length;
        for ( var i = 0; i < options.length; i++ ) {
            options[ i ].setAttribute( 'aria-selected', i === activeIndex ? 'true' : 'false' );
        }
        // Focus itself never leaves the input.
        paletteInput.setAttribute( 'aria-activedescendant', options[ activeIndex ].id );
        options[ activeIndex ].scrollIntoView( { block: 'nearest' } );
    }

    function openPalette( opener ) {
        if ( ! palette || ! palette.hidden ) {
            return;
        }
        paletteOpener = opener || document.activeElement;
        palette.hidden = false;
        renderPalette( '' );
        paletteInput.value = '';
        paletteInput.focus();
    }

    function closePalette() {
        if ( ! palette || palette.hidden ) {
            return;
        }
        palette.hidden = true;
        if ( liveStatus ) {
            liveStatus.textContent = '';
        }
        // Focus returns to whatever opened it. `restoringFocus` is raised across
        // the call because `focus()` dispatches the focus event synchronously,
        // and the opener is very often the search field, whose listener would
        // otherwise re-open what we are closing.
        var opener = paletteOpener;
        paletteOpener = null;
        if ( opener && typeof opener.focus === 'function' ) {
            restoringFocus = true;
            opener.focus();
            restoringFocus = false;
        }
    }

    if ( palette && paletteInput && paletteList ) {
        document.addEventListener( 'keydown', function ( event ) {
            if ( ( event.metaKey || event.ctrlKey ) && event.key.toLowerCase() === 'k' ) {
                event.preventDefault();
                if ( palette.hidden ) {
                    openPalette( document.activeElement );
                } else {
                    closePalette();
                }
            }
        } );

        // Focusing the sidebar's search field opens the palette
        // (template-shell.md §1).
        if ( searchInput ) {
            searchInput.addEventListener( 'focus', function () {
                if ( restoringFocus ) {
                    return;
                }
                if ( palette.hidden ) {
                    openPalette( searchInput );
                }
            } );
        }

        paletteInput.addEventListener( 'input', function () {
            renderPalette( paletteInput.value );
        } );

        paletteInput.addEventListener( 'keydown', function ( event ) {
            if ( event.key === 'ArrowDown' ) {
                event.preventDefault();
                setActiveOption( activeIndex + 1 );
            } else if ( event.key === 'ArrowUp' ) {
                event.preventDefault();
                setActiveOption( activeIndex - 1 );
            } else if ( event.key === 'Enter' ) {
                var option = paletteList.children[ activeIndex ];
                if ( option ) {
                    event.preventDefault();
                    window.location.href = option.dataset.url;
                }
            } else if ( event.key === 'Escape' ) {
                event.preventDefault();
                closePalette();
            } else if ( event.key === 'Tab' ) {
                trapFocus( palette, event );
            }
        } );

        paletteList.addEventListener( 'click', function ( event ) {
            var option = event.target.closest( '.k-palette-option' );
            if ( option && option.dataset.url ) {
                window.location.href = option.dataset.url;
            }
        } );

        // A click on the scrim behind the window closes it, like Esc.
        palette.addEventListener( 'mousedown', function ( event ) {
            if ( event.target === palette ) {
                closePalette();
            }
        } );
    }

    /* ==========================================================
       Off-canvas drawer, below 900px.
       ========================================================== */

    var drawerTrigger = document.getElementById( 'k-drawer-trigger' );
    var drawerScrim   = document.getElementById( 'k-drawer-scrim' );
    var sidebar       = document.getElementById( 'k-sidebar' );

    function closeDrawer() {
        shell.classList.remove( 'k-shell--drawer-open' );
        if ( drawerScrim ) {
            drawerScrim.hidden = true;
        }
        if ( sidebar ) {
            sidebar.removeAttribute( 'role' );
            sidebar.removeAttribute( 'aria-modal' );
        }
        if ( drawerTrigger ) {
            drawerTrigger.setAttribute( 'aria-expanded', 'false' );
            drawerTrigger.focus();
        }
    }

    function openDrawer() {
        shell.classList.add( 'k-shell--drawer-open' );
        if ( drawerScrim ) {
            drawerScrim.hidden = false;
        }
        if ( sidebar ) {
            // The drawer is a modal dialog only while it IS a drawer; at the
            // wider breakpoints the same element is plain furniture, so the
            // role is added and removed rather than baked into the markup.
            sidebar.setAttribute( 'role', 'dialog' );
            sidebar.setAttribute( 'aria-modal', 'true' );
            var first = sidebar.querySelector( FOCUSABLE );
            if ( first ) {
                first.focus();
            }
        }
        if ( drawerTrigger ) {
            drawerTrigger.setAttribute( 'aria-expanded', 'true' );
        }
    }

    if ( drawerTrigger ) {
        drawerTrigger.addEventListener( 'click', function () {
            if ( shell.classList.contains( 'k-shell--drawer-open' ) ) {
                closeDrawer();
            } else {
                openDrawer();
            }
        } );
    }

    if ( drawerScrim ) {
        drawerScrim.addEventListener( 'click', closeDrawer );
    }

    document.addEventListener( 'keydown', function ( event ) {
        if ( event.key !== 'Escape' || ! shell.classList.contains( 'k-shell--drawer-open' ) ) {
            return;
        }
        closeDrawer();
    } );

    if ( sidebar ) {
        sidebar.addEventListener( 'keydown', function ( event ) {
            if ( event.key === 'Tab' && shell.classList.contains( 'k-shell--drawer-open' ) ) {
                trapFocus( sidebar, event );
            }
        } );
    }

    /* ==========================================================
       "Expand navigation" — the 56px rail, 900–1199px.
       It restores 232px and remembers the choice
       (template-shell.md §2). There is no collapse button:
       collapsing is the breakpoint's job.
       ========================================================== */

    var railExpand = document.getElementById( 'k-rail-expand' );
    var RAIL_KEY   = 'klytos_nav_expanded';

    if ( window.localStorage && window.localStorage.getItem( RAIL_KEY ) === '1' ) {
        shell.classList.add( 'k-shell--nav-expanded' );
    }

    if ( railExpand ) {
        railExpand.addEventListener( 'click', function () {
            shell.classList.add( 'k-shell--nav-expanded' );
            if ( window.localStorage ) {
                window.localStorage.setItem( RAIL_KEY, '1' );
            }
        } );
    }

    /* ==========================================================
       Status bar — offline.
       "The rest of the shell is unchanged; the admin does not
       throw up a full-screen offline state" (template-shell.md §1).
       ========================================================== */

    var statusRight = document.getElementById( 'k-statusbar-right' );

    function paintConnection() {
        if ( ! statusRight ) {
            return;
        }
        if ( navigator.onLine === false ) {
            statusRight.textContent = statusRight.dataset.offlineText || '';
            statusRight.classList.add( 'k-statusbar-offline' );
        } else {
            statusRight.textContent = statusRight.dataset.onlineText || '';
            statusRight.classList.remove( 'k-statusbar-offline' );
        }
    }

    window.addEventListener( 'online', paintConnection );
    window.addEventListener( 'offline', paintConnection );
    paintConnection();
}() );
