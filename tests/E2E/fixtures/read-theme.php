<?php

/**
 * Klytos CMS — read the stored theme document (manifest entry 3, Design).
 *
 * `SPEC/accessibility.md` §10.7 requires an override of the 4.5:1 floor to be
 * RECORDED, not merely permitted. "Recorded" is a claim about what is on disk,
 * so the test has to read what is on disk — the screen re-rendering the value
 * it just posted proves nothing about persistence.
 *
 * The theme lives in encrypted storage under a path only the app can resolve,
 * so this boots the product rather than reading a file by hand.
 *
 * Usage:  php tests/E2E/fixtures/read-theme.php [key]
 *         key defaults to `contrast_overrides`; the whole document is dumped
 *         when the key is `all`.
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$theme = $app->getTheme()->get();
$key   = $argv[1] ?? 'contrast_overrides';

echo json_encode( $key === 'all' ? $theme : ( $theme[ $key ] ?? [] ) );
echo "\n";
