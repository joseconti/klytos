/**
 * Klytos Importer — Admin JavaScript
 *
 * Tab switching, file drag-and-drop, delete confirmation.
 * No inline event handlers — all via addEventListener.
 */

document.addEventListener( 'DOMContentLoaded', function () {
    'use strict';

    // ─── Tab switching ──────────────────────────────────────
    const tabs        = document.querySelectorAll( '.importer-tab' );
    const tabContents = document.querySelectorAll( '.importer-tab-content' );

    tabs.forEach( function ( tab ) {
        tab.addEventListener( 'click', function () {
            const target = this.getAttribute( 'data-tab' );

            tabs.forEach( function ( t ) { t.classList.remove( 'active' ); } );
            tabContents.forEach( function ( c ) { c.classList.remove( 'active' ); } );

            this.classList.add( 'active' );
            var content = document.getElementById( 'tab-' + target );
            if ( content ) {
                content.classList.add( 'active' );
            }
        } );
    } );

    // ─── File upload ────────────────────────────────────────
    const uploadZone  = document.getElementById( 'upload-zone' );
    const fileInput   = document.getElementById( 'xml-file-input' );
    const browseBtn   = document.getElementById( 'browse-btn' );
    const fileInfo    = document.getElementById( 'upload-file-info' );
    const fileName    = document.getElementById( 'upload-file-name' );
    const fileSize    = document.getElementById( 'upload-file-size' );
    const clearBtn    = document.getElementById( 'upload-clear' );
    const analyzeBtn  = document.getElementById( 'analyze-btn' );

    if ( uploadZone && fileInput ) {
        // Click to browse.
        uploadZone.addEventListener( 'click', function ( e ) {
            if ( e.target !== browseBtn && !browseBtn.contains( e.target ) ) {
                fileInput.click();
            }
        } );

        if ( browseBtn ) {
            browseBtn.addEventListener( 'click', function ( e ) {
                e.stopPropagation();
                fileInput.click();
            } );
        }

        // Drag and drop.
        uploadZone.addEventListener( 'dragover', function ( e ) {
            e.preventDefault();
            this.classList.add( 'drag-over' );
        } );

        uploadZone.addEventListener( 'dragleave', function () {
            this.classList.remove( 'drag-over' );
        } );

        uploadZone.addEventListener( 'drop', function ( e ) {
            e.preventDefault();
            this.classList.remove( 'drag-over' );

            var files = e.dataTransfer.files;
            if ( files.length > 0 ) {
                fileInput.files = files;
                showFileInfo( files[0] );
            }
        } );

        // File selected via input.
        fileInput.addEventListener( 'change', function () {
            if ( this.files.length > 0 ) {
                showFileInfo( this.files[0] );
            }
        } );

        // Clear file.
        if ( clearBtn ) {
            clearBtn.addEventListener( 'click', function () {
                fileInput.value = '';
                hideFileInfo();
            } );
        }
    }

    function showFileInfo( file ) {
        if ( fileName ) fileName.textContent = file.name;
        if ( fileSize ) fileSize.textContent = formatFileSize( file.size );
        if ( fileInfo ) fileInfo.style.display = 'flex';
        if ( uploadZone ) uploadZone.style.display = 'none';
        if ( analyzeBtn ) analyzeBtn.disabled = false;
    }

    function hideFileInfo() {
        if ( fileInfo ) fileInfo.style.display = 'none';
        if ( uploadZone ) uploadZone.style.display = '';
        if ( analyzeBtn ) analyzeBtn.disabled = true;
    }

    function formatFileSize( bytes ) {
        if ( bytes === 0 ) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
        return ( bytes / Math.pow( 1024, i ) ).toFixed( 1 ) + ' ' + units[i];
    }

    // ─── URL import: 3-step flow ───────────────────────────
    var stepUrl        = document.getElementById( 'step-url' );
    var stepPrompt     = document.getElementById( 'step-prompt' );
    var importUrlInput = document.getElementById( 'import-url' );
    var urlLabel       = document.getElementById( 'url-label' );
    var generateBtn    = document.getElementById( 'generate-prompt-btn' );
    var promptText     = document.getElementById( 'prompt-text' );
    var copyPromptBtn  = document.getElementById( 'copy-prompt-btn' );
    var methodRadios   = document.querySelectorAll( 'input[name="import_method"]' );
    var selectedMethod = '';

    var labels = {
        auto:    { label: 'URL del sitio web',        placeholder: 'https://example.com' },
        sitemap: { label: 'URL completa del sitemap',  placeholder: 'https://example.com/sitemap.xml' },
        crawl:   { label: 'URL de la p\u00e1gina principal', placeholder: 'https://example.com' }
    };

    // Step 1 → Step 2: method selected → show URL input.
    methodRadios.forEach( function ( radio ) {
        radio.addEventListener( 'change', function () {
            selectedMethod = this.value;
            var cfg = labels[selectedMethod] || labels.auto;

            if ( urlLabel ) urlLabel.textContent = cfg.label;
            if ( importUrlInput ) {
                importUrlInput.placeholder = cfg.placeholder;
                importUrlInput.value = '';
                importUrlInput.focus();
            }
            if ( generateBtn ) generateBtn.disabled = true;
            if ( stepUrl ) stepUrl.style.display = '';
            if ( stepPrompt ) stepPrompt.style.display = 'none';

            // Highlight selected card.
            document.querySelectorAll( '.method-card' ).forEach( function ( card ) {
                card.classList.remove( 'selected' );
            } );
            this.closest( '.method-card' ).classList.add( 'selected' );
        } );
    } );

    // Enable generate button when URL is entered.
    if ( importUrlInput ) {
        importUrlInput.addEventListener( 'input', function () {
            if ( generateBtn ) {
                generateBtn.disabled = !this.value.trim();
            }
            if ( stepPrompt ) stepPrompt.style.display = 'none';
        } );
    }

    // Step 2 → Step 3: generate prompt.
    if ( generateBtn ) {
        generateBtn.addEventListener( 'click', function () {
            var url = importUrlInput ? importUrlInput.value.trim() : '';
            if ( !url || !promptText ) return;

            var prompt = '';
            if ( selectedMethod === 'sitemap' ) {
                prompt = 'Import this site using its sitemap: ' + url;
            } else if ( selectedMethod === 'crawl' ) {
                prompt = 'Import this site by crawling from the homepage: ' + url;
            } else {
                prompt = 'Import this site: ' + url;
            }

            promptText.textContent = prompt;
            if ( stepPrompt ) stepPrompt.style.display = '';
        } );
    }

    // Copy prompt to clipboard.
    if ( copyPromptBtn ) {
        copyPromptBtn.addEventListener( 'click', function () {
            var text = promptText ? promptText.textContent : '';
            if ( !text ) return;

            navigator.clipboard.writeText( text ).then( function () {
                var icon = copyPromptBtn.querySelector( 'i' );
                if ( icon ) {
                    icon.className = 'fa-solid fa-check';
                    setTimeout( function () {
                        icon.className = 'fa-solid fa-copy';
                    }, 1500 );
                }
            } );
        } );
    }

    // ─── Delete confirmation ────────────────────────────────
    document.querySelectorAll( '[data-confirm]' ).forEach( function ( btn ) {
        btn.addEventListener( 'click', function ( e ) {
            var message = this.getAttribute( 'data-confirm' );
            if ( !confirm( message ) ) {
                e.preventDefault();
            }
        } );
    } );
} );
