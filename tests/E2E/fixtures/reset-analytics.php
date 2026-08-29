<?php

/**
 * Klytos CMS — seed a known pageview population for manifest entry 7.
 *
 * The Analytics screen's four stat cards, its 30-day line chart, that chart's
 * data table and both detail cards are all functions of the analytics
 * collection. Against an empty collection the screen renders its empty state
 * and **none of them can be asserted** — a check over a zero population is not
 * evidence (D-079).
 *
 * The series is DETERMINISTIC, for the same reason entry 18's is: the chart's
 * accessible headline names the total and the peak with its date, and a random
 * population makes the one thing a screen reader is given unassertable (D-112).
 *
 * `recordPageView()` always stamps TODAY — `klytos_gmdate( 'Y-m-d' )`, with no
 * parameter to override it — so a fixture that wants a multi-day series has to
 * write the entries directly. That is stated rather than hidden: it is the ONE
 * place this fixture does not go through the product's own writer, the reason
 * is that the writer has no seam for it, and the record shape below is copied
 * from `recordPageView()` and must stay in step with it. Everything the writer
 * would derive — the hashed visitor, the referrer DOMAIN only, the device
 * CLASS only — is written already-derived, never as raw input the product would
 * have reduced.
 *
 * Usage:
 *   php tests/E2E/fixtures/reset-analytics.php          seed
 *   php tests/E2E/fixtures/reset-analytics.php --off    remove
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

require __DIR__ . '/../../../installer/core/app.php';

$app = \Klytos\Core\App::getInstance();
$app->boot();

$storage = $app->getStorage();

const COLLECTION = 'analytics';

/** Everything this fixture writes carries the prefix, so removal is exact. */
const PREFIX = 'e2eanalytics-';

/*
 * ─── Remove whatever a previous run left ─────────────────────────
 *
 * By RECONSTRUCTING the ids, never by listing. `StorageInterface::list()`
 * returns `$records[]` — a plain list with the ids discarded (file-storage.php
 * :268-296) — so nothing that reads it can delete a specific record. That is
 * not a quirk to work around here quietly: it is the root of a real shipped
 * defect this fixture's first run surfaced, recorded and escalated rather than
 * patched inside a screen slice (see `docs/PROGRESS.md` open items).
 *
 * The ids are this fixture's own and deterministic, so the removal is exact and
 * touches nothing it did not write.
 */
$removed = 0;
for ( $i = 0; $i < 200; $i++ ) {
    $id = PREFIX . str_pad( (string) $i, 3, '0', STR_PAD_LEFT );
    if ( $storage->delete( COLLECTION, $id ) ) {
        $removed++;
    }
}

if ( in_array( '--off', $argv, true ) ) {
    echo "reset-analytics: removed {$removed} seeded entr(y|ies)\n";
    exit( 0 );
}

/*
 * The population, chosen so every assertion on the screen has an unambiguous
 * answer:
 *
 *   - 30 days of window, traffic on exactly four of them, so the DENSE series
 *     the chart needs is visibly different from the sparse map `getSummary()`
 *     returns — 26 of the 30 rows are a measured zero.
 *   - a single unambiguous PEAK (day -3, 6 views), so the headline is assertable.
 *   - three distinct pages and three referrer domains with distinct counts, so
 *     both detail cards have a stable order.
 *   - two device classes, so the devices card has more than one row and its
 *     percentages sum to 100.
 *   - visitor hashes chosen so `unique_visitors` differs from `total_views`:
 *     the number 5 is not reachable by accident from a count of 12.
 */
$plan = [
    // [ days ago, page, referrer domain, device, visitor ]
    [ 9, '/',        'duckduckgo.com', 'desktop', 'v1' ],
    [ 9, '/about',   'duckduckgo.com', 'desktop', 'v1' ],
    [ 7, '/',        '',               'mobile',  'v2' ],
    [ 7, '/pricing', 'news.example',   'mobile',  'v2' ],
    [ 7, '/',        'news.example',   'desktop', 'v3' ],
    [ 3, '/',        'duckduckgo.com', 'desktop', 'v4' ],
    [ 3, '/',        'duckduckgo.com', 'desktop', 'v4' ],
    [ 3, '/about',   'social.example', 'mobile',  'v5' ],
    [ 3, '/about',   'social.example', 'mobile',  'v5' ],
    [ 3, '/pricing', '',               'desktop', 'v4' ],
    [ 3, '/pricing', '',               'desktop', 'v4' ],
    [ 1, '/',        'news.example',   'desktop', 'v3' ],
];

$written = 0;
foreach ( $plan as $i => [ $daysAgo, $page, $referrer, $device, $visitor ] ) {
    $date = gmdate( 'Y-m-d', strtotime( "-{$daysAgo} days" ) );

    $storage->write( COLLECTION, PREFIX . str_pad( (string) $i, 3, '0', STR_PAD_LEFT ), [
        'page_path'       => $page,
        'referrer_domain' => $referrer,
        'device_category' => $device,
        // Already hashed, exactly as the product stores it: the salt rotates
        // daily, so a per-day-unique value is what a real day's entries hold.
        'visitor_hash'    => hash( 'sha256', $visitor . '|' . $date ),
        'date'            => $date,
        'timestamp'       => $date . 'T12:00:00Z',
    ] );

    $written++;
}

echo "reset-analytics: removed {$removed}, wrote {$written} entries\n";
echo "  window     : 30 days, traffic on 4 of them\n";
echo "  peak       : " . gmdate( 'Y-m-d', strtotime( '-3 days' ) ) . " with 6 views\n";
echo "  total views: {$written}\n";
