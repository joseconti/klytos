/**
 * Klytos Editor API — Wrapper around @automattic/isolated-block-editor
 *
 * Uses the browser standalone API: wp.attachEditor( textarea ).
 * See: https://github.com/Automattic/isolated-block-editor
 *
 * @copyright 2024-2026 Jose Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 * @version   2.0.0
 */

( function( window, document ) {
    'use strict';

    var KlytosEditor = {

        _slug: null,
        _config: {},
        _container: null,
        _textarea: null,
        _autosaveTimer: null,
        _dirty: false,
        _lastSavedHash: '',

        /**
         * Initialize the editor.
         *
         * @param {string|HTMLElement} container - CSS selector or DOM element.
         * @param {object} options - Configuration options.
         * @return {KlytosEditor}
         */
        init: function( container, options ) {
            var self = this;

            if ( typeof container === 'string' ) {
                self._container = document.querySelector( container );
            } else {
                self._container = container;
            }

            if ( ! self._container ) {
                console.error( 'KlytosEditor: Container not found.' );
                return self;
            }

            self._config = Object.assign( {
                slug: '',
                content: '',
                apiBase: '',
                csrfToken: '',
                placeholder: 'Start writing or add a block...',
                allowBlocks: null,
                autosaveInterval: 60,
                onSave: function() {},
                onChange: function() {},
                onError: function( err ) { console.error( 'KlytosEditor:', err ); }
            }, options || {} );

            self._slug = self._config.slug;

            // Check that wp.attachEditor is available (loaded by isolated-block-editor.js).
            if ( ! window.wp || typeof window.wp.attachEditor !== 'function' ) {
                self._config.onError( 'Gutenberg library not loaded. Check vendor/gutenberg/ files.' );
                self._showFallback();
                return self;
            }

            // Create a textarea for wp.attachEditor.
            self._textarea = document.createElement( 'textarea' );
            self._textarea.id = 'klytos-gutenberg-textarea';
            self._textarea.value = self._config.content || '';
            self._textarea.style.width = '100%';
            self._textarea.style.minHeight = '500px';
            self._container.appendChild( self._textarea );

            // Attach the isolated block editor to the textarea.
            // Enable the inspector sidebar and full toolbar for a WP-like experience.
            wp.attachEditor( self._textarea, {
                iso: {
                    toolbar: {
                        inserter: true,
                        undo: true,
                        inspector: true,
                        navigation: true,
                        selectorTool: true,
                        toc: true
                    },
                    sidebar: {
                        inspector: true,
                        inserter: true
                    },
                    moreMenu: {
                        editor: true,
                        fullscreen: false,
                        topToolbar: true
                    },
                    defaultPreferences: {
                        fixedToolbar: true
                    }
                }
            } );

            // Start autosave if enabled.
            if ( self._config.autosaveInterval > 0 ) {
                self._startAutosave();
            }

            self._dirty = false;
            self._lastSavedHash = self._hashContent( self._config.content );

            // Warn before leaving with unsaved changes.
            window.addEventListener( 'beforeunload', function( e ) {
                if ( self._dirty ) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            } );

            // Listen for content changes via wp.data store.
            if ( window.wp && window.wp.data ) {
                var unsubscribe = window.wp.data.subscribe( function() {
                    var html = self.getContent();
                    var newHash = self._hashContent( html );
                    if ( newHash !== self._lastSavedHash ) {
                        self._dirty = true;
                        self._config.onChange( html );
                    }
                } );
            }

            return self;
        },

        /**
         * Get the current HTML content from the editor.
         *
         * @return {string}
         */
        getContent: function() {
            if ( window.wp && window.wp.data ) {
                try {
                    var store = window.wp.data.select( 'isolated/editor' );
                    if ( store && store.getBlocks ) {
                        var blocks = store.getBlocks();
                        if ( window.wp.blocks && window.wp.blocks.serialize ) {
                            return window.wp.blocks.serialize( blocks );
                        }
                    }
                } catch ( e ) {
                    // Fall through to textarea fallback.
                }
            }
            // Fallback: read from textarea (wp.attachEditor syncs back).
            return this._textarea ? this._textarea.value : '';
        },

        /**
         * Get the current blocks as an array.
         *
         * @return {array}
         */
        getBlocks: function() {
            if ( window.wp && window.wp.data ) {
                try {
                    var store = window.wp.data.select( 'isolated/editor' );
                    if ( store && store.getBlocks ) {
                        return store.getBlocks();
                    }
                } catch ( e ) {
                    // Ignore.
                }
            }
            return [];
        },

        /**
         * Set HTML content in the editor.
         *
         * @param {string} html
         * @return {KlytosEditor}
         */
        setContent: function( html ) {
            if ( window.wp && window.wp.data && window.wp.blocks ) {
                try {
                    var dispatch = window.wp.data.dispatch( 'isolated/editor' );
                    if ( dispatch && dispatch.resetBlocks ) {
                        var blocks = window.wp.blocks.parse( html );
                        dispatch.resetBlocks( blocks );
                    }
                } catch ( e ) {
                    // Ignore.
                }
            }
            this._dirty = true;
            return this;
        },

        /**
         * Save content to the server.
         *
         * @param {object} extraData
         * @return {Promise}
         */
        save: function( extraData ) {
            var self = this;
            var content = self.getContent();
            var blocks = self.getBlocks();

            var payload = Object.assign( {
                slug: self._slug,
                content_html: content,
                content_blocks: JSON.stringify( blocks ),
                csrf: self._config.csrfToken
            }, extraData || {} );

            return self._post( self._config.apiBase + '/api/autosave.php', payload )
                .then( function( response ) {
                    self._dirty = false;
                    self._lastSavedHash = self._hashContent( content );
                    self._config.onSave( response );
                    return response;
                } )
                .catch( function( err ) {
                    self._config.onError( err );
                    throw err;
                } );
        },

        isDirty: function() {
            return this._dirty;
        },

        destroy: function() {
            if ( this._autosaveTimer ) {
                clearInterval( this._autosaveTimer );
                this._autosaveTimer = null;
            }
            if ( this._container ) {
                this._container.innerHTML = '';
            }
        },

        // ── Private ───────────────────────────────────────────

        _showFallback: function() {
            var self = this;
            var html = '<div class="klytos-editor-fallback">' +
                '<p class="klytos-editor-fallback__notice">' +
                    'Visual editor unavailable. Using HTML editor.' +
                '</p>' +
                '<textarea class="klytos-editor-fallback__textarea" ' +
                    'rows="30" style="width:100%;font-family:monospace;font-size:14px;padding:1rem;">' +
                    self._escapeHtml( self._config.content ) +
                '</textarea>' +
            '</div>';

            self._container.innerHTML = html;

            var textarea = self._container.querySelector( 'textarea' );
            self._textarea = textarea;

            if ( textarea ) {
                textarea.addEventListener( 'input', function() {
                    self._dirty = true;
                    self._config.onChange( textarea.value );
                } );
            }
        },

        _startAutosave: function() {
            var self = this;
            self._autosaveTimer = setInterval( function() {
                if ( self._dirty && self._slug ) {
                    self.save( { autosave: '1' } ).catch( function() {} );
                }
            }, self._config.autosaveInterval * 1000 );
        },

        _post: function( url, data ) {
            var formData = new FormData();
            for ( var key in data ) {
                if ( data.hasOwnProperty( key ) ) {
                    formData.append( key, data[ key ] );
                }
            }

            return fetch( url, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            } ).then( function( response ) {
                if ( ! response.ok ) {
                    throw new Error( 'HTTP ' + response.status );
                }
                return response.json();
            } );
        },

        _hashContent: function( str ) {
            var hash = 0;
            str = str || '';
            for ( var i = 0; i < str.length; i++ ) {
                var chr = str.charCodeAt( i );
                hash = ( ( hash << 5 ) - hash ) + chr;
                hash |= 0;
            }
            return String( hash );
        },

        _escapeHtml: function( str ) {
            var div = document.createElement( 'div' );
            div.appendChild( document.createTextNode( str || '' ) );
            return div.innerHTML;
        }
    };

    window.KlytosEditor = KlytosEditor;

} )( window, document );
