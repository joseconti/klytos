<?php

/**
 * Klytos x402 — .htaccess Writer
 *
 * Injects x402 rewrite rules into the public .htaccess between markers.
 * Also generates Nginx config snippet.
 *
 * @package Klytos
 * @since   2.0.0
 */

declare( strict_types=1 );

namespace Klytos\Core\X402;

use Klytos\Core\Helpers;

class HtaccessWriter
{
    private const MARKER_START = '# --- Klytos x402 Plugin - START ---';
    private const MARKER_END   = '# --- Klytos x402 Plugin - END ---';

    private BotDetector $detector;

    public function __construct( BotDetector $detector )
    {
        $this->detector = $detector;
    }

    public function writeRules(): void
    {
        $htaccessPath = $this->getPublicPath() . '/.htaccess';
        if ( !file_exists( $htaccessPath ) ) return;

        $content = file_get_contents( $htaccessPath );
        if ( $content === false ) return;

        $content = $this->removeExistingRules( $content );
        $rules   = $this->buildRulesBlock();

        $insertBefore = 'RewriteCond %{REQUEST_FILENAME} -f';
        $pos = strpos( $content, $insertBefore );

        if ( $pos !== false ) {
            $lineStart = strrpos( substr( $content, 0, $pos ), "\n" );
            $lineStart = $lineStart !== false ? $lineStart + 1 : $pos;

            $beforeLine = substr( $content, 0, $lineStart );
            $lastNewline = strrpos( $beforeLine, "\n" );
            if ( $lastNewline !== false ) {
                $previousLine = trim( substr( $beforeLine, $lastNewline + 1 ) );
                if ( str_starts_with( $previousLine, '#' ) ) {
                    $lineStart = $lastNewline + 1;
                }
            }

            $content = substr( $content, 0, $lineStart ) . $rules . "\n" . substr( $content, $lineStart );
        } else {
            $content .= "\n" . $rules . "\n";
        }

        file_put_contents( $htaccessPath, $content, LOCK_EX );
    }

    public function removeRules(): void
    {
        $htaccessPath = $this->getPublicPath() . '/.htaccess';
        if ( !file_exists( $htaccessPath ) ) return;

        $content = file_get_contents( $htaccessPath );
        if ( $content === false ) return;

        $content = $this->removeExistingRules( $content );
        file_put_contents( $htaccessPath, $content, LOCK_EX );
    }

    public function generateNginxConfig(): string
    {
        $pattern = $this->detector->buildHtaccessPattern();

        return <<<NGINX
# Klytos x402 — Nginx config. Include in your server block.
set \$x402_gate 0;
if (\$http_x_payment) {
    set \$x402_gate 1;
}
if (\$http_user_agent ~* "({$pattern})") {
    set \$x402_gate 1;
}
if (\$x402_gate = 1) {
    rewrite ^/(.+)\.(html|html\.md)\$ /x402-gate.php?slug=\$1&format=\$2 last;
}
NGINX;
    }

    private function buildRulesBlock(): string
    {
        $pattern = $this->detector->buildHtaccessPattern();

        return self::MARKER_START . "\n"
             . "# Auto-generated. Do not edit manually.\n\n"
             . "# Detect x402 headers (any client with payment headers)\n"
             . "RewriteCond %{HTTP:X-Payment} !^$\n"
             . "RewriteRule ^(.+)\\.(html|html\\.md)$ x402-gate.php?slug=$1&format=$2 [L,QSA]\n\n"
             . "# Detect known AI bot User-Agents\n"
             . "RewriteCond %{HTTP_USER_AGENT} ({$pattern}) [NC]\n"
             . "RewriteRule ^(.+)\\.(html|html\\.md)$ x402-gate.php?slug=$1&format=$2 [L,QSA]\n\n"
             . self::MARKER_END;
    }

    private function removeExistingRules( string $content ): string
    {
        $startPos = strpos( $content, self::MARKER_START );
        $endPos   = strpos( $content, self::MARKER_END );

        if ( $startPos !== false && $endPos !== false ) {
            $endPos += strlen( self::MARKER_END );
            while ( $endPos < strlen( $content ) && $content[$endPos] === "\n" ) $endPos++;
            $content = substr( $content, 0, $startPos ) . substr( $content, $endPos );
        }

        return $content;
    }

    private function getPublicPath(): string
    {
        return dirname( Helpers::getRootPath() );
    }
}
