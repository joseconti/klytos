<?php

/**
 * Klytos Admin — Page Editor (Gutenberg / TinyMCE)
 *
 * Supports two editors, selectable in Settings:
 * - Gutenberg: block editor via @automattic/isolated-block-editor.
 * - TinyMCE: classic WYSIWYG editor (self-hosted or CDN).
 *
 * @copyright 2024-2026 José Conti. All rights reserved.
 * @license   GPL-3.0-or-later
 */

$currentPage = 'pages';
require __DIR__ . '/bootstrap.php';

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
$pagePostType  = trim($_GET['post_type'] ?? 'page');

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
        $pagePostType  = $page['post_type'] ?? $pagePostType;
    }
}

// Resolve editor type from post type definition (per-post-type setting).
$editorType = 'gutenberg';
try {
    $ptDef = $app->getPostTypeManager()->get($pagePostType);
    $editorType = $ptDef['editor'] ?? 'gutenberg';
} catch (\Throwable $e) {
    // Post type not found, fallback to gutenberg.
}

// Handle POST (save).
if ( $_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf() ) {
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
        'post_type'           => $_POST['post_type'] ?? 'page',
    ];

    $saveSlug = $_POST['slug'] ?? '';

    if ( ! $saveSlug && $data['title'] ) {
        // Auto-generate slug from title.
        $saveSlug = Helpers::sanitizeSlug($data['title']);
    }

    if ( $saveSlug && $data['title'] ) {
        $data['slug'] = $saveSlug;

        if ( $isEditing ) {
            $pm->update( $saveSlug, $data );
        } else {
            /*
             * create() takes ONE argument — the data array, whose `slug` key
             * it reads itself (page-manager.php:70). This call passed the slug
             * first, so creating a page from this screen ended in an uncaught
             * TypeError and an HTTP 500 on every install that has ever had
             * one. It survived because nothing had driven the create path: the
             * suite covers PageManager directly and the screen's own POST
             * handler had no test at all, which is L-030's shape again — the
             * artifact was verified, its CONSUMER was not.
             */
            $pm->create( $data );
            $isEditing = true;
        }

        $slug = $saveSlug;
        $page = $pm->get( $slug );
        $success = true;

        // Save custom field values.
        if (!empty($_POST['cf']) && is_array($_POST['cf'])) {
            try {
                $ptDef    = $app->getPostTypeManager()->get($data['post_type'] ?? 'page');
                $cfFields = $ptDef['custom_fields'] ?? [];
                $meta     = $app->getMetaManager();

                foreach ($cfFields as $cfDef) {
                    $fid = $cfDef['id'] ?? '';
                    if ($fid === '' || !array_key_exists($fid, $_POST['cf'])) {
                        continue;
                    }
                    $rawVal = $_POST['cf'][$fid];
                    $validated = $app->getPostTypeManager()->validateFieldValue($cfDef, $rawVal);
                    $meta->set('pages', $saveSlug, 'cf.' . $fid, $validated);
                }
            } catch (\Throwable $e) {
                // Custom field save errors are non-fatal.
            }
        }

        // Save taxonomy term assignments.
        try {
            $ptDefTax     = $app->getPostTypeManager()->get($data['post_type'] ?? 'page');
            $ptTaxonomies = $ptDefTax['taxonomies'] ?? [];
            $meta         = $app->getMetaManager();

            foreach ($ptTaxonomies as $tax) {
                $taxId   = $tax['id'] ?? '';
                $postKey = 'tax_' . $taxId;
                if ($taxId === '') {
                    continue;
                }
                $assigned = $_POST[$postKey] ?? [];
                if (!is_array($assigned)) {
                    $assigned = [$assigned];
                }
                // Filter to non-empty values.
                $assigned = array_values(array_filter($assigned, fn($v) => trim((string) $v) !== ''));
                $meta->set('pages', $saveSlug, 'tax.' . $taxId, $assigned);
            }
        } catch (\Throwable $e) {
            // Taxonomy assignment errors are non-fatal.
        }

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
        $error = __( 'editor.title_required' );
    }
}

// Override CSP for the editor page: Gutenberg creates blob: iframes with
// inline scripts that cannot carry a nonce. We must allow 'unsafe-inline'
// for script-src on this page only. The nonce attribute on our own <script>
// tags is kept for defense-in-depth but is no longer the sole gate.
// This MUST be set before including header.php (which sends HTTP headers).
if ( $editorType === 'gutenberg' ) {
    // CSP frame-src: allow all oEmbed provider iframe domains.
    // CSP img-src: allow provider thumbnails/images.
    // CSP script-src: some embeds inject scripts (Twitter, Reddit, TikTok, etc.).
    $frameSrc = implode( ' ', [
        "'self'",
        'blob:',
        // YouTube
        '*.youtube.com',
        '*.youtube-nocookie.com',
        // Vimeo
        'player.vimeo.com',
        '*.vimeo.com',
        // Dailymotion
        '*.dailymotion.com',
        'geo.dailymotion.com',
        // Spotify
        'open.spotify.com',
        '*.spotify.com',
        // SoundCloud
        'w.soundcloud.com',
        '*.soundcloud.com',
        // TikTok
        '*.tiktok.com',
        // Twitter / X
        'platform.twitter.com',
        '*.twitter.com',
        '*.x.com',
        // Flickr
        '*.flickr.com',
        // SmugMug
        '*.smugmug.com',
        // Scribd
        '*.scribd.com',
        // WordPress.tv / VideoPress
        'wordpress.tv',
        '*.wordpress.tv',
        'videopress.com',
        '*.videopress.com',
        // Crowdsignal / Polldaddy
        '*.crowdsignal.net',
        '*.crowdsignal.com',
        '*.polldaddy.com',
        'poll.fm',
        'survey.fm',
        // Imgur
        '*.imgur.com',
        // Issuu
        'e.issuu.com',
        '*.issuu.com',
        // Mixcloud
        '*.mixcloud.com',
        // TED
        'embed.ted.com',
        '*.ted.com',
        // Animoto
        '*.animoto.com',
        // Tumblr
        '*.tumblr.com',
        'assets.tumblr.com',
        // Kickstarter
        '*.kickstarter.com',
        // Cloudup
        'cloudup.com',
        '*.cloudup.com',
        // ReverbNation
        '*.reverbnation.com',
        // Reddit
        '*.reddit.com',
        '*.redditmedia.com',
        // Speaker Deck
        'speakerdeck.com',
        '*.speakerdeck.com',
        // Amazon Kindle
        'read.amazon.com',
        'read.amazon.co.uk',
        'read.amazon.co.jp',
        'read.amazon.com.au',
        'read.amazon.cn',
        // Someecards
        '*.someecards.com',
        // Pinterest
        '*.pinterest.com',
        'assets.pinterest.com',
        // Wolfram Cloud
        '*.wolframcloud.com',
        // Pocket Casts
        '*.pocketcasts.com',
        'pca.st',
        // Anghami
        '*.anghami.com',
        // Bluesky
        'embed.bsky.app',
        '*.bsky.app',
        // Canva
        '*.canva.com',
    ] );

    $imgSrc = implode( ' ', [
        "'self'",
        'data:',
        '*.youtube.com',
        '*.ytimg.com',
        '*.vimeocdn.com',
        '*.spotify.com',
        '*.scdn.co',
        '*.flickr.com',
        '*.staticflickr.com',
        '*.smugmug.com',
        '*.tumblr.com',
        '*.imgur.com',
        '*.pinimg.com',
        '*.twimg.com',
        '*.redditmedia.com',
        'embed.ted.com',
        '*.kickstarter.com',
    ] );

    // $customCsp tells header.php to use this policy instead of the default one.
    $customCsp = "default-src 'self'; style-src 'self' 'unsafe-inline' fonts.googleapis.com; font-src 'self' fonts.gstatic.com; img-src {$imgSrc}; script-src 'self' 'unsafe-inline'; frame-src {$frameSrc}";
}

/*
 * ─── Stage 6 of 6, entry 2 — the chrome, to template-editor-split.md ───
 *
 * §4: "<h1> is the page's title (the record being edited), not the word
 * 'Editor'." The shell prints exactly one <h1> in main and repeats it as the
 * breadcrumb's last crumb, so this screen emits none of its own and sets no
 * $pageEmitsOwnH1. $recordTitle stays the field's value; $pageTitle is the
 * heading, which for an unsaved record is the action rather than an empty
 * string.
 *
 * The canvas INTERIOR is not built here and that is a recorded decision, not
 * an omission: the product mounts Gutenberg or TinyMCE and the delivery draws
 * the interior of software this product did not write (D-104, roadmap.md §0c).
 */
$recordTitle = $pageTitle;
$pageTitle   = $recordTitle !== '' ? $recordTitle : __( 'pages.create_page' );
$breadcrumb  = [ [ 'label' => __( 'pages.title' ), 'url' => 'pages.php' ] ];

// template-shell.md's mechanism for a screen that owns the viewport, built in
// stage 2 for this screen and the AI chat. It replaces the four !important
// overrides this page carried, which hid the sidebar, the toolbar and the
// status bar outright — the shell chrome template-editor-split.md §1 requires.
$shellFullBleed = true;

/*
 * The status buttons, resolved BEFORE the header so the toolbar can carry
 * them. template-shell.md §1: "Actions — up to two `sm` buttons, secondary
 * then primary. NEVER three. A third action belongs in the page." This
 * product has one submit per status, and a post type may define custom ones,
 * so the bound is met by placing the primary and the draft in the toolbar and
 * every remaining status in the inspector's Status section — the page. No
 * shipped control is removed (D-076's rule) and none is duplicated.
 */
$editorStatuses = $app->getPostTypeManager()->getStatusesForPostType( $pagePostType );
// Trashed and scheduled have their own mechanisms and were never offered here.
$editorStatuses = array_values( array_filter(
    $editorStatuses,
    static fn( array $s ): bool => ! in_array( $s['id'], [ 'trashed', 'scheduled' ], true )
) );

$primaryStatus   = null;
$secondaryStatus = null;
$extraStatuses   = [];
foreach ( $editorStatuses as $stDef ) {
    if ( $stDef['id'] === 'published' && $primaryStatus === null ) {
        $primaryStatus = $stDef;
    } elseif ( $stDef['id'] === 'draft' && $secondaryStatus === null ) {
        $secondaryStatus = $stDef;
    } else {
        $extraStatuses[] = $stDef;
    }
}
// A post type without a published status still gets a primary action rather
// than a toolbar with only a secondary in it.
if ( $primaryStatus === null && $extraStatuses !== [] ) {
    $primaryStatus = array_shift( $extraStatuses );
}

$editorFormId = 'k-page-editor-form';

// The four shipped page templates, and the six languages the shipped selector
// offers. The language names stay in their own language — an endonym is what a
// person picking a locale recognises, and translating "Español" into twenty
// catalogues would make the list unreadable for exactly the people it is for.
$editorTemplateKeys = [
    'default'   => 'common.default',
    'landing'   => 'editor.template_landing',
    'blog-post' => 'editor.template_blog_post',
    'blank'     => 'editor.template_blank',
];
$editorLanguageNames = [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'pt' => 'Português',
    'ca' => 'Català',
];

/*
 * §2's three autosave readings live in the toolbar's save-state slot. The
 * server renders the resting one — an existing record has a last-saved time
 * and a new one has nothing saved at all, which is why the filter returns ''
 * there and the slot is then absent rather than empty.
 */
$editorSavedAt = '';
if ( $isEditing && ! empty( $page['updated_at'] ) ) {
    $editorSavedAt = klytos_date( 'H:i', klytos_datetime_to_timestamp( (string) $page['updated_at'] ) );
}
klytos_add_filter( 'admin.topbar_center', static function ( string $html ) use ( $editorSavedAt ): string {
    /*
     * The slot is claimed even when there is nothing to say yet — a record
     * being created has never been saved — because the script writes the two
     * live readings into it and a slot that only appears after the first
     * autosave would move the toolbar's actions under the person's cursor.
     * klytos_kses_post() keeps class and id on a <span> and drops everything
     * else, which is why the test hook is on the shell's wrapper.
     */
    return $html . '<span id="k-editor-save-text">'
        . klytos_esc_html( $editorSavedAt === '' ? '' : __( 'editor.saved_at', [ 'time' => $editorSavedAt ] ) )
        . '</span>';
} );

klytos_add_filter(
    'admin.topbar_actions',
    static function ( string $html ) use ( $primaryStatus, $secondaryStatus, $editorFormId ): string {
        /*
         * §3, 900–1199 and below: the inspector becomes a sheet opened by a
         * toolbar toggle carrying aria-expanded and aria-controls. It is in
         * the DOM at every width and the media query hides it above 1199,
         * so the markup never depends on a server-side guess about the
         * viewport — stage 2's own rule for the drawer trigger.
         */
        $out = '<button type="button" class="k-btn k-btn--secondary k-btn--sm k-inspector-trigger"'
            . ' id="k-inspector-trigger" aria-expanded="false" aria-controls="k-inspector" hidden'
            . ' data-testid="editor.inspector_trigger">'
            . klytos_esc_html( __( 'editor.inspector' ) )
            . '</button>';

        foreach ( [ [ $secondaryStatus, 'secondary' ], [ $primaryStatus, 'primary' ] ] as $pair ) {
            [ $status, $variant ] = $pair;
            if ( $status === null ) {
                continue;
            }
            $out .= '<button type="submit" name="status" value="' . klytos_esc_attr( $status['id'] ) . '"'
                . ' form="' . klytos_esc_attr( $editorFormId ) . '"'
                . ' class="k-btn k-btn--' . $variant . ' k-btn--sm"'
                . ' data-testid="editor.status.' . klytos_esc_attr( $status['id'] ) . '">'
                . klytos_esc_html( $status['label'] )
                . '</button>';
        }

        return $html . $out;
    }
);

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.editor.before' ); ?>

<?php
/*
 * §2 Success — "Published — klytos.io/pricing" in the status region, with the
 * URL as a link. It is a role="status" line under the H1, which is the same
 * shape every form screen already uses (template-record-form.md §2), so the
 * announcement lands in one place on this screen too.
 */
?>
<?php if ( isset( $success ) ) : ?>
    <?php
    $editorPublicUrl = rtrim( \Klytos\Core\Helpers::getBasePath(), '/' ) . '/' . ltrim( $slug, '/' ) . '/';
    ?>
    <p class="k-status-line" role="status" data-testid="editor.status_line">
        <?php if ( $pageStatus === 'published' ) : ?>
            <?php echo klytos_esc_html( __( 'editor.published' ) ); ?>
            <a href="<?php echo klytos_esc_url( $editorPublicUrl ); ?>" target="_blank" rel="noopener">
                <?php echo klytos_esc_html( $editorPublicUrl ); ?>
            </a>
        <?php else : ?>
            <?php echo klytos_esc_html( __( 'editor.saved' ) ); ?>
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php if ( isset( $error ) ) : ?>
    <?php /*
     * The form-level error summary the whole build uses: role="alert", focus
     * moved to it on load, every failed field a link to that field
     * (template-record-form.md §2). It replaces a fixed-position banner that
     * covered the toolbar and named no field at all.
     */ ?>
    <div class="k-error-summary"
         id="k-editor-error-summary"
         role="alert"
         tabindex="-1"
         data-testid="editor.error_summary">
        <h2><?php echo klytos_esc_html( __( 'editor.summary_title' ) ); ?></h2>
        <ul>
            <li>
                <a href="#k-editor-title" data-testid="editor.error_link.0">
                    <?php echo klytos_esc_html( $error ); ?>
                </a>
            </li>
        </ul>
    </div>
<?php endif; ?>

<?php
/*
 * §2 Autosave — failed: after the SECOND consecutive failure the state
 * becomes a role="alert" panel offering Retry now and Copy the content, and
 * the buffer is never discarded. The panel is in the DOM from the start,
 * hidden, because a role="alert" inserted into the document is announced
 * inconsistently across screen readers while a hidden one that is revealed is
 * not. Its text is filled by the script with the status the server returned.
 */
?>
<div class="k-editor-alert"
     id="k-editor-autosave-alert"
     role="alert"
     hidden
     data-message="<?php echo klytos_esc_attr( __( 'editor.autosave_failed' ) ); ?>"
     data-testid="editor.autosave_alert">
    <span id="k-editor-autosave-message"></span>
    <button type="button" class="k-btn k-btn--secondary k-btn--sm" id="k-editor-autosave-retry" data-testid="editor.autosave_retry">
        <?php echo klytos_esc_html( __( 'list.retry' ) ); ?>
    </button>
    <button type="button" class="k-btn k-btn--secondary k-btn--sm" id="k-editor-autosave-copy" data-testid="editor.autosave_copy">
        <?php echo klytos_esc_html( __( 'editor.copy_content' ) ); ?>
    </button>
</div>

<?php
/*
 * §1's three columns. The left rail is absent from the DOM rather than
 * rendered empty: §1 marks it "[optional]" and its only content on this
 * screen is the block list, which is the engine's own model and is deferred
 * with the rest of the canvas interior (roadmap.md §0c, D-104). The modifier
 * removes the track, exactly as .k-record-form--no-nav does for a form screen
 * with no sections.
 */
?>
<form method="post"
      id="<?php echo klytos_esc_attr( $editorFormId ); ?>"
      class="k-editor k-editor--no-rail"
      data-testid="editor.screen">
    <?php echo klytos_csrf_field(); ?>
    <input type="hidden" name="content_html" value="" id="content-html-field">
    <input type="hidden" name="content_blocks" value="" id="content-blocks-field">
    <input type="hidden" name="post_type" value="<?php echo klytos_esc_attr( $pagePostType ); ?>">

    <?php klytos_do_action( 'editor.before_canvas', $page ?? null, $isEditing ?? false ); ?>

    <?php // §4: "The canvas is <section aria-label="Page content">". ?>
    <section class="k-editor-canvas"
             aria-label="<?php echo klytos_esc_attr( __( 'editor.canvas' ) ); ?>"
             data-testid="editor.canvas">

        <?php /*
         * §1: "a URL line in mono at the top … with the slug in
         * --color-acento and an edit affordance". §4 settles what the edit
         * affordance is: "The URL/slug line is a real form control with a
         * visible label, not an inline-editable span." It was a hidden input
         * before this stage, so the slug could not be edited at all.
         */ ?>
        <div class="k-editor-url">
            <label class="k-label" for="k-editor-slug">
                <?php echo klytos_esc_html( __( 'pages.slug' ) ); ?>
            </label>
            <span class="k-editor-url-base" aria-hidden="true"><?php
                echo klytos_esc_html( rtrim( \Klytos\Core\Helpers::getBasePath(), '/' ) . '/' );
            ?></span>
            <input type="text"
                   class="k-control k-control--mono"
                   id="k-editor-slug"
                   name="slug"
                   value="<?php echo klytos_esc_attr( $slug ); ?>"
                   data-testid="editor.slug">
        </div>

        <?php /*
         * The engine. Everything the delivery draws INSIDE this node — the
         * blocks as bordered cards, their role="group" names, their
         * contenteditable regions, the "Edit as form" fallback page per block
         * and the two hard publish blockers — is Gutenberg's or TinyMCE's own
         * DOM or product that exists nowhere, and is deferred with its reason
         * in roadmap.md §0c (D-104). Not an omission: a recorded decision.
         */ ?>
        <div id="klytos-editor-container"></div>

        <?php
        // ─── Custom fields ───────────────────────────────────────────
        // They stay in the canvas rather than moving into the inspector, and
        // the reason is measurable: §1 makes inspector rows "label/control
        // pairs at 30px" in a 300px column, and this product's custom fields
        // are 27 typed controls including repeaters and rich text. The
        // delivery draws none of them anywhere. Logged as an adaptation.
        $cfDefs   = [];
        $cfValues = [];
        try {
            $cfDefs = $app->getPostTypeManager()->listCustomFields( $pagePostType );
        } catch ( \Throwable $e ) {
            // Post type may not have custom fields.
        }
        if ( ! empty( $cfDefs ) && $isEditing && $slug ) {
            $cfMeta = $app->getMetaManager()->getAll( 'pages', $slug );
            foreach ( $cfMeta as $mk => $mv ) {
                if ( str_starts_with( $mk, 'cf.' ) ) {
                    $cfValues[ substr( $mk, 3 ) ] = $mv;
                }
            }
        }
        if ( ! empty( $cfDefs ) ) :
            require_once __DIR__ . '/includes/custom-field-renderer.php';
            ?>
            <?php klytos_do_action( 'editor.before_custom_fields', $page ?? null, $isEditing ?? false ); ?>
            <section class="k-card k-card--padded" aria-labelledby="k-editor-cf-heading">
                <div class="k-card-body">
                    <h2 class="k-card-heading" id="k-editor-cf-heading">
                        <?php echo klytos_esc_html( __( 'editor.custom_fields' ) ); ?>
                    </h2>
                    <div class="k-field-grid">
                        <?php foreach ( $cfDefs as $cfDef ) : ?>
                            <?php echo renderCustomField( $cfDef, $cfValues[ $cfDef['id'] ] ?? null ); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>
        <?php klytos_do_action( 'editor.after_custom_fields', $page ?? null, $isEditing ?? false ); ?>
    </section>
    <?php klytos_do_action( 'editor.after_canvas', $page ?? null, $isEditing ?? false ); ?>

    <?php /*
     * §1 Inspector, and §2's "Empty — no selection in the inspector — never
     * blank: document properties, as above". The tab pair this screen carried
     * ("Page" / "Bloque") is gone with it: its second tab was a permanent
     * "Select a block to see its settings", which is precisely the blank
     * panel §2 forbids, and block properties are the engine's own inspector.
     */ ?>
    <aside class="k-inspector"
           id="k-inspector"
           aria-label="<?php echo klytos_esc_attr( __( 'editor.inspector' ) ); ?>"
           data-testid="editor.inspector">

        <?php
        /**
         * One inspector section: an <h3> whose button carries aria-expanded
         * and controls its panel (§4). Written once and called per section so
         * the disclosure contract cannot drift between them.
         *
         * @param string $id    Panel id, unique on the page.
         * @param string $label Visible section name, already translated.
         */
        $editorSection = static function ( string $id, string $label ): void {
            ?>
            <h3 class="k-inspector-heading">
                <button type="button"
                        class="k-inspector-toggle"
                        aria-expanded="true"
                        aria-controls="<?php echo klytos_esc_attr( $id ); ?>"
                        data-testid="editor.section.<?php echo klytos_esc_attr( $id ); ?>">
                    <span><?php echo klytos_esc_html( $label ); ?></span>
                    <span class="k-inspector-chevron" aria-hidden="true"></span>
                </button>
            </h3>
            <?php
        };
        ?>

        <?php // ─── Document ─────────────────────────────────────────── ?>
        <div class="k-inspector-section">
            <?php $editorSection( 'k-insp-document', __( 'editor.section_document' ) ); ?>
            <div class="k-inspector-panel" id="k-insp-document">

                <div class="k-field">
                    <label class="k-label" for="k-editor-title"><?php echo klytos_esc_html( __( 'pages.page_title' ) ); ?></label>
                    <input type="text"
                           class="k-control"
                           id="k-editor-title"
                           name="title"
                           value="<?php echo klytos_esc_attr( $recordTitle ); ?>"
                           required
                           data-testid="editor.title">
                </div>

                <div class="k-field">
                    <label class="k-label" for="k-editor-template"><?php echo klytos_esc_html( __( 'pages.template' ) ); ?></label>
                    <select class="k-control" id="k-editor-template" name="template" data-testid="editor.template">
                        <?php foreach ( $editorTemplateKeys as $tpl => $tplKey ) : ?>
                            <option value="<?php echo klytos_esc_attr( $tpl ); ?>" <?php echo $pageTemplate === $tpl ? 'selected' : ''; ?>>
                                <?php echo klytos_esc_html( __( $tplKey ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="k-field">
                    <label class="k-label" for="k-editor-lang"><?php echo klytos_esc_html( __( 'pages.language' ) ); ?></label>
                    <select class="k-control" id="k-editor-lang" name="lang" data-testid="editor.lang">
                        <?php foreach ( $editorLanguageNames as $langCode => $langName ) : ?>
                            <option value="<?php echo klytos_esc_attr( $langCode ); ?>" <?php echo $pageLang === $langCode ? 'selected' : ''; ?>>
                                <?php echo klytos_esc_html( $langName ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ( $extraStatuses !== [] ) : ?>
                    <?php /*
                     * template-shell.md §1's "a third action belongs in the
                     * page", honoured literally: the toolbar carries the
                     * primary and the draft, and every remaining status of
                     * this post type submits from here. Nothing shipped is
                     * removed and nothing is duplicated.
                     */ ?>
                    <div class="k-field">
                        <span class="k-label" id="k-editor-more-statuses"><?php echo klytos_esc_html( __( 'editor.more_statuses' ) ); ?></span>
                        <div class="k-collection-actions" role="group" aria-labelledby="k-editor-more-statuses">
                            <?php foreach ( $extraStatuses as $stDef ) : ?>
                                <button type="submit"
                                        name="status"
                                        value="<?php echo klytos_esc_attr( $stDef['id'] ); ?>"
                                        class="k-btn k-btn--secondary k-btn--sm"
                                        data-testid="editor.status.<?php echo klytos_esc_attr( $stDef['id'] ); ?>">
                                    <?php echo klytos_esc_html( $stDef['label'] ); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( $isEditing ) : ?>
                    <p class="k-hint">
                        <a href="<?php echo klytos_esc_url( '../' . $slug . '/' ); ?>" target="_blank" rel="noopener" data-testid="editor.preview">
                            <?php echo klytos_esc_html( __( 'common.preview' ) ); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php klytos_do_action( 'editor.sidebar.before_seo', $page ?? null, $isEditing ?? false ); ?>

        <?php // ─── Search engine ────────────────────────────────────── ?>
        <div class="k-inspector-section">
            <?php $editorSection( 'k-insp-seo', __( 'editor.section_seo' ) ); ?>
            <div class="k-inspector-panel" id="k-insp-seo">

                <div class="k-field">
                    <label class="k-label" for="k-editor-meta"><?php echo klytos_esc_html( __( 'pages.meta_description' ) ); ?></label>
                    <textarea class="k-control"
                              id="k-editor-meta"
                              name="meta_description"
                              rows="3"
                              maxlength="160"
                              aria-describedby="meta-counter"
                              data-testid="editor.meta_description"><?php echo klytos_esc_textarea( $pageMetaDesc ); ?></textarea>
                    <p class="k-hint" id="meta-counter">
                        <span id="meta-count-text">0/160</span>
                        <span id="meta-quality"></span>
                    </p>
                </div>

                <div class="k-field">
                    <label class="k-label" for="k-editor-canonical"><?php echo klytos_esc_html( __( 'editor.canonical' ) ); ?></label>
                    <input type="url"
                           class="k-control"
                           id="k-editor-canonical"
                           name="canonical_url"
                           value="<?php echo klytos_esc_attr( $pageCanonical ); ?>"
                           data-testid="editor.canonical">
                </div>

                <label class="k-choice k-hit-24" for="k-editor-noindex">
                    <input type="checkbox"
                           class="k-check"
                           id="k-editor-noindex"
                           name="noindex"
                           value="1"
                           <?php echo $pageNoIndex ? 'checked' : ''; ?>
                           data-testid="editor.noindex">
                    <span><?php echo klytos_esc_html( __( 'editor.noindex' ) ); ?></span>
                </label>
            </div>
        </div>

        <?php // ─── Search preview ───────────────────────────────────── ?>
        <div class="k-inspector-section">
            <?php $editorSection( 'k-insp-preview', __( 'editor.section_preview' ) ); ?>
            <div class="k-inspector-panel" id="k-insp-preview">
                <div class="klytos-seo-preview">
                    <div id="seo-preview-title" class="klytos-seo-preview__title"></div>
                    <div id="seo-preview-url" class="klytos-seo-preview__url"></div>
                    <div id="seo-preview-desc" class="klytos-seo-preview__desc"></div>
                </div>
            </div>
        </div>
        <?php klytos_do_action( 'editor.sidebar.after_seo', $page ?? null, $isEditing ?? false ); ?>

        <?php // ─── Social ───────────────────────────────────────────── ?>
        <div class="k-inspector-section">
            <?php $editorSection( 'k-insp-social', __( 'editor.section_social' ) ); ?>
            <div class="k-inspector-panel" id="k-insp-social">
                <div class="k-field">
                    <label class="k-label" for="k-editor-og-image"><?php echo klytos_esc_html( __( 'pages.og_image' ) ); ?></label>
                    <input type="text"
                           class="k-control"
                           id="k-editor-og-image"
                           name="og_image"
                           value="<?php echo klytos_esc_attr( $pageOgImage ); ?>"
                           aria-describedby="k-editor-og-image-hint"
                           data-testid="editor.og_image">
                    <p class="k-hint" id="k-editor-og-image-hint"><?php echo klytos_esc_html( __( 'editor.og_image_hint' ) ); ?></p>
                </div>

                <div class="k-field">
                    <label class="k-label" for="k-editor-og-title"><?php echo klytos_esc_html( __( 'pages.page_title' ) ); ?></label>
                    <input type="text"
                           class="k-control"
                           id="k-editor-og-title"
                           name="og_title"
                           maxlength="70"
                           value="<?php echo klytos_esc_attr( $pageOgTitle ); ?>"
                           aria-describedby="k-editor-og-title-hint"
                           data-testid="editor.og_title">
                    <p class="k-hint" id="k-editor-og-title-hint"><?php echo klytos_esc_html( __( 'editor.inherits_title' ) ); ?></p>
                </div>

                <div class="k-field">
                    <label class="k-label" for="k-editor-og-desc"><?php echo klytos_esc_html( __( 'common.description' ) ); ?></label>
                    <textarea class="k-control"
                              id="k-editor-og-desc"
                              name="og_description"
                              rows="2"
                              maxlength="200"
                              aria-describedby="k-editor-og-desc-hint"
                              data-testid="editor.og_description"><?php echo klytos_esc_textarea( $pageOgDesc ); ?></textarea>
                    <p class="k-hint" id="k-editor-og-desc-hint"><?php echo klytos_esc_html( __( 'editor.inherits_description' ) ); ?></p>
                </div>
            </div>
        </div>

        <?php // ─── Twitter / X ──────────────────────────────────────── ?>
        <div class="k-inspector-section">
            <?php $editorSection( 'k-insp-twitter', __( 'editor.section_twitter' ) ); ?>
            <div class="k-inspector-panel" id="k-insp-twitter">
                <div class="k-field">
                    <label class="k-label" for="k-editor-tw-title"><?php echo klytos_esc_html( __( 'pages.page_title' ) ); ?></label>
                    <input type="text"
                           class="k-control"
                           id="k-editor-tw-title"
                           name="twitter_title"
                           maxlength="70"
                           value="<?php echo klytos_esc_attr( $pageTwTitle ); ?>"
                           data-testid="editor.twitter_title">
                </div>
                <div class="k-field">
                    <label class="k-label" for="k-editor-tw-desc"><?php echo klytos_esc_html( __( 'common.description' ) ); ?></label>
                    <textarea class="k-control"
                              id="k-editor-tw-desc"
                              name="twitter_description"
                              rows="2"
                              maxlength="200"
                              data-testid="editor.twitter_description"><?php echo klytos_esc_textarea( $pageTwDesc ); ?></textarea>
                </div>
            </div>
        </div>

        <?php // ─── Custom code ──────────────────────────────────────── ?>
        <div class="k-inspector-section">
            <?php $editorSection( 'k-insp-code', __( 'editor.section_code' ) ); ?>
            <div class="k-inspector-panel" id="k-insp-code">
                <div class="k-field">
                    <label class="k-label" for="k-editor-css"><?php echo klytos_esc_html( __( 'pages.custom_css' ) ); ?></label>
                    <textarea class="k-control k-control--mono"
                              id="k-editor-css"
                              name="custom_css"
                              rows="4"
                              data-testid="editor.custom_css"><?php echo klytos_esc_textarea( $pageCustomCss ); ?></textarea>
                </div>
                <div class="k-field">
                    <label class="k-label" for="k-editor-js"><?php echo klytos_esc_html( __( 'pages.custom_js' ) ); ?></label>
                    <textarea class="k-control k-control--mono"
                              id="k-editor-js"
                              name="custom_js"
                              rows="4"
                              data-testid="editor.custom_js"><?php echo klytos_esc_textarea( $pageCustomJs ); ?></textarea>
                </div>
            </div>
        </div>

        <?php
        // ─── Taxonomies ──────────────────────────────────────────────
        $ptDef        = null;
        $ptTaxonomies = [];
        $taxAssigned  = [];
        try {
            $ptDef        = $app->getPostTypeManager()->get( $pagePostType );
            $ptTaxonomies = $ptDef['taxonomies'] ?? [];
        } catch ( \Throwable $e ) {
            // Post type not found.
        }
        if ( ! empty( $ptTaxonomies ) && $isEditing && $slug ) {
            $allMetaSidebar = $app->getMetaManager()->getAll( 'pages', $slug );
            foreach ( $allMetaSidebar as $mk => $mv ) {
                if ( str_starts_with( $mk, 'tax.' ) ) {
                    $taxAssigned[ substr( $mk, 4 ) ] = is_array( $mv ) ? $mv : [ $mv ];
                }
            }
        }
        foreach ( $ptTaxonomies as $taxonomy ) :
            $taxId          = $taxonomy['id'] ?? '';
            $taxName        = $taxonomy['name'] ?? ucfirst( $taxId );
            $isHierarchical = $taxonomy['hierarchical'] ?? false;
            $currentTerms   = $taxAssigned[ $taxId ] ?? [];
            $availableTerms = [];
            try {
                $availableTerms = $app->getPostTypeManager()->listTerms( $pagePostType, $taxId );
            } catch ( \Throwable $e ) {
                // No terms.
            }
            $taxPanelId = 'k-insp-tax-' . preg_replace( '/[^a-z0-9_-]/i', '', (string) $taxId );
            ?>
            <div class="k-inspector-section">
                <?php $editorSection( $taxPanelId, (string) $taxName ); ?>
                <div class="k-inspector-panel" id="<?php echo klytos_esc_attr( $taxPanelId ); ?>">
                    <?php if ( ! empty( $availableTerms ) ) : ?>
                        <?php if ( $isHierarchical ) : ?>
                            <fieldset class="k-fieldset">
                                <legend class="k-legend"><?php echo klytos_esc_html( (string) $taxName ); ?></legend>
                                <?php foreach ( $availableTerms as $term ) : ?>
                                    <?php $termId = $taxPanelId . '-' . preg_replace( '/[^a-z0-9_-]/i', '', (string) $term['slug'] ); ?>
                                    <label class="k-choice k-hit-24" for="<?php echo klytos_esc_attr( $termId ); ?>">
                                        <input type="checkbox"
                                               class="k-check"
                                               id="<?php echo klytos_esc_attr( $termId ); ?>"
                                               name="tax_<?php echo klytos_esc_attr( $taxId ); ?>[]"
                                               value="<?php echo klytos_esc_attr( $term['slug'] ); ?>"
                                               <?php echo in_array( $term['slug'], $currentTerms, true ) ? 'checked' : ''; ?>>
                                        <span><?php echo klytos_esc_html( $term['name'] ?? $term['slug'] ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php else : ?>
                            <div class="k-field">
                                <label class="k-label" for="<?php echo klytos_esc_attr( $taxPanelId ); ?>-select">
                                    <?php echo klytos_esc_html( (string) $taxName ); ?>
                                </label>
                                <select class="k-control"
                                        id="<?php echo klytos_esc_attr( $taxPanelId ); ?>-select"
                                        name="tax_<?php echo klytos_esc_attr( $taxId ); ?>[]"
                                        multiple
                                        size="<?php echo (int) min( count( $availableTerms ), 6 ); ?>"
                                        aria-describedby="<?php echo klytos_esc_attr( $taxPanelId ); ?>-hint">
                                    <?php foreach ( $availableTerms as $term ) : ?>
                                        <option value="<?php echo klytos_esc_attr( $term['slug'] ); ?>"
                                            <?php echo in_array( $term['slug'], $currentTerms, true ) ? 'selected' : ''; ?>>
                                            <?php echo klytos_esc_html( $term['name'] ?? $term['slug'] ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="k-hint" id="<?php echo klytos_esc_attr( $taxPanelId ); ?>-hint">
                                    <?php echo klytos_esc_html( __( 'editor.multi_select_hint' ) ); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php else : ?>
                        <p class="k-hint">
                            <?php echo klytos_esc_html( __( 'editor.no_terms' ) ); ?>
                            <a href="taxonomy.php?post_type=<?php echo urlencode( $pagePostType ); ?>&amp;taxonomy=<?php echo urlencode( (string) $taxId ); ?>">
                                <?php echo klytos_esc_html( __( 'editor.add_terms' ) ); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php klytos_do_action( 'editor.sidebar.after_panels', $page ?? null, $isEditing ?? false ); ?>
    </aside>
</form>


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
                if ( window.KlytosPageEditor ) { window.KlytosPageEditor.dirty(); }
            } );
        }
    } );

    document.getElementById( 'k-page-editor-form' ).addEventListener( 'submit', function() {
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

<!-- Intercept fetch() so Gutenberg embed blocks use our oEmbed proxy -->
<script nonce="<?php echo $cspNonce; ?>">
( function() {
    'use strict';

    var oembedBase = <?php echo json_encode( rtrim( \Klytos\Core\Helpers::getBasePath(), '/' ) . '/admin/api/oembed.php' ); ?>;
    var originalFetch = window.fetch;

    /**
     * Override window.fetch to intercept requests to /oembed/1.0/proxy.
     * The isolated-block-editor's internal apiFetch ultimately calls
     * window.fetch, so this is the reliable interception point.
     */
    window.fetch = function( input, init ) {
        var url = ( typeof input === 'string' ) ? input : ( input && input.url ? input.url : '' );

        // Intercept oEmbed proxy requests → route to Klytos proxy.
        if ( url.indexOf( '/oembed/1.0/proxy' ) !== -1 ) {
            var match = url.match( /[?&]url=([^&]+)/ );
            if ( match ) {
                var embedUrl = decodeURIComponent( match[1] );
                var proxyUrl = oembedBase + '?url=' + encodeURIComponent( embedUrl );
                return originalFetch.call( window, proxyUrl, { credentials: 'same-origin' } );
            }
        }

        // Intercept WP REST API calls that don't exist in Klytos.
        // Gutenberg tries to call these on init — return empty/stub responses.
        if ( url.indexOf( '/wp/v2/' ) !== -1 || url.indexOf( '/wp-json/' ) !== -1 ) {
            var stubResponse = null;

            if ( url.indexOf( '/wp/v2/settings' ) !== -1 ) {
                stubResponse = {};
            } else if ( url.indexOf( '/wp/v2/themes' ) !== -1 ) {
                stubResponse = [];
            } else if ( url.indexOf( '/wp/v2/types' ) !== -1 ) {
                stubResponse = {};
            } else if ( url.indexOf( '/wp/v2/taxonomies' ) !== -1 ) {
                stubResponse = {};
            } else if ( url.indexOf( '/wp/v2/users/me' ) !== -1 ) {
                stubResponse = { id: 1, name: 'admin', slug: 'admin', roles: [ 'administrator' ] };
            } else if ( url.indexOf( '/wp/v2/users' ) !== -1 ) {
                stubResponse = [];
            } else if ( url.indexOf( '/wp/v2/media' ) !== -1 ) {
                stubResponse = [];
            } else if ( url.indexOf( '/wp/v2/blocks' ) !== -1 ) {
                stubResponse = [];
            } else if ( url.indexOf( '/wp/v2/block-patterns' ) !== -1 ) {
                stubResponse = [];
            } else if ( url.indexOf( '/wp/v2/comments' ) !== -1 ) {
                stubResponse = [];
            }

            if ( stubResponse !== null ) {
                return Promise.resolve( new Response(
                    JSON.stringify( stubResponse ),
                    { status: 200, headers: { 'Content-Type': 'application/json' } }
                ) );
            }
        }

        return originalFetch.apply( window, arguments );
    };

} )();
</script>

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

        // template-editor-split.md §2's three autosave readings. The engine
        // reports; KlytosPageEditor owns what the toolbar says, so the two
        // editors cannot drift into two different vocabularies.
        onSave: function() {
            if ( window.KlytosPageEditor ) { window.KlytosPageEditor.saved(); }
        },
        onChange: function() {
            if ( window.KlytosPageEditor ) { window.KlytosPageEditor.dirty(); }
        },
        onError: function( err ) {
            if ( window.KlytosPageEditor ) { window.KlytosPageEditor.failed( err ); }
        }
    } );

    // Before form submit, inject content into hidden fields.
    document.getElementById( 'k-page-editor-form' ).addEventListener( 'submit', function() {
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
    var slugField    = document.getElementById( 'k-editor-slug' );
    var countText    = document.getElementById( 'meta-count-text' );
    var qualityBadge = document.getElementById( 'meta-quality' );
    var previewTitle = document.getElementById( 'seo-preview-title' );
    var previewUrl   = document.getElementById( 'seo-preview-url' );
    var previewDesc  = document.getElementById( 'seo-preview-desc' );
    var siteUrl      = <?php echo json_encode( rtrim( \Klytos\Core\Helpers::publicUrl(), '/' ) ); ?>;
    // These four verdicts and the two placeholders are on the screen, so they
    // are catalogue keys like everything else on it. They were English
    // literals before this stage.
    var strings      = <?php echo json_encode( [
        'missing'     => __( 'editor.meta_missing' ),
        'short'       => __( 'editor.meta_short' ),
        'good'        => __( 'editor.meta_good' ),
        'long'        => __( 'editor.meta_long' ),
        'titleHolder' => __( 'pages.page_title' ),
        'descHolder'  => __( 'editor.preview_desc_placeholder' ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
    var siteName     = <?php echo json_encode( $app->getSiteConfig()->getValue( 'site_name', 'Klytos' ) ); ?>;

    function updateSeoPreview() {
        var title = titleField ? titleField.value : '';
        var desc  = metaField ? metaField.value : '';
        var slug  = slugField ? slugField.value : '';
        var len   = desc.length;

        // Counter.
        if ( countText ) {
            countText.textContent = len + '/160';
            // The redesign removed the --admin-* tokens these three lines
            // named, so the verdict painted as an unresolved variable — real
            // text below AA on --fondo-elevado. Colour was never the only cue
            // here (the words say it), so it is dropped rather than re-tinted.
            countText.style.color = '';
        }

        // Quality badge.
        if ( qualityBadge ) {
            if ( len === 0 ) {
                qualityBadge.textContent = strings.missing;
            } else if ( len < 80 ) {
                qualityBadge.textContent = strings.short;
            } else if ( len <= 155 ) {
                qualityBadge.textContent = strings.good;
            } else {
                qualityBadge.textContent = strings.long;
            }
        }

        // Google SERP Preview.
        if ( previewTitle ) {
            var fullTitle = title;
            if ( siteName && fullTitle.toLowerCase().indexOf( siteName.toLowerCase() ) === -1 ) {
                fullTitle += ' — ' + siteName;
            }
            previewTitle.textContent = fullTitle || strings.titleHolder;
        }

        if ( previewUrl ) {
            var displayUrl = siteUrl + '/' + ( slug || 'page-slug' ) + '/';
            previewUrl.textContent = displayUrl;
        }

        if ( previewDesc ) {
            previewDesc.textContent = desc || strings.descHolder;
            previewDesc.style.fontStyle = desc ? 'normal' : 'italic';
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

<?php /*
 * The tab pair this block drove ("Page" / "Bloque") is gone with stage 6: its
 * second tab was a permanent "Select a block to see its settings", which is
 * the blank inspector template-editor-split.md §2 forbids, and block
 * properties are the ENGINE's own inspector (roadmap.md §0c, D-104). The
 * script also read #klytos-sidebar before its own null guard, so with the
 * node removed it would have thrown on every load.
 *
 * What replaces it is the template's own chrome behaviour — the three
 * autosave readings in the toolbar and the inspector's disclosure and sheet
 * modes — in a file of its own rather than inline, because it is the same
 * behaviour for every editor-split screen.
 */ ?>
<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
    window.KLYTOS_EDITOR_STRINGS = <?php echo json_encode( [
        'saving'    => __( 'editor.saving' ),
        'savedAt'   => __( 'editor.saved_at', [ 'time' => '{time}' ] ),
        'notSaved'  => __( 'editor.not_saved' ),
        'failed'    => __( 'editor.autosave_failed', [ 'status' => '{status}' ] ),
        'unsaved'   => __( 'editor.unsaved' ),
        'copied'    => __( 'common.copied' ),
        'inspector' => __( 'editor.inspector' ),
        'close'     => __( 'common.close' ),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>;
</script>
<script src="assets/js/klytos-page-editor.js" nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>"></script>

<?php klytos_do_action( 'admin.editor.after' ); ?>
<?php include __DIR__ . '/templates/footer.php'; ?>
