/**
 * Klytos DevBar — Debug Bar JavaScript
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 */

( function () {
    'use strict';

    const STORAGE_KEY = 'klytos_devbar_state';

    class KlytosDevBar {

        constructor( raw ) {
            // Flatten the nested toArray() structure into a flat object for rendering.
            this.data = this._flatten( raw );
            this.expanded = this._loadState();
            this.activeTab = 'performance';
            this.el = null;
            this.panel = null;

            this._render();
            this._bindEvents();

            if ( this.expanded ) {
                this._expand( false );
            }
        }

        /* ---------------------------------------------------------- */
        /*  Rendering                                                  */
        /* ---------------------------------------------------------- */

        _render() {
            // Root element
            this.el = document.createElement( 'div' );
            this.el.className = 'devbar';
            this.el.id = 'klytos-devbar';

            // Compact bar items
            const items = document.createElement( 'div' );
            items.className = 'devbar-items';
            items.innerHTML = this._renderBarItems();
            this.el.appendChild( items );

            // Toggle button
            const toggle = document.createElement( 'button' );
            toggle.className = 'devbar-toggle';
            toggle.setAttribute( 'aria-label', 'Toggle DevBar panel' );
            toggle.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>';
            this.el.appendChild( toggle );

            // Expanded panel
            this.panel = document.createElement( 'div' );
            this.panel.className = 'devbar-panel';
            this.panel.innerHTML = this._renderPanel();
            this.el.appendChild( this.panel );

            document.body.appendChild( this.el );
            document.body.classList.add( 'has-devbar' );
        }

        _renderBarItems() {
            const d = this.data;
            const timeClass = this._timeClass( d.request_time_ms );
            const memoryClass = this._memoryClass( d.memory_usage );

            return `
                <div class="devbar-item">
                    <span class="devbar-label">PHP</span>
                    <span class="devbar-value">${ this._esc( d.php_version ) }</span>
                </div>
                <div class="devbar-item">
                    <span class="devbar-label">Time</span>
                    <span class="devbar-value ${ timeClass }">${ this._formatMs( d.request_time_ms ) }</span>
                </div>
                <div class="devbar-item">
                    <span class="devbar-label">Memory</span>
                    <span class="devbar-value ${ memoryClass }">${ this._formatBytes( d.memory_usage ) }</span>
                </div>
                <div class="devbar-item">
                    <span class="devbar-label">Queries</span>
                    <span class="devbar-value">${ d.queries_count ?? 0 } <span style="color:#94a3b8">in</span> ${ this._formatMs( d.queries_time_ms ) }</span>
                </div>
                <div class="devbar-item">
                    <span class="devbar-label">Hooks</span>
                    <span class="devbar-value">${ d.hooks_count ?? 0 }</span>
                </div>
                <div class="devbar-item">
                    <span class="devbar-label">Page</span>
                    <span class="devbar-value">${ this._esc( d.current_page ?? '/' ) }</span>
                </div>
            `;
        }

        _renderPanel() {
            const tabs = [ 'performance', 'storage', 'hooks', 'logs' ];
            const tabLabels = { performance: 'Performance', storage: 'Storage', hooks: 'Hooks', logs: 'Logs' };

            let tabsHtml = '<div class="devbar-tabs">';
            tabs.forEach( ( tab ) => {
                const active = tab === this.activeTab ? ' is-active' : '';
                tabsHtml += `<button class="devbar-tab${ active }" data-tab="${ tab }">${ tabLabels[ tab ] }</button>`;
            } );
            tabsHtml += '</div>';

            let contentHtml = '<div class="devbar-tab-content">';
            tabs.forEach( ( tab ) => {
                const active = tab === this.activeTab ? ' is-active' : '';
                contentHtml += `<div class="devbar-tab-pane${ active }" data-pane="${ tab }">${ this._renderTabContent( tab ) }</div>`;
            } );
            contentHtml += '</div>';

            return tabsHtml + contentHtml;
        }

        _renderTabContent( tab ) {
            switch ( tab ) {
                case 'performance':
                    return this._renderPerformance();
                case 'storage':
                    return this._renderStorage();
                case 'hooks':
                    return this._renderHooks();
                case 'logs':
                    return this._renderLogs();
                default:
                    return '';
            }
        }

        _renderPerformance() {
            const d = this.data;
            const rows = [
                [ 'PHP Version', d.php_version ],
                [ 'Klytos Version', d.klytos_version ?? '-' ],
                [ 'Request Time', this._formatMs( d.request_time_ms ) ],
                [ 'Memory Usage', this._formatBytes( d.memory_usage ) ],
                [ 'Peak Memory', this._formatBytes( d.memory_peak ) ],
                [ 'Memory Limit', d.memory_limit ?? '-' ],
                [ 'DB Queries', d.queries_count ?? 0 ],
                [ 'DB Query Time', this._formatMs( d.queries_time_ms ) ],
                [ 'Hooks Fired', d.hooks_count ?? 0 ],
                [ 'Included Files', d.included_files ?? '-' ],
            ];

            let html = '<table class="devbar-table"><thead><tr><th>Metric</th><th>Value</th></tr></thead><tbody>';
            rows.forEach( ( [ label, value ] ) => {
                html += `<tr><td>${ this._esc( label ) }</td><td class="col-number">${ this._esc( String( value ) ) }</td></tr>`;
            } );
            html += '</tbody></table>';

            // Slow queries
            if ( d.slow_queries && d.slow_queries.length > 0 ) {
                html += '<div class="devbar-section-title">Slow Queries</div>';
                html += '<table class="devbar-table"><thead><tr><th>Query</th><th>Time</th></tr></thead><tbody>';
                d.slow_queries.forEach( ( q ) => {
                    html += `<tr><td>${ this._esc( q.sql ?? q.query ?? '' ) }</td><td class="col-number">${ this._formatMs( q.time_ms ?? q.time ?? 0 ) }</td></tr>`;
                } );
                html += '</tbody></table>';
            }

            return html;
        }

        _renderStorage() {
            const d = this.data;
            const ops = d.storage?.operations ?? [];

            if ( ops.length === 0 ) {
                return '<div class="devbar-empty">No storage operations recorded.</div>';
            }

            let html = `<div class="devbar-section-title">${ d.queries_count } operations in ${ this._formatMs( d.queries_time_ms ) }</div>`;
            html += '<table class="devbar-table"><thead><tr><th>#</th><th>Type</th><th>Collection</th><th>Time</th><th>Caller</th></tr></thead><tbody>';
            ops.forEach( ( op, i ) => {
                const slow = ( op.duration_ms ?? 0 ) > 50 ? ' style="color:#ef4444"' : '';
                html += `<tr>
                    <td class="col-number">${ i + 1 }</td>
                    <td>${ this._esc( op.type ?? '' ) }</td>
                    <td>${ this._esc( op.collection ?? '' ) }</td>
                    <td class="col-number"${ slow }>${ this._formatMs( op.duration_ms ) }</td>
                    <td style="font-size:0.75rem;color:#94a3b8">${ this._esc( op.caller ?? '' ) }</td>
                </tr>`;
            } );
            html += '</tbody></table>';

            return html;
        }

        _renderHooks() {
            const d = this.data;

            const hooks = Array.isArray( d.hooks ) ? d.hooks : ( d.hooks?.fired ?? [] );

            if ( hooks.length === 0 ) {
                return '<div class="devbar-empty">No hooks data available.</div>';
            }

            let html = '<table class="devbar-table"><thead><tr><th>#</th><th>Hook</th><th>Type</th><th>Callbacks</th><th>Time</th></tr></thead><tbody>';
            hooks.forEach( ( hook, i ) => {
                html += `<tr>
                    <td class="col-number">${ i + 1 }</td>
                    <td>${ this._esc( hook.name ?? '' ) }</td>
                    <td>${ this._esc( hook.type ?? '' ) }</td>
                    <td class="col-number">${ hook.callbacks ?? hook.listeners ?? '-' }</td>
                    <td class="col-number">${ hook.duration_ms !== undefined ? this._formatMs( hook.duration_ms ) : '-' }</td>
                </tr>`;
            } );
            html += '</tbody></table>';

            return html;
        }

        _renderLogs() {
            const d = this.data;

            if ( !d.logs || d.logs.length === 0 ) {
                return '<div class="devbar-empty">No log entries.</div>';
            }

            let html = '<table class="devbar-table"><thead><tr><th>Level</th><th>Message</th><th>Time</th></tr></thead><tbody>';
            d.logs.forEach( ( entry ) => {
                const level = ( entry.level ?? 'info' ).toLowerCase();
                const badgeClass = this._logBadgeClass( level );
                html += `<tr>
                    <td><span class="devbar-badge ${ badgeClass }">${ this._esc( level ) }</span></td>
                    <td>${ this._esc( entry.message ?? '' ) }</td>
                    <td class="col-number">${ this._esc( entry.time ?? '' ) }</td>
                </tr>`;
            } );
            html += '</tbody></table>';

            return html;
        }

        /* ---------------------------------------------------------- */
        /*  Events                                                     */
        /* ---------------------------------------------------------- */

        _bindEvents() {
            // Toggle expand / collapse
            this.el.querySelector( '.devbar-toggle' ).addEventListener( 'click', () => {
                this.expanded ? this._collapse() : this._expand();
            } );

            // Tab switching
            this.el.querySelectorAll( '.devbar-tab' ).forEach( ( btn ) => {
                btn.addEventListener( 'click', () => {
                    this._switchTab( btn.dataset.tab );
                } );
            } );
        }

        _expand( animate = true ) {
            this.expanded = true;
            this.el.classList.add( 'is-expanded' );
            document.body.classList.add( 'devbar-expanded' );
            this._saveState();
        }

        _collapse( animate = true ) {
            this.expanded = false;
            this.el.classList.remove( 'is-expanded' );
            document.body.classList.remove( 'devbar-expanded' );
            this._saveState();
        }

        _switchTab( tabName ) {
            this.activeTab = tabName;

            // Update tab buttons
            this.el.querySelectorAll( '.devbar-tab' ).forEach( ( btn ) => {
                btn.classList.toggle( 'is-active', btn.dataset.tab === tabName );
            } );

            // Update tab panes
            this.el.querySelectorAll( '.devbar-tab-pane' ).forEach( ( pane ) => {
                pane.classList.toggle( 'is-active', pane.dataset.pane === tabName );
            } );
        }

        /* ---------------------------------------------------------- */
        /*  Data flattening                                            */
        /* ---------------------------------------------------------- */

        /**
         * Flatten the nested toArray() structure from DevBar into the flat
         * property names that the rendering methods expect.
         */
        _flatten( raw ) {
            const meta = raw.meta ?? {};
            const perf = raw.performance ?? {};
            const stor = raw.storage ?? {};
            const hooks = raw.hooks ?? {};

            return {
                // Meta
                php_version:     meta.php_version ?? '',
                klytos_version:  meta.klytos_version ?? '',
                storage_backend: meta.storage_backend ?? 'file',
                current_page:    meta.page ?? '',
                slow_threshold:  meta.slow_threshold ?? 200,
                // Performance
                request_time_ms: perf.execution_time_ms ?? 0,
                memory_usage:    perf.memory_usage ?? 0,
                memory_peak:     perf.memory_peak ?? 0,
                memory_formatted:      perf.memory_formatted ?? '',
                memory_peak_formatted: perf.memory_peak_formatted ?? '',
                cpu_user_time:   perf.cpu_user_time,
                cpu_system_time: perf.cpu_system_time,
                included_files_count: perf.included_files_count ?? 0,
                // Storage
                queries_count:   stor.total_ops ?? 0,
                queries_time_ms: stor.total_time_ms ?? 0,
                storage:         stor,
                // Hooks
                hooks_count:     hooks.total_fired ?? 0,
                hooks:           hooks,
                // Logs, deprecations, cache, timers (pass through)
                logs:            raw.logs ?? [],
                deprecations:    raw.deprecations ?? [],
                cache:           raw.cache ?? {},
                timers:          raw.timers ?? [],
                assets:          raw.assets ?? [],
            };
        }

        /* ---------------------------------------------------------- */
        /*  Formatting helpers                                         */
        /* ---------------------------------------------------------- */

        _formatMs( ms ) {
            if ( ms === undefined || ms === null ) {
                return '-';
            }
            const val = parseFloat( ms );
            if ( val < 1 ) {
                return val.toFixed( 2 ) + ' ms';
            }
            if ( val < 1000 ) {
                return val.toFixed( 1 ) + ' ms';
            }
            return ( val / 1000 ).toFixed( 2 ) + ' s';
        }

        _formatBytes( bytes ) {
            if ( bytes === undefined || bytes === null ) {
                return '-';
            }
            const val = parseInt( bytes, 10 );
            if ( val === 0 ) {
                return '0 B';
            }
            const units = [ 'B', 'KB', 'MB', 'GB' ];
            const i = Math.floor( Math.log( val ) / Math.log( 1024 ) );
            const size = ( val / Math.pow( 1024, i ) ).toFixed( i > 0 ? 1 : 0 );
            return size + ' ' + units[ i ];
        }

        _timeClass( ms ) {
            if ( ms === undefined || ms === null ) {
                return '';
            }
            const val = parseFloat( ms );
            if ( val < 100 ) {
                return 'devbar-time--fast';
            }
            if ( val <= 200 ) {
                return 'devbar-time--medium';
            }
            return 'devbar-time--slow';
        }

        _memoryClass( bytes ) {
            if ( bytes === undefined || bytes === null ) {
                return '';
            }
            const mb = parseInt( bytes, 10 ) / ( 1024 * 1024 );
            if ( mb < 32 ) {
                return 'devbar-memory--ok';
            }
            if ( mb <= 64 ) {
                return 'devbar-memory--warn';
            }
            return 'devbar-memory--critical';
        }

        _logBadgeClass( level ) {
            switch ( level ) {
                case 'error':
                case 'critical':
                case 'emergency':
                    return 'devbar-badge--error';
                case 'warning':
                case 'warn':
                    return 'devbar-badge--warning';
                case 'info':
                case 'notice':
                    return 'devbar-badge--info';
                default:
                    return 'devbar-badge--debug';
            }
        }

        _esc( str ) {
            const div = document.createElement( 'div' );
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        /* ---------------------------------------------------------- */
        /*  State persistence                                          */
        /* ---------------------------------------------------------- */

        _saveState() {
            try {
                localStorage.setItem( STORAGE_KEY, JSON.stringify( {
                    expanded: this.expanded,
                } ) );
            } catch ( e ) {
                // Silently ignore storage errors
            }
        }

        _loadState() {
            try {
                const raw = localStorage.getItem( STORAGE_KEY );
                if ( raw ) {
                    const state = JSON.parse( raw );
                    return !!state.expanded;
                }
            } catch ( e ) {
                // Silently ignore
            }
            return false;
        }
    }

    /* -------------------------------------------------------------- */
    /*  Initialization                                                 */
    /* -------------------------------------------------------------- */

    function init() {
        if ( !window.__KLYTOS_DEVBAR_DATA__ ) {
            return;
        }
        new KlytosDevBar( window.__KLYTOS_DEVBAR_DATA__ );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }

} )();
