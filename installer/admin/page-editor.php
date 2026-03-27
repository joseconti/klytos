<?php
/**
 * Klytos Admin — Page Editor (Gutenberg / TinyMCE)
 *
 * Supports two editors, selectable in Settings:
 * - Gutenberg: block editor via @automattic/isolated-block-editor.
 * - TinyMCE: classic WYSIWYG editor (self-hosted or CDN).
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   Elastic License 2.0 (ELv2)
 */

$currentPage = 'pages';
require __DIR__ . '/bootstrap.php';

$editorType = $app->getSiteConfig()->getValue('editor', 'gutenberg');
$auth = $app->getAuth();
$csrf = $auth->getCsrfToken();
$pm   = $app->getPages();

// Determine if editing or creating.
$slug       = $_GET['slug'] ?? '';
$isEditing  = false;
$page       = null;
$pageTitle  = '';
$pageContent = '';
$pageBlocks = '';
$pageStatus = 'draft';
$pageTemplate = 'default';
$pageLang   = $app->getSiteConfig()->get( 'default_language', 'en' );
$pageMetaDesc  = '';
$pageOgImage   = '';
$pageOgTitle   = '';
$pageOgDesc    = '';
$pageTwTitle   = '';
$pageTwDesc    = '';
$pageCanonical = '';
$pageNoIndex   = false;
$pageCustomCss = '';
$pageCustomJs  = '';

if ( $slug ) {
    $page = $pm->get( $slug );
    if ( $page ) {
        $isEditing     = true;
        $pageTitle     = $page['title'] ?? '';
        $pageContent   = $page['content_html'] ?? '';
        $pageBlocks    = $page['content_blocks'] ?? '';
        $pageStatus    = $page['status'] ?? 'draft';
        $pageTemplate  = $page['template'] ?? 'default';
        $pageLang      = $page['lang'] ?? $pageLang;
        $pageMetaDesc  = $page['meta_description'] ?? '';
        $pageOgImage   = $page['og_image'] ?? '';
        $pageOgTitle   = $page['og_title'] ?? '';
        $pageOgDesc    = $page['og_description'] ?? '';
        $pageTwTitle   = $page['twitter_title'] ?? '';
        $pageTwDesc    = $page['twitter_description'] ?? '';
        $pageCanonical = $page['canonical_url'] ?? '';
        $pageNoIndex   = ! empty( $page['noindex'] );
        $pageCustomCss = $page['custom_css'] ?? '';
        $pageCustomJs  = $page['custom_js'] ?? '';
    }
}

// Handle POST (save).
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf( $_POST['csrf'] ?? '' ) ) {
    $data = [
        'title'           => trim( $_POST['title'] ?? '' ),
        'content_html'    => $_POST['content_html'] ?? '',
        'content_blocks'  => $_POST['content_blocks'] ?? '',
        'status'          => $_POST['status'] ?? 'draft',
        'template'        => $_POST['template'] ?? 'default',
        'lang'            => $_POST['lang'] ?? 'en',
        'meta_description'    => trim( $_POST['meta_description'] ?? '' ),
        'og_image'            => trim( $_POST['og_image'] ?? '' ),
        'og_title'            => trim( $_POST['og_title'] ?? '' ),
        'og_description'      => trim( $_POST['og_description'] ?? '' ),
        'twitter_title'       => trim( $_POST['twitter_title'] ?? '' ),
        'twitter_description' => trim( $_POST['twitter_description'] ?? '' ),
        'canonical_url'       => trim( $_POST['canonical_url'] ?? '' ),
        'noindex'             => ! empty( $_POST['noindex'] ),
        'custom_css'          => $_POST['custom_css'] ?? '',
        'custom_js'           => $_POST['custom_js'] ?? '',
    ];

    $saveSlug = $_POST['slug'] ?? '';

    if ( ! $saveSlug && $data['title'] ) {
        // Auto-generate slug from title.
        $saveSlug = strtolower( trim( preg_replace( '/[^a-zA-Z0-9\/]+/', '-', $data['title'] ), '-' ) );
    }

    if ( $saveSlug && $data['title'] ) {
        $data['slug'] = $saveSlug;

        if ( $isEditing ) {
            $pm->update( $saveSlug, $data );
        } else {
            $pm->create( $saveSlug, $data );
            $isEditing = true;
        }

        $slug = $saveSlug;
        $page = $pm->get( $slug );
        $success = true;

        // Update local vars.
        $pageTitle     = $data['title'];
        $pageContent   = $data['content_html'];
        $pageBlocks    = $data['content_blocks'];
        $pageStatus    = $data['status'];
        $pageTemplate  = $data['template'];
        $pageLang      = $data['lang'];
        $pageMetaDesc  = $data['meta_description'];
        $pageOgImage   = $data['og_image'];
        $pageOgTitle   = $data['og_title'];
        $pageOgDesc    = $data['og_description'];
        $pageTwTitle   = $data['twitter_title'];
        $pageTwDesc    = $data['twitter_description'];
        $pageCanonical = $data['canonical_url'];
        $pageNoIndex   = $data['noindex'];
        $pageCustomCss = $data['custom_css'];
        $pageCustomJs  = $data['custom_js'];
    } else {
        $error = 'Title and slug are required.';
    }
}

$adminPageTitle = $isEditing
    ? __( 'pages.edit_page' ) . ': ' . htmlspecialchars( $pageTitle )
    : __( 'pages.create_page' );

$pageTitle_header = $adminPageTitle;
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>

        <?php if ( isset( $success ) ): ?>
            <div class="alert alert-success" style="margin-bottom:0;"><?php echo __( 'common.success' ); ?></div>
        <?php endif; ?>

        <?php if ( isset( $error ) ): ?>
            <div class="alert alert-error" style="margin-bottom:0;"><?php echo htmlspecialchars( $error ); ?></div>
        <?php endif; ?>

        <form method="post" id="page-editor-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf ); ?>">
            <input type="hidden" name="slug" value="<?php echo htmlspecialchars( $slug ); ?>" id="page-slug">
            <input type="hidden" name="content_html" value="" id="content-html-field">
            <input type="hidden" name="content_blocks" value="" id="content-blocks-field">

            <!-- ═══ FULLSCREEN EDITOR SHELL ═══ -->
            <div class="klytos-editor-shell" id="klytos-editor-shell">

                <!-- ─── Top Header Bar (like WordPress) ─── -->
                <div class="klytos-editor-header">
                    <div class="klytos-editor-header__left">
                        <a href="pages.php" class="klytos-editor-header__back" title="<?php echo __( 'common.back' ); ?>">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        </a>
                        <div class="klytos-editor-header__title-group">
                            <input
                                type="text"
                                name="title"
                                class="klytos-editor-header__title"
                                placeholder="<?php echo __( 'pages.page_title' ); ?>..."
                                value="<?php echo htmlspecialchars( $pageTitle ); ?>"
                                required
                            >
                            <?php if ( $isEditing ): ?>
                            <span class="klytos-editor-header__slug">/<?php echo htmlspecialchars( $slug ); ?>/</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="klytos-editor-header__center">
                        <span class="klytos-editor-header__status" id="editor-status">
                            <?php if ( $isEditing ): ?>
                                <span class="badge-status badge-<?php echo $pageStatus; ?>"><?php echo ucfirst( $pageStatus ); ?></span>
                            <?php else: ?>
                                <span class="badge-status badge-draft"><?php echo __( 'pages.draft' ); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="klytos-editor-header__right">
                        <?php if ( $isEditing ): ?>
                        <a href="../<?php echo htmlspecialchars( $slug ); ?>/" target="_blank" class="klytos-editor-header__btn klytos-editor-header__btn--ghost">
                            <?php echo __( 'common.preview' ); ?>
                        </a>
                        <?php endif; ?>
                        <button type="submit" name="status" value="draft" class="klytos-editor-header__btn klytos-editor-header__btn--secondary">
                            <?php echo __( 'pages.draft' ); ?>
                        </button>
                        <button type="submit" name="status" value="published" class="klytos-editor-header__btn klytos-editor-header__btn--primary">
                            <?php echo __( 'pages.published' ); ?>
                        </button>
                        <button type="button" class="klytos-editor-header__btn klytos-editor-header__btn--icon" id="toggle-settings-panel" title="Page Settings">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <!-- ─── Editor Body ─── -->
                <div class="klytos-editor-body">

                    <!-- Main Canvas (Gutenberg) -->
                    <div class="klytos-editor-canvas">
                        <div id="klytos-editor-container"></div>
                    </div>

                    <!-- Right Settings Panel (Klytos page settings) -->
                    <div class="klytos-editor-settings" id="klytos-editor-settings">

                        <!-- Tabs: Page / Block -->
                        <div class="klytos-editor-settings__tabs">
                            <button type="button" class="klytos-editor-settings__tab active" data-tab="page"><?php echo __( 'pages.title' ); ?></button>
                            <button type="button" class="klytos-editor-settings__tab" data-tab="seo">SEO</button>
                        </div>

                        <div class="klytos-editor-settings__content">

                            <!-- PAGE TAB -->
                            <div class="klytos-editor-settings__panel active" data-panel="page">

                                <!-- Status & Template -->
                                <div class="klytos-editor-settings__section">
                                    <h3 class="klytos-editor-settings__heading"><?php echo __( 'common.status' ); ?></h3>

                                    <label class="klytos-editor-settings__label"><?php echo __( 'pages.template' ); ?></label>
                                    <select name="template" class="klytos-editor-settings__input">
                                        <option value="default" <?php echo $pageTemplate === 'default' ? 'selected' : ''; ?>>Default</option>
                                        <option value="landing" <?php echo $pageTemplate === 'landing' ? 'selected' : ''; ?>>Landing</option>
                                        <option value="blog-post" <?php echo $pageTemplate === 'blog-post' ? 'selected' : ''; ?>>Blog Post</option>
                                        <option value="blank" <?php echo $pageTemplate === 'blank' ? 'selected' : ''; ?>>Blank</option>
                                    </select>

                                    <label class="klytos-editor-settings__label"><?php echo __( 'pages.language' ); ?></label>
                                    <select name="lang" class="klytos-editor-settings__input">
                                        <option value="en" <?php echo $pageLang === 'en' ? 'selected' : ''; ?>>English</option>
                                        <option value="es" <?php echo $pageLang === 'es' ? 'selected' : ''; ?>>Español</option>
                                        <option value="fr" <?php echo $pageLang === 'fr' ? 'selected' : ''; ?>>Français</option>
                                        <option value="de" <?php echo $pageLang === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                        <option value="pt" <?php echo $pageLang === 'pt' ? 'selected' : ''; ?>>Português</option>
                                        <option value="ca" <?php echo $pageLang === 'ca' ? 'selected' : ''; ?>>Català</option>
                                    </select>
                                </div>

                                <!-- Social Media -->
                                <details class="klytos-editor-settings__section">
                                    <summary class="klytos-editor-settings__heading klytos-editor-settings__heading--toggle">Facebook / LinkedIn</summary>
                                    <div class="klytos-editor-settings__details-body">
                                        <label class="klytos-editor-settings__label">OG Image <span class="klytos-editor-settings__hint">recommended</span></label>
                                        <input type="text" name="og_image" class="klytos-editor-settings__input" value="<?php echo htmlspecialchars( $pageOgImage ); ?>" placeholder="https://... (1200x630px)">
                                        <div class="form-help">1200x630px recommended.</div>

                                        <label class="klytos-editor-settings__label">OG Title</label>
                                        <input type="text" name="og_title" class="klytos-editor-settings__input" value="<?php echo htmlspecialchars( $pageOgTitle ); ?>" maxlength="70" placeholder="Leave empty to use page title">

                                        <label class="klytos-editor-settings__label">OG Description</label>
                                        <textarea name="og_description" rows="2" maxlength="200" class="klytos-editor-settings__input" placeholder="Leave empty to use meta description"><?php echo htmlspecialchars( $pageOgDesc ); ?></textarea>
                                    </div>
                                </details>

                                <!-- Twitter / X -->
                                <details class="klytos-editor-settings__section">
                                    <summary class="klytos-editor-settings__heading klytos-editor-settings__heading--toggle">Twitter / X</summary>
                                    <div class="klytos-editor-settings__details-body">
                                        <label class="klytos-editor-settings__label">Twitter Title</label>
                                        <input type="text" name="twitter_title" class="klytos-editor-settings__input" value="<?php echo htmlspecialchars( $pageTwTitle ); ?>" maxlength="70" placeholder="Leave empty to use OG title">

                                        <label class="klytos-editor-settings__label">Twitter Description</label>
                                        <textarea name="twitter_description" rows="2" maxlength="200" class="klytos-editor-settings__input" placeholder="Leave empty to use OG description"><?php echo htmlspecialchars( $pageTwDesc ); ?></textarea>
                                        <div class="form-help">Uses OG image automatically.</div>
                                    </div>
                                </details>

                                <!-- Custom Code -->
                                <details class="klytos-editor-settings__section">
                                    <summary class="klytos-editor-settings__heading klytos-editor-settings__heading--toggle"><?php echo __( 'pages.custom_css' ); ?> / JS</summary>
                                    <div class="klytos-editor-settings__details-body">
                                        <label class="klytos-editor-settings__label"><?php echo __( 'pages.custom_css' ); ?></label>
                                        <textarea name="custom_css" rows="4" class="klytos-editor-settings__input mono"><?php echo htmlspecialchars( $pageCustomCss ); ?></textarea>

                                        <label class="klytos-editor-settings__label"><?php echo __( 'pages.custom_js' ); ?></label>
                                        <textarea name="custom_js" rows="4" class="klytos-editor-settings__input mono"><?php echo htmlspecialchars( $pageCustomJs ); ?></textarea>
                                    </div>
                                </details>
                            </div>

                            <!-- SEO TAB -->
                            <div class="klytos-editor-settings__panel" data-panel="seo">

                                <div class="klytos-editor-settings__section">
                                    <h3 class="klytos-editor-settings__heading">Meta Description</h3>

                                    <textarea name="meta_description" rows="3" maxlength="160" class="klytos-editor-settings__input" placeholder="120-155 characters. Include keyword and call-to-action."><?php echo htmlspecialchars( $pageMetaDesc ); ?></textarea>
                                    <div class="form-help" id="meta-counter" style="display:flex;justify-content:space-between;">
                                        <span id="meta-count-text">0/160</span>
                                        <span id="meta-quality"></span>
                                    </div>

                                    <label class="klytos-editor-settings__label">Canonical URL</label>
                                    <input type="url" name="canonical_url" class="klytos-editor-settings__input" value="<?php echo htmlspecialchars( $pageCanonical ); ?>" placeholder="Leave empty (recommended)">

                                    <div style="margin-top:0.75rem;">
                                        <label style="display:inline-flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:400;font-size:0.85rem;">
                                            <input type="checkbox" name="noindex" value="1" <?php echo $pageNoIndex ? 'checked' : ''; ?>>
                                            noindex
                                        </label>
                                    </div>
                                </div>

                                <!-- Search Preview -->
                                <div class="klytos-editor-settings__section">
                                    <h3 class="klytos-editor-settings__heading">Search Preview</h3>
                                    <div class="klytos-seo-preview">
                                        <div id="seo-preview-title" class="klytos-seo-preview__title"></div>
                                        <div id="seo-preview-url" class="klytos-seo-preview__url"></div>
                                        <div id="seo-preview-desc" class="klytos-seo-preview__desc"></div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>
</div>

<?php if ($editorType === 'tinymce') { ?>
<!-- TinyMCE Editor -->
<link rel="stylesheet" href="assets/css/klytos-editor.css">
<script src="assets/vendor/tinymce/tinymce.min.js"></script>

<script nonce="<?php echo $cspNonce; ?>">
( function() {
    'use strict';

    var container = document.getElementById( 'klytos-editor-container' );
    var textarea = document.createElement( 'textarea' );
    textarea.id = 'tinymce-editor';
    textarea.style.width = '100%';
    textarea.style.minHeight = '500px';
    container.appendChild( textarea );

    tinymce.init( {
        selector: '#tinymce-editor',
        height: 700,
        menubar: 'file edit view insert format tools table',
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | forecolor backcolor | removeformat | code fullscreen',
        content_style: 'body { font-family: Inter, sans-serif; font-size: 16px; padding: 1.5rem; }',
        promotion: false,
        branding: false,
        license_key: 'gpl',
        setup: function( editor ) {
            editor.on( 'init', function() {
                editor.setContent( <?php echo json_encode( $pageContent ); ?> );
            } );
            editor.on( 'change keyup', function() {
                document.getElementById( 'editor-status' ).textContent = 'Unsaved changes';
                document.getElementById( 'editor-status' ).className = 'klytos-page-editor__status dirty';
            } );
        }
    } );

    document.getElementById( 'page-editor-form' ).addEventListener( 'submit', function() {
        var content = tinymce.get( 'tinymce-editor' ).getContent();
        document.getElementById( 'content-html-field' ).value = content;
        document.getElementById( 'content-blocks-field' ).value = '';
    } );

} )();
</script>

<?php } else { ?>
<!-- React (required by Gutenberg) -->
<script nonce="<?php echo $cspNonce; ?>" src="assets/vendor/gutenberg/react.production.min.js"></script>
<script nonce="<?php echo $cspNonce; ?>" src="assets/vendor/gutenberg/react-dom.production.min.js"></script>

<!-- Gutenberg vendor files (NEVER modify these) -->
<link rel="stylesheet" href="assets/vendor/gutenberg/core.css">
<link rel="stylesheet" href="assets/vendor/gutenberg/isolated-block-editor.css">

<!-- Klytos editor overrides (OUR styles) -->
<link rel="stylesheet" href="assets/css/klytos-editor.css">

<!-- Gutenberg library (NEVER modify this) -->
<script nonce="<?php echo $cspNonce; ?>" src="assets/vendor/gutenberg/isolated-block-editor.js"></script>

<!-- Klytos Editor API wrapper (OUR code) -->
<script nonce="<?php echo $cspNonce; ?>" src="assets/js/klytos-editor.js"></script>

<!-- Initialize the editor -->
<script nonce="<?php echo $cspNonce; ?>">
( function() {
    'use strict';

    // Initialize the Klytos Editor.
    KlytosEditor.init( '#klytos-editor-container', {
        slug: <?php echo json_encode( $slug ); ?>,
        content: <?php echo json_encode( $pageContent ); ?>,
        apiBase: <?php echo json_encode( rtrim( \Klytos\Core\Helpers::getBasePath(), '/' ) . '/admin' ); ?>,
        csrfToken: <?php echo json_encode( $csrf ); ?>,
        placeholder: <?php echo json_encode( __( 'pages.content' ) . '...' ); ?>,
        allowBlocks: null,
        autosaveInterval: 60,

        onSave: function( response ) {
            var el = document.getElementById( 'editor-status' );
            if ( el ) el.innerHTML = '<span class="badge-status badge-published"><?php echo __( 'common.success' ); ?></span>';
        },
        onChange: function( html ) {
            var el = document.getElementById( 'editor-status' );
            if ( el ) el.innerHTML = '<span style="color:#f59e0b;font-size:0.8rem;">Unsaved changes</span>';
        },
        onError: function( err ) {
            console.error( 'KlytosEditor error:', err );
        }
    } );

    // Before form submit, inject content into hidden fields.
    document.getElementById( 'page-editor-form' ).addEventListener( 'submit', function() {
        document.getElementById( 'content-html-field' ).value = KlytosEditor.getContent();
        document.getElementById( 'content-blocks-field' ).value = JSON.stringify( KlytosEditor.getBlocks() );
    } );

} )();
</script>
<?php } ?>

<!-- SEO Preview (shared by both editors) -->
<script nonce="<?php echo $cspNonce; ?>">
( function() {
    'use strict';

    var titleField   = document.querySelector( 'input[name="title"]' );
    var metaField    = document.querySelector( 'textarea[name="meta_description"]' );
    var slugField    = document.getElementById( 'page-slug' );
    var countText    = document.getElementById( 'meta-count-text' );
    var qualityBadge = document.getElementById( 'meta-quality' );
    var previewTitle = document.getElementById( 'seo-preview-title' );
    var previewUrl   = document.getElementById( 'seo-preview-url' );
    var previewDesc  = document.getElementById( 'seo-preview-desc' );
    var siteUrl      = <?php echo json_encode( rtrim( \Klytos\Core\Helpers::publicUrl(), '/' ) ); ?>;
    var siteName     = <?php echo json_encode( $app->getSiteConfig()->getValue( 'site_name', 'Klytos' ) ); ?>;

    function updateSeoPreview() {
        var title = titleField ? titleField.value : '';
        var desc  = metaField ? metaField.value : '';
        var slug  = slugField ? slugField.value : '';
        var len   = desc.length;

        // Counter.
        if ( countText ) {
            countText.textContent = len + '/160';
            countText.style.color = len > 155 ? 'var(--admin-warning)' : '';
        }

        // Quality badge.
        if ( qualityBadge ) {
            if ( len === 0 ) {
                qualityBadge.textContent = 'Missing';
                qualityBadge.style.color = 'var(--admin-error)';
            } else if ( len < 80 ) {
                qualityBadge.textContent = 'Too short';
                qualityBadge.style.color = 'var(--admin-warning)';
            } else if ( len <= 155 ) {
                qualityBadge.textContent = 'Good';
                qualityBadge.style.color = 'var(--admin-success)';
            } else {
                qualityBadge.textContent = 'Too long';
                qualityBadge.style.color = 'var(--admin-warning)';
            }
        }

        // Google SERP Preview.
        if ( previewTitle ) {
            var fullTitle = title;
            if ( siteName && fullTitle.toLowerCase().indexOf( siteName.toLowerCase() ) === -1 ) {
                fullTitle += ' — ' + siteName;
            }
            previewTitle.textContent = fullTitle || 'Page Title';
        }

        if ( previewUrl ) {
            var displayUrl = siteUrl + '/' + ( slug || 'page-slug' ) + '/';
            previewUrl.textContent = displayUrl;
        }

        if ( previewDesc ) {
            previewDesc.textContent = desc || 'No meta description set. Google will generate one from the page content.';
            previewDesc.style.fontStyle = desc ? 'normal' : 'italic';
            previewDesc.style.color = desc ? '#545454' : '#999';
        }
    }

    if ( titleField ) {
        titleField.addEventListener( 'input', updateSeoPreview );
    }
    if ( metaField ) {
        metaField.addEventListener( 'input', updateSeoPreview );
    }
    updateSeoPreview();

} )();
</script>

<!-- Settings panel toggle & tabs -->
<script nonce="<?php echo $cspNonce; ?>">
( function() {
    'use strict';

    // ─── Settings panel toggle ───────────────────────────────
    var toggleBtn = document.getElementById( 'toggle-settings-panel' );
    var panel     = document.getElementById( 'klytos-editor-settings' );

    if ( toggleBtn && panel ) {
        toggleBtn.addEventListener( 'click', function() {
            panel.classList.toggle( 'hidden' );
            toggleBtn.classList.toggle( 'active' );
        } );
    }

    // ─── Tab switching ───────────────────────────────────────
    var tabs   = document.querySelectorAll( '.klytos-editor-settings__tab' );
    var panels = document.querySelectorAll( '.klytos-editor-settings__panel' );

    tabs.forEach( function( tab ) {
        tab.addEventListener( 'click', function() {
            var target = tab.getAttribute( 'data-tab' );

            tabs.forEach( function( t ) { t.classList.remove( 'active' ); } );
            panels.forEach( function( p ) { p.classList.remove( 'active' ); } );

            tab.classList.add( 'active' );
            var targetPanel = document.querySelector( '[data-panel="' + target + '"]' );
            if ( targetPanel ) {
                targetPanel.classList.add( 'active' );
            }
        } );
    } );

} )();
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>
