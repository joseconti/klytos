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

$editorType = $app->getSiteConfig()->get('editor', 'gutenberg');
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

<div class="admin-content">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:1rem;">
            <a href="pages.php" class="btn btn-outline btn-sm">&larr; <?php echo __( 'common.back' ); ?></a>
            <h1 style="font-size:1.1rem;font-weight:600;">
                <?php echo $isEditing ? __( 'pages.edit_page' ) : __( 'pages.create_page' ); ?>
            </h1>
        </div>
        <div style="display:flex;align-items:center;gap:0.75rem;">
            <span class="klytos-page-editor__status" id="editor-status"></span>
            <?php echo htmlspecialchars( $auth->getUsername() ); ?>
            <a href="logout.php" class="btn btn-outline btn-sm"><?php echo __( 'auth.logout' ); ?></a>
        </div>
    </div>

    <div class="admin-main">

        <?php if ( isset( $success ) ): ?>
            <div class="alert alert-success"><?php echo __( 'common.success' ); ?></div>
        <?php endif; ?>

        <?php if ( isset( $error ) ): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
        <?php endif; ?>

        <form method="post" id="page-editor-form">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars( $csrf ); ?>">
            <input type="hidden" name="slug" value="<?php echo htmlspecialchars( $slug ); ?>" id="page-slug">
            <input type="hidden" name="content_html" value="" id="content-html-field">
            <input type="hidden" name="content_blocks" value="" id="content-blocks-field">

            <div class="klytos-page-editor">

                <!-- MAIN COLUMN — Title + Editor -->
                <div class="klytos-page-editor__main">

                    <!-- Title -->
                    <input
                        type="text"
                        name="title"
                        class="klytos-page-editor__title"
                        placeholder="<?php echo __( 'pages.page_title' ); ?>..."
                        value="<?php echo htmlspecialchars( $pageTitle ); ?>"
                        required
                    >

                    <!-- Slug (shown when editing or after first save) -->
                    <?php if ( $isEditing ): ?>
                    <div style="margin-bottom:1rem;font-size:0.85rem;color:var(--admin-text-muted);">
                        URL: <code style="background:#f1f5f9;padding:0.15rem 0.5rem;border-radius:4px;">/<?php echo htmlspecialchars( $slug ); ?>/</code>
                    </div>
                    <?php endif; ?>

                    <!-- Action buttons -->
                    <div class="klytos-page-editor__actions">
                        <button type="submit" name="status" value="draft" class="btn btn-secondary">
                            <?php echo __( 'pages.draft' ); ?>
                        </button>
                        <button type="submit" name="status" value="published" class="btn btn-primary">
                            <?php echo __( 'pages.published' ); ?>
                        </button>
                        <?php if ( $isEditing ): ?>
                        <a href="../<?php echo htmlspecialchars( $slug ); ?>/" target="_blank" class="btn btn-ghost">
                            <?php echo __( 'common.preview' ); ?> &rarr;
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Gutenberg Editor Container -->
                    <div id="klytos-editor-container"></div>

                </div>

                <!-- SIDEBAR — Page settings -->
                <div class="klytos-page-editor__sidebar">

                    <!-- Page Settings -->
                    <div class="card">
                        <h3><?php echo __( 'common.status' ); ?></h3>

                        <label><?php echo __( 'pages.template' ); ?></label>
                        <select name="template">
                            <option value="default" <?php echo $pageTemplate === 'default' ? 'selected' : ''; ?>>Default</option>
                            <option value="landing" <?php echo $pageTemplate === 'landing' ? 'selected' : ''; ?>>Landing</option>
                            <option value="blog-post" <?php echo $pageTemplate === 'blog-post' ? 'selected' : ''; ?>>Blog Post</option>
                            <option value="blank" <?php echo $pageTemplate === 'blank' ? 'selected' : ''; ?>>Blank</option>
                        </select>

                        <label><?php echo __( 'pages.language' ); ?></label>
                        <select name="lang">
                            <option value="en" <?php echo $pageLang === 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="es" <?php echo $pageLang === 'es' ? 'selected' : ''; ?>>Español</option>
                            <option value="fr" <?php echo $pageLang === 'fr' ? 'selected' : ''; ?>>Français</option>
                            <option value="de" <?php echo $pageLang === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                            <option value="pt" <?php echo $pageLang === 'pt' ? 'selected' : ''; ?>>Português</option>
                            <option value="ca" <?php echo $pageLang === 'ca' ? 'selected' : ''; ?>>Català</option>
                        </select>
                    </div>

                    <!-- SEO — Search Engines -->
                    <div class="card">
                        <h3>SEO</h3>

                        <label>Meta Description <span style="color:var(--admin-error);">*</span></label>
                        <textarea name="meta_description" rows="3" maxlength="160" style="resize:vertical;" placeholder="120-155 characters. Include keyword and call-to-action."><?php echo htmlspecialchars( $pageMetaDesc ); ?></textarea>
                        <div class="form-help" id="meta-counter" style="display:flex;justify-content:space-between;">
                            <span id="meta-count-text">0/160</span>
                            <span id="meta-quality"></span>
                        </div>

                        <label>Canonical URL</label>
                        <input type="url" name="canonical_url" value="<?php echo htmlspecialchars( $pageCanonical ); ?>" placeholder="Leave empty to use page URL (recommended)">
                        <div class="form-help">Only set if this content exists at another URL.</div>

                        <div style="margin-top:0.75rem;">
                            <label style="display:inline-flex;align-items:center;gap:0.5rem;cursor:pointer;font-weight:400;">
                                <input type="checkbox" name="noindex" value="1" <?php echo $pageNoIndex ? 'checked' : ''; ?>>
                                noindex — Hide from search engines
                            </label>
                        </div>
                    </div>

                    <!-- SEO — Social Media / Open Graph -->
                    <div class="card">
                        <h3>Facebook / LinkedIn</h3>

                        <label>OG Image <span style="color:var(--admin-warning);">recommended</span></label>
                        <input type="text" name="og_image" value="<?php echo htmlspecialchars( $pageOgImage ); ?>" placeholder="https://... (1200x630px)">
                        <div class="form-help">Preview image when shared. 1200x630px recommended.</div>

                        <label>OG Title</label>
                        <input type="text" name="og_title" value="<?php echo htmlspecialchars( $pageOgTitle ); ?>" maxlength="70" placeholder="Leave empty to use page title">

                        <label>OG Description</label>
                        <textarea name="og_description" rows="2" maxlength="200" style="resize:vertical;" placeholder="Leave empty to use meta description"><?php echo htmlspecialchars( $pageOgDesc ); ?></textarea>
                    </div>

                    <!-- SEO — Twitter / X -->
                    <details class="card">
                        <summary style="cursor:pointer;font-weight:600;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--admin-text-muted);">
                            Twitter / X
                        </summary>
                        <div style="margin-top:1rem;">
                            <label>Twitter Title</label>
                            <input type="text" name="twitter_title" value="<?php echo htmlspecialchars( $pageTwTitle ); ?>" maxlength="70" placeholder="Leave empty to use OG title">

                            <label>Twitter Description</label>
                            <textarea name="twitter_description" rows="2" maxlength="200" style="resize:vertical;" placeholder="Leave empty to use OG description"><?php echo htmlspecialchars( $pageTwDesc ); ?></textarea>
                            <div class="form-help">Twitter uses the OG image automatically. Set these only if you want different text for Twitter.</div>
                        </div>
                    </details>

                    <!-- SEO Preview -->
                    <div class="card" id="seo-preview-card">
                        <h3>Search Preview</h3>
                        <div style="font-family:Arial,sans-serif;max-width:600px;">
                            <div id="seo-preview-title" style="color:#1a0dab;font-size:1.1rem;font-weight:400;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
                            <div id="seo-preview-url" style="color:#006621;font-size:0.85rem;margin:0.15rem 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
                            <div id="seo-preview-desc" style="color:#545454;font-size:0.85rem;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;"></div>
                        </div>
                    </div>

                    <!-- Custom Code -->
                    <details class="card">
                        <summary style="cursor:pointer;font-weight:600;font-size:0.875rem;text-transform:uppercase;letter-spacing:0.05em;color:var(--admin-text-muted);">
                            <?php echo __( 'pages.custom_css' ); ?> / <?php echo __( 'pages.custom_js' ); ?>
                        </summary>
                        <div style="margin-top:1rem;">
                            <label><?php echo __( 'pages.custom_css' ); ?></label>
                            <textarea name="custom_css" rows="4" class="mono" style="resize:vertical;font-size:0.8rem;"><?php echo htmlspecialchars( $pageCustomCss ); ?></textarea>

                            <label><?php echo __( 'pages.custom_js' ); ?></label>
                            <textarea name="custom_js" rows="4" class="mono" style="resize:vertical;font-size:0.8rem;"><?php echo htmlspecialchars( $pageCustomJs ); ?></textarea>
                        </div>
                    </details>

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
        height: 500,
        menubar: 'file edit view insert format tools table',
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media | forecolor backcolor | removeformat | code fullscreen',
        content_style: 'body { font-family: Inter, sans-serif; font-size: 16px; max-width: 800px; margin: 0 auto; padding: 1rem; }',
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
<script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
<script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>

<!-- Gutenberg vendor files (NEVER modify these) -->
<link rel="stylesheet" href="assets/vendor/gutenberg/core.css">
<link rel="stylesheet" href="assets/vendor/gutenberg/isolated-block-editor.css">

<!-- Klytos editor overrides (OUR styles) -->
<link rel="stylesheet" href="assets/css/klytos-editor.css">

<!-- Gutenberg library (NEVER modify this) -->
<script src="assets/vendor/gutenberg/isolated-block-editor.js"></script>

<!-- Klytos Editor API wrapper (OUR code) -->
<script src="assets/js/klytos-editor.js"></script>

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
            document.getElementById( 'editor-status' ).textContent = '<?php echo __( 'common.success' ); ?>';
            document.getElementById( 'editor-status' ).className = 'klytos-page-editor__status';
        },
        onChange: function( html ) {
            document.getElementById( 'editor-status' ).textContent = 'Unsaved changes';
            document.getElementById( 'editor-status' ).className = 'klytos-page-editor__status dirty';
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

<?php include __DIR__ . '/templates/footer.php'; ?>
