<?php

/**
 * Klytos — Notice Manager
 * Centralized API for admin notices (transient and persistent).
 *
 * Notices are text-only messages displayed at the top of admin pages.
 * They come in two flavours:
 *
 * **Transient** — In-memory + session flash. Shown once, then gone.
 *   Created via klytos_add_notice() or $noticeManager->addTransient().
 *
 * **Persistent** — Stored in the 'notices' collection via StorageInterface.
 *   Survive across requests. Can be dismissed via AJAX (per-user, session-based)
 *   or removed programmatically. Support a `condition_hook` filter that controls
 *   whether the notice renders — the key advantage over WordPress admin_notices.
 *
 * Every notice enforces text-only content (strip_tags), has a type that maps to
 * existing .alert-{type} CSS classes, and a dismissible boolean flag.
 *
 * @package Klytos
 * @since   2.1.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

namespace Klytos\Core;

class NoticeManager
{
    /** @var StorageInterface Storage backend. */
    private StorageInterface $storage;

    /** @var string Collection name for persistent notices. */
    private const COLLECTION = 'notices';

    /** @var array Valid notice types (map to .alert-{type} CSS classes). */
    private const VALID_TYPES = ['success', 'error', 'warning', 'info'];

    /** @var string Session key for flash notices that survive a redirect. */
    private const SESSION_KEY = 'klytos_flash_notices';

    /** @var string Session key for dismissed notice IDs. */
    private const DISMISSED_KEY = 'klytos_dismissed_notices';

    /** @var array In-memory transient notices for the current request. */
    private array $transient = [];

    /** @var bool Whether the dismiss JS has been enqueued for this request. */
    private bool $jsEnqueued = false;

    /**
     * @param StorageInterface $storage Storage backend instance.
     */
    public function __construct( StorageInterface $storage )
    {
        $this->storage = $storage;
    }

    // ─── Transient Notices ──────────────────────────────────────

    /**
     * Add a transient (flash) notice.
     *
     * Transient notices are shown once on the current page load.
     * They also survive a single POST-redirect-GET cycle via the session.
     *
     * @param string $message     Plain text message (HTML stripped).
     * @param string $type        Notice type: success, error, warning, info.
     * @param bool   $dismissible Whether the notice can be closed by the user.
     */
    public function addTransient( string $message, string $type = 'info', bool $dismissible = true ): void
    {
        $type = in_array( $type, self::VALID_TYPES, true ) ? $type : 'info';

        $notice = [
            'id'          => 'nt_' . Helpers::randomHex( 8 ),
            'message'     => strip_tags( trim( $message ) ),
            'type'        => $type,
            'dismissible' => $dismissible,
            'persistent'  => false,
        ];

        $notice = klytos_apply_filters( 'notice.transient.add', $notice );

        $this->transient[] = $notice;

        // Also store in session so the notice survives a redirect.
        if ( session_status() === PHP_SESSION_ACTIVE ) {
            $flash   = $_SESSION[self::SESSION_KEY] ?? [];
            $flash[] = $notice;
            $_SESSION[self::SESSION_KEY] = $flash;
        }
    }

    // ─── Persistent Notices ─────────────────────────────────────

    /**
     * Create a persistent notice (stored in the notices collection).
     *
     * Persistent notices survive across page loads and sessions.
     * They can optionally declare a condition_hook filter — when
     * the filter returns false, the notice is silently skipped.
     *
     * @param  array $data Notice data:
     *   - id             (string, required) Unique identifier.
     *   - message        (string, required) Plain text.
     *   - type           (string) success|error|warning|info. Default: info.
     *   - dismissible    (bool)   Default: true.
     *   - context        (string) Page filter — only show on this admin page (e.g. 'index', 'settings'). Empty = all pages.
     *   - condition_hook (string) Filter hook name. If set, notice only renders when the filter returns true.
     * @return array The created notice record.
     * @throws \InvalidArgumentException On validation failure.
     */
    public function create( array $data ): array
    {
        $id      = trim( $data['id'] ?? '' );
        $message = trim( $data['message'] ?? '' );

        if ( empty( $id ) ) {
            throw new \InvalidArgumentException( 'Notice id is required.' );
        }
        if ( empty( $message ) ) {
            throw new \InvalidArgumentException( 'Notice message is required.' );
        }

        $type = $data['type'] ?? 'info';
        if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
            $type = 'info';
        }

        $notice = [
            'id'             => Helpers::sanitizeSlug( $id ),
            'message'        => strip_tags( $message ),
            'type'           => $type,
            'dismissible'    => (bool) ( $data['dismissible'] ?? true ),
            'context'        => trim( $data['context'] ?? '' ),
            'condition_hook' => trim( $data['condition_hook'] ?? '' ),
            'ads'            => (bool) ( $data['ads'] ?? true ),
            'persistent'     => true,
            'created_at'     => Helpers::now(),
            'updated_at'     => Helpers::now(),
        ];

        $this->storage->write( self::COLLECTION, $notice['id'], $notice );

        klytos_do_action( 'notice.created', $notice );

        return $notice;
    }

    /**
     * Create or update a system notice (idempotent).
     *
     * If a notice with the given ID already exists, its message and settings
     * are updated. If it does not exist, it is created. This is the preferred
     * method for core/system notices that must always be present.
     *
     * @param  string $id   Unique notice identifier.
     * @param  array  $data Notice data (same fields as create()).
     * @return array  The notice record.
     */
    public function ensureSystemNotice( string $id, array $data ): array
    {
        $data['id'] = $id;

        try {
            $existing = $this->storage->read( self::COLLECTION, Helpers::sanitizeSlug( $id ) );
            // Update message and settings but preserve created_at.
            $existing['message']        = strip_tags( trim( $data['message'] ?? $existing['message'] ) );
            $existing['type']           = $data['type'] ?? $existing['type'];
            $existing['dismissible']    = (bool) ( $data['dismissible'] ?? $existing['dismissible'] );
            $existing['context']        = trim( $data['context'] ?? $existing['context'] ?? '' );
            $existing['condition_hook'] = trim( $data['condition_hook'] ?? $existing['condition_hook'] ?? '' );
            $existing['ads']            = (bool) ( $data['ads'] ?? $existing['ads'] ?? true );
            $existing['updated_at']     = Helpers::now();

            $this->storage->write( self::COLLECTION, $existing['id'], $existing );

            return $existing;
        } catch ( \Throwable $e ) {
            // Does not exist yet — create it.
            return $this->create( $data );
        }
    }

    /**
     * Get a persistent notice by ID.
     *
     * @param  string $id Notice ID.
     * @return array  Notice data.
     * @throws \RuntimeException If not found.
     */
    public function get( string $id ): array
    {
        return $this->storage->read( self::COLLECTION, $id );
    }

    /**
     * Dismiss a persistent dismissible notice for the current user session.
     *
     * Dismissed state is stored in the session. The notice remains in storage
     * so it can reappear for other users or after session expiry.
     *
     * @param string $id Notice ID to dismiss.
     */
    public function dismiss( string $id ): void
    {
        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            return;
        }

        $dismissed   = $_SESSION[self::DISMISSED_KEY] ?? [];
        $dismissed[] = $id;
        $_SESSION[self::DISMISSED_KEY] = array_unique( $dismissed );

        klytos_do_action( 'notice.dismissed', $id );
    }

    /**
     * Delete a persistent notice from storage entirely.
     *
     * @param  string $id Notice ID.
     * @return bool   True if deleted.
     */
    public function delete( string $id ): bool
    {
        $result = $this->storage->delete( self::COLLECTION, $id );

        if ( $result ) {
            klytos_do_action( 'notice.deleted', $id );
        }

        return $result;
    }

    /**
     * List all persistent notices.
     *
     * @return array Array of notice records.
     */
    public function list(): array
    {
        return $this->storage->list( self::COLLECTION );
    }

    // ─── Rendering ──────────────────────────────────────────────

    /**
     * Get all notices that should be rendered on the current page.
     *
     * Collects transient (in-memory + session flash) and persistent notices,
     * filtering by page context, condition hooks, and dismiss state.
     *
     * @param  string $currentPage Current admin page slug (e.g. 'index', 'settings').
     * @return array  Array of notice records ready to render.
     */
    public function getRenderable( string $currentPage = '' ): array
    {
        $notices = [];

        // 1. In-memory transient notices.
        foreach ( $this->transient as $notice ) {
            $notices[] = $notice;
        }

        // 2. Session flash notices (from previous request's redirect).
        if ( session_status() === PHP_SESSION_ACTIVE && ! empty( $_SESSION[self::SESSION_KEY] ) ) {
            foreach ( $_SESSION[self::SESSION_KEY] as $flash ) {
                // Avoid duplicates — transient notices are also in session.
                $isDuplicate = false;
                foreach ( $this->transient as $t ) {
                    if ( $t['id'] === $flash['id'] ) {
                        $isDuplicate = true;
                        break;
                    }
                }
                if ( ! $isDuplicate ) {
                    $notices[] = $flash;
                }
            }
            // Consume flash notices — they are shown only once.
            unset( $_SESSION[self::SESSION_KEY] );
        }

        // 3. Persistent notices from storage.
        $dismissed = ( session_status() === PHP_SESSION_ACTIVE )
            ? ( $_SESSION[self::DISMISSED_KEY] ?? [] )
            : [];

        $persistent = $this->storage->list( self::COLLECTION );
        foreach ( $persistent as $notice ) {
            // Skip if dismissed by this user session.
            if ( $notice['dismissible'] && in_array( $notice['id'], $dismissed, true ) ) {
                continue;
            }

            // Skip if page context does not match.
            if ( ! empty( $notice['context'] ) && $notice['context'] !== $currentPage ) {
                continue;
            }

            // Skip if condition hook returns false.
            if ( ! empty( $notice['condition_hook'] ) ) {
                $shouldShow = klytos_apply_filters( $notice['condition_hook'], true );
                if ( ! $shouldShow ) {
                    continue;
                }
            }

            $notices[] = $notice;
        }

        // 4. Filter out advertising notices if disabled in site config.
        $showAds = App::getInstance()->getSiteConfig()->getValue( 'notices.show_ads', true );
        if ( ! $showAds ) {
            $notices = array_values( array_filter( $notices, function ( array $n ): bool {
                // Transient/flash notices always show regardless of ads toggle.
                if ( empty( $n['persistent'] ) ) {
                    return true;
                }
                return ! ( $n['ads'] ?? true );
            } ) );
        }

        // Allow plugins to add/remove/reorder notices.
        $notices = klytos_apply_filters( 'notice.before_render', $notices, $currentPage );

        return $notices;
    }

    /**
     * Render all notices for the current admin page.
     *
     * Outputs notice HTML and enqueues the dismiss JS (once per request)
     * via the admin.footer hook.
     *
     * @param string $cspNonce    CSP nonce for inline scripts.
     * @param string $currentPage Current admin page slug.
     */
    public function render( string $cspNonce = '', string $currentPage = '' ): void
    {
        $notices = $this->getRenderable( $currentPage );

        if ( empty( $notices ) ) {
            return;
        }

        $hasDismissible = false;

        klytos_do_action( 'notice.render.before', $notices );

        foreach ( $notices as $notice ) {
            $type        = klytos_esc_attr( $notice['type'] ?? 'info' );
            $message     = klytos_esc_html( $notice['message'] ?? '' );
            $id          = klytos_esc_attr( $notice['id'] ?? '' );
            $dismissible = ! empty( $notice['dismissible'] );
            $persistent  = ! empty( $notice['persistent'] );

            $classes = 'alert alert-' . $type;
            if ( $dismissible ) {
                $classes .= ' alert-dismissible';
                $hasDismissible = true;
            }

            $html = '<div class="' . $classes . '" data-notice-id="' . $id . '" role="alert">';
            $html .= '<span class="alert-message">' . $message . '</span>';

            if ( $dismissible ) {
                $html .= '<button type="button" class="alert-close" '
                       . 'data-dismiss-notice="' . $id . '" '
                       . ( $persistent ? 'data-persistent="1" ' : '' )
                       . 'aria-label="' . klytos_esc_attr( __( 'notices.close' ) ) . '">'
                       . '<i class="fa-solid fa-xmark"></i>'
                       . '</button>';
            }

            $html .= '</div>';

            // Allow plugins to customize per-notice HTML.
            $html = klytos_apply_filters( 'notice.render_html', $html, $notice );

            echo $html . "\n";
        }

        klytos_do_action( 'notice.render.after', $notices );

        // Enqueue dismiss JS once per request.
        if ( $hasDismissible && ! $this->jsEnqueued ) {
            $this->jsEnqueued = true;
            $this->enqueueDismissJs( $cspNonce );
        }
    }

    /**
     * Enqueue the dismiss JavaScript via the admin.footer hook.
     *
     * @param string $cspNonce CSP nonce for the inline script.
     */
    private function enqueueDismissJs( string $cspNonce ): void
    {
        klytos_add_action( 'admin.footer', function ( string $nonce ) use ( $cspNonce ): void {
            // Use the footer nonce if available, fall back to the one from render time.
            $scriptNonce = ! empty( $nonce ) ? $nonce : $cspNonce;
            if ( empty( $scriptNonce ) ) {
                return;
            }

            $apiUrl = klytos_esc_attr( Helpers::getBasePath() . 'admin/api/notices.php' );
            $csrf   = klytos_esc_attr( klytos_csrf_token() );

            echo '<script nonce="' . klytos_esc_attr( $scriptNonce ) . '">' . "\n";
            echo '(function(){' . "\n";
            echo '  document.querySelectorAll("[data-dismiss-notice]").forEach(function(btn){' . "\n";
            echo '    btn.addEventListener("click",function(){' . "\n";
            echo '      var noticeEl=btn.closest(".alert");' . "\n";
            echo '      var noticeId=btn.getAttribute("data-dismiss-notice");' . "\n";
            echo '      var persistent=btn.hasAttribute("data-persistent");' . "\n";
            echo '      if(noticeEl){' . "\n";
            echo '        noticeEl.style.opacity="0";' . "\n";
            echo '        noticeEl.style.transition="opacity var(--klytos-transition-fast,0.15s) ease";' . "\n";
            echo '        setTimeout(function(){noticeEl.remove();},200);' . "\n";
            echo '      }' . "\n";
            echo '      if(persistent&&noticeId){' . "\n";
            echo '        fetch("' . $apiUrl . '",{' . "\n";
            echo '          method:"POST",' . "\n";
            echo '          headers:{"Content-Type":"application/json"},' . "\n";
            echo '          body:JSON.stringify({action:"dismiss",id:noticeId,csrf:"' . $csrf . '"})' . "\n";
            echo '        });' . "\n";
            echo '      }' . "\n";
            echo '    });' . "\n";
            echo '  });' . "\n";
            echo '})();' . "\n";
            echo '</script>' . "\n";
        }, 20 );
    }
}
