/**
 * Klytos Editor API — Wrapper around @automattic/isolated-block-editor
 *
 * This file is the ONLY interface between Klytos CMS and Gutenberg.
 * The rest of the CMS never talks to Gutenberg directly.
 * When updating Gutenberg, replace vendor/gutenberg/ files only.
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 * @version   1.0.0
 */

( function( window, document ) {
    'use strict';

    /**
     * KlytosEditor — Public API
     *
     * Usage:
     *   KlytosEditor.init( '#editor', { ... } );
     *   KlytosEditor.getContent();
     *   KlytosEditor.getBlocks();
     *   KlytosEditor.setContent( '<p>Hello</p>' );
     *   KlytosEditor.save();
     *   KlytosEditor.destroy();
     */
    var KlytosEditor = {

        /** @type {object|null} Internal editor instance */
        _instance: null,

        /** @type {string|null} Current page slug */
        _slug: null,

        /** @type {object} Configuration */
        _config: {},

        /** @type {HTMLElement|null} Container element */
        _container: null,

        /** @type {number|null} Autosave interval ID */
        _autosaveTimer: null,

        /** @type {boolean} Whether content has changed since last save */
        _dirty: false,

        /** @type {string} Last saved content hash for dirty detection */
        _lastSavedHash: '',

        /**
         * Initialize the editor.
         *
         * @param {string|HTMLElement} container - CSS selector or DOM element.
         * @param {object}             options   - Configuration options.
         * @param {string}             options.slug         - Page slug (for save/load).
         * @param {string}             options.content      - Initial HTML content.
         * @param {string}             options.apiBase      - Base URL for API calls.
         * @param {string}             options.csrfToken    - CSRF token for POST requests.
         * @param {string}             options.placeholder  - Placeholder text.
         * @param {string[]}           options.allowBlocks  - Whitelist of blocks (null = all).
         * @param {number}             options.autosaveInterval - Autosave in seconds (0 = off).
         * @param {function}           options.onSave       - Callback after save.
         * @param {function}           options.onChange      - Callback on content change.
         * @param {function}           options.onError      - Callback on error.
         * @return {KlytosEditor}
         */
        init: function( container, options ) {
            var self = this;

            // Resolve container element.
            if ( typeof container === 'string' ) {
                self._container = document.querySelector( container );
            } else {
                self._container = container;
            }

            if ( ! self._container ) {
                console.error( 'KlytosEditor: Container not found.' );
                return self;
            }

            // Merge options with defaults.
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

            // Check that the Gutenberg vendor library is loaded.
            if ( ! window.IsolatedBlockEditor ) {
                self._config.onError( 'Gutenberg library not loaded. Check vendor/gutenberg/ files.' );
                self._showFallback();
                return self;
            }

            // Initialize the isolated block editor.
            self._initGutenberg();

            // Start autosave if enabled.
            if ( self._config.autosaveInterval > 0 ) {
                self._startAutosave();
            }

            // Mark as clean.
            self._dirty = false;
            self._lastSavedHash = self._hashContent( self._config.content );

            // Warn before leaving with unsaved changes.
            window.addEventListener( 'beforeunload', function( e ) {
                if ( self._dirty ) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            } );

            return self;
        },

        /**
         * Get the current HTML content.
         *
         * @return {string} HTML content.
         */
        getContent: function() {
            if ( this._instance && typeof this._instance.getContent === 'function' ) {
                return this._instance.getContent();
            }
            // Fallback: read from the editor DOM.
            var editorEl = this._container.querySelector( '.block-editor-writing-flow' );
            return editorEl ? editorEl.innerHTML : '';
        },

        /**
         * Get the current blocks as JSON array.
         *
         * @return {array} Block objects.
         */
        getBlocks: function() {
            if ( this._instance && typeof this._instance.getBlocks === 'function' ) {
                return this._instance.getBlocks();
            }
            return [];
        },

        /**
         * Set HTML content in the editor.
         *
         * @param {string} html - HTML content.
         * @return {KlytosEditor}
         */
        setContent: function( html ) {
            if ( this._instance && typeof this._instance.setContent === 'function' ) {
                this._instance.setContent( html );
            }
            this._dirty = true;
            return this;
        },

        /**
         * Save content to the server.
         *
         * @param {object} extraData - Additional fields to include in the POST.
         * @return {Promise}
         */
        save: function( extraData ) {
            var self = this;
            var content = self.getContent();
            var blocks  = self.getBlocks();

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
                    self._showNotification( 'Saved', 'success' );
                    return response;
                } )
                .catch( function( err ) {
                    self._config.onError( err );
                    self._showNotification( 'Save failed', 'error' );
                    throw err;
                } );
        },

        /**
         * Check if the editor has unsaved changes.
         *
         * @return {boolean}
         */
        isDirty: function() {
            return this._dirty;
        },

        /**
         * Destroy the editor and clean up.
         *
         * @return {void}
         */
        destroy: function() {
            if ( this._autosaveTimer ) {
                clearInterval( this._autosaveTimer );
                this._autosaveTimer = null;
            }
            if ( this._instance && typeof this._instance.destroy === 'function' ) {
                this._instance.destroy();
            }
            if ( this._container ) {
                this._container.innerHTML = '';
            }
            this._instance = null;
        },


        /* ============================================================
         * PRIVATE METHODS — Internal implementation
         * These talk to Gutenberg. Everything above does NOT.
         * ============================================================ */

        /**
         * Initialize the Gutenberg isolated block editor.
         *
         * @private
         */
        _initGutenberg: function() {
            var self = this;
            var IsolatedBlockEditor = window.IsolatedBlockEditor;
            var React    = window.React || window.wp.element;
            var ReactDOM = window.ReactDOM || window.wp.element;

            if ( ! React || ! ReactDOM ) {
                self._config.onError( 'React/ReactDOM not available.' );
                self._showFallback();
                return;
            }

            // Build Gutenberg settings object.
            var settings = {
                iso: {
                    moreMenu: true,
                    sidebar: {
                        inspector: true,
                        inserter: true
                    },
                    toolbar: {
                        inspector: true,
                        navigation: true
                    },
                    defaultPreferences: {
                        fixedToolbar: true
                    }
                },
                editor: {
                    bodyPlaceholder: self._config.placeholder,
                    hasFixedToolbar: true
                }
            };

            // Block whitelist.
            if ( self._config.allowBlocks ) {
                settings.iso.blocks = {
                    allowBlocks: self._config.allowBlocks
                };
            }

            // Create React element.
            var editorElement = React.createElement( IsolatedBlockEditor, {
                settings: settings,

                onSaveContent: function( html ) {
                    // Called by Gutenberg when content changes.
                    var newHash = self._hashContent( html );
                    if ( newHash !== self._lastSavedHash ) {
                        self._dirty = true;
                        self._config.onChange( html );
                    }
                },

                onLoad: function( parse, rawHandler ) {
                    // Called once when editor loads — provide initial content.
                    if ( self._config.content ) {
                        return parse( self._config.content );
                    }
                    return [];
                },

                onError: function() {
                    self._config.onError( 'Gutenberg editor error.' );
                }
            } );

            // Render into container.
            var root;
            if ( ReactDOM.createRoot ) {
                // React 18+
                root = ReactDOM.createRoot( self._container );
                root.render( editorElement );
            } else {
                // React 17 fallback
                ReactDOM.render( editorElement, self._container );
            }

            // Store references.
            self._instance = {
                root: root,
                getContent: function() {
                    // Extract HTML from the editor via WordPress data stores.
                    if ( window.wp && window.wp.data ) {
                        var store = window.wp.data.select( 'isolated/editor' );
                        if ( store && store.getBlocks ) {
                            var blocks = store.getBlocks();
                            var serializer = window.wp.blocks;
                            if ( serializer && serializer.serialize ) {
                                return serializer.serialize( blocks );
                            }
                        }
                    }
                    // DOM fallback.
                    var writingFlow = self._container.querySelector( '.block-editor-writing-flow' );
                    return writingFlow ? writingFlow.innerHTML : '';
                },
                getBlocks: function() {
                    if ( window.wp && window.wp.data ) {
                        var store = window.wp.data.select( 'isolated/editor' );
                        if ( store && store.getBlocks ) {
                            return store.getBlocks();
                        }
                    }
                    return [];
                },
                setContent: function( html ) {
                    if ( window.wp && window.wp.data ) {
                        var dispatch = window.wp.data.dispatch( 'isolated/editor' );
                        if ( dispatch && dispatch.resetBlocks ) {
                            var blocks = window.wp.blocks.parse( html );
                            dispatch.resetBlocks( blocks );
                        }
                    }
                },
                destroy: function() {
                    if ( root && root.unmount ) {
                        root.unmount();
                    }
                }
            };
        },

        /**
         * Show fallback textarea if Gutenberg fails to load.
         *
         * @private
         */
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

            // Wire up the fallback so getContent/setContent still work.
            self._instance = {
                getContent: function() {
                    return textarea ? textarea.value : '';
                },
                getBlocks: function() {
                    return [];
                },
                setContent: function( newHtml ) {
                    if ( textarea ) {
                        textarea.value = newHtml;
                    }
                },
                destroy: function() {}
            };

            if ( textarea ) {
                textarea.addEventListener( 'input', function() {
                    self._dirty = true;
                    self._config.onChange( textarea.value );
                } );
            }
        },

        /**
         * Start the autosave timer.
         *
         * @private
         */
        _startAutosave: function() {
            var self = this;
            self._autosaveTimer = setInterval( function() {
                if ( self._dirty && self._slug ) {
                    self.save( { autosave: '1' } ).catch( function() {
                        // Silently fail autosave — user will be warned on exit.
                    } );
                }
            }, self._config.autosaveInterval * 1000 );
        },

        /**
         * POST data to an endpoint.
         *
         * @private
         * @param  {string} url  - Endpoint URL.
         * @param  {object} data - Key/value pairs.
         * @return {Promise}
         */
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

        /**
         * Simple hash for dirty detection.
         *
         * @private
         * @param  {string} str
         * @return {string}
         */
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

        /**
         * Escape HTML for safe insertion.
         *
         * @private
         * @param  {string} str
         * @return {string}
         */
        _escapeHtml: function( str ) {
            var div = document.createElement( 'div' );
            div.appendChild( document.createTextNode( str || '' ) );
            return div.innerHTML;
        },

        /**
         * Show a brief notification toast.
         *
         * @private
         * @param {string} message
         * @param {string} type - 'success' or 'error'
         */
        _showNotification: function( message, type ) {
            var toast = document.createElement( 'div' );
            toast.className = 'klytos-editor-toast klytos-editor-toast--' + type;
            toast.textContent = message;
            document.body.appendChild( toast );

            // Animate in.
            requestAnimationFrame( function() {
                toast.classList.add( 'klytos-editor-toast--visible' );
            } );

            // Remove after 3 seconds.
            setTimeout( function() {
                toast.classList.remove( 'klytos-editor-toast--visible' );
                setTimeout( function() {
                    if ( toast.parentNode ) {
                        toast.parentNode.removeChild( toast );
                    }
                }, 300 );
            }, 3000 );
        },


        /* ============================================================
         * MEDIA UPLOAD — Bridge for Gutenberg's media upload system
         * ============================================================ */

        /**
         * Handle media upload from Gutenberg blocks.
         * This is passed to Gutenberg via settings.editor.mediaUpload.
         *
         * @param {object} params
         * @param {FileList} params.filesList
         * @param {function} params.onFileChange
         * @param {function} params.onError
         * @param {string[]} params.allowedTypes
         */
        _handleMediaUpload: function( params ) {
            var self = this;
            var files = params.filesList;

            Array.from( files ).forEach( function( file ) {
                var formData = new FormData();
                formData.append( 'file', file );
                formData.append( 'csrf', self._config.csrfToken );

                fetch( self._config.apiBase + '/api/media-upload.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                } )
                .then( function( response ) {
                    if ( ! response.ok ) {
                        throw new Error( 'Upload failed: HTTP ' + response.status );
                    }
                    return response.json();
                } )
                .then( function( media ) {
                    // Return media object in the format Gutenberg expects.
                    params.onFileChange( [ {
                        id: media.id || Date.now(),
                        url: media.url,
                        alt: media.alt || file.name,
                        title: media.title || file.name,
                        mime: media.mime || file.type,
                        type: file.type.split( '/' )[ 0 ],
                        sizes: media.sizes || {}
                    } ] );
                } )
                .catch( function( err ) {
                    if ( params.onError ) {
                        params.onError( err.message );
                    }
                    self._config.onError( err );
                } );
            } );
        }
    };

    // Expose globally.
    window.KlytosEditor = KlytosEditor;

} )( window, document );
