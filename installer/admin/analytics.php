<?php
/**
 * Klytos Admin — Analytics Dashboard
 * Privacy-first analytics: pageviews, unique visitors, top pages, referrers, devices.
 * No cookies. No fingerprinting. GDPR/RGPD compliant by design.
 *
 * @package Klytos
 * @since   2.0.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 José Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;
use Klytos\Core\AnalyticsManager;

$pageTitle = 'Analytics';
$analytics = new AnalyticsManager($app->getStorage());

// ─── Date range ──────────────────────────────────────────────
$period   = $_GET['period'] ?? '7d';
$dateFrom = match ($period) {
    '24h'  => date( 'Y-m-d', strtotime('-1 day')),
    '7d'   => date( 'Y-m-d', strtotime('-7 days')),
    '30d'  => date( 'Y-m-d', strtotime('-30 days')),
    '90d'  => date( 'Y-m-d', strtotime('-90 days')),
    default => date( 'Y-m-d', strtotime('-7 days')),
};
$dateTo = date( 'Y-m-d');

$summary = $analytics->getSummary($dateFrom, $dateTo);

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<!-- Privacy badge -->
<div class="alert alert-info" style="display:flex;align-items:center;gap:0.75rem">
    <span style="font-size:1.3rem">🔒</span>
    <div>
        <strong>Privacy-First Analytics</strong> — No cookies, no fingerprinting, no personal data.
        IP addresses are hashed with a daily rotating salt. GDPR/RGPD compliant. No consent banner required.
    </div>
</div>

<!-- Period selector -->
<div class="tabs" style="margin-bottom:1.5rem">
    <a href="?period=24h" class="tab <?php echo $period === '24h' ? 'active' : ''; ?>">Last 24h</a>
    <a href="?period=7d" class="tab <?php echo $period === '7d' ? 'active' : ''; ?>">Last 7 days</a>
    <a href="?period=30d" class="tab <?php echo $period === '30d' ? 'active' : ''; ?>">Last 30 days</a>
    <a href="?period=90d" class="tab <?php echo $period === '90d' ? 'active' : ''; ?>">Last 90 days</a>
</div>

<!-- Stats overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Page Views</div>
        <div class="stat-value"><?php echo number_format( $summary['total_views'] ?? 0); ?></div>
        <div class="stat-detail"><?php echo $dateFrom; ?> — <?php echo $dateTo; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Unique Visitors</div>
        <div class="stat-value"><?php echo number_format( $summary['unique_visitors'] ?? 0); ?></div>
        <div class="stat-detail">Anonymized (daily hash)</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Avg. Views/Day</div>
        <div class="stat-value">
            <?php
            $days = max(1, (int) ((strtotime($dateTo) - strtotime($dateFrom)) / 86400));
            echo number_format(($summary['total_views'] ?? 0) / $days, 1);
            ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pages Tracked</div>
        <div class="stat-value"><?php echo count( $summary['top_pages'] ?? []); ?></div>
    </div>
</div>

<!-- Daily chart -->
<?php $dailyViews = $summary['daily_views'] ?? []; ?>
<?php if (!empty($dailyViews)): ?>
<div class="card">
    <div class="card-header">
        <h3>Daily Page Views</h3>
    </div>
    <?php
    $maxViews = max(1, max($dailyViews));
    ?>
    <div class="chart-bar" style="height:140px">
        <?php foreach ($dailyViews as $date => $count): ?>
            <div class="chart-bar-item" style="height:<?php echo round(($count / $maxViews) * 100); ?>%"
                 title="<?php echo htmlspecialchars( $date ); ?>: <?php echo $count; ?> views"></div>
        <?php endforeach; ?>
    </div>
    <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--admin-text-muted);margin-top:0.4rem">
        <span><?php echo $dateFrom; ?></span>
        <span><?php echo $dateTo; ?></span>
    </div>
</div>
<?php endif; ?>

<div class="grid-2">
    <!-- Top Pages -->
    <div class="card">
        <div class="card-header">
            <h3>Top Pages</h3>
        </div>
        <?php if (empty($summary['top_pages'])): ?>
            <p style="color:var(--admin-text-muted)">No data yet. Analytics starts recording when pages receive visits.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Page</th><th style="text-align:right">Views</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($summary['top_pages'], 0, 10, true) as $path => $views): ?>
                        <tr>
                            <td><code style="font-size:0.85rem"><?php echo htmlspecialchars( $path ); ?></code></td>
                            <td style="text-align:right;font-weight:600"><?php echo number_format( $views); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Top Referrers -->
    <div class="card">
        <div class="card-header">
            <h3>Top Referrers</h3>
        </div>
        <?php if (empty($summary['top_referrers'])): ?>
            <p style="color:var(--admin-text-muted)">No referrer data yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>Domain</th><th style="text-align:right">Visits</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary['top_referrers'] as $domain => $count): ?>
                        <tr>
                            <td><?php echo htmlspecialchars( $domain ); ?></td>
                            <td style="text-align:right;font-weight:600"><?php echo number_format( $count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Devices -->
<?php if (!empty($summary['devices'])): ?>
<div class="card">
    <div class="card-header">
        <h3>Devices</h3>
    </div>
    <div style="display:flex;gap:2rem;flex-wrap:wrap">
        <?php foreach ($summary['devices'] as $category => $info): ?>
        <div style="text-align:center">
            <div style="font-size:2rem;margin-bottom:0.25rem">
                <?php echo match ($category) {
                    'mobile'  => '📱',
                    'tablet'  => '📋',
                    'desktop' => '🖥️',
                    default   => '❓',
                } ?>
            </div>
            <div style="font-weight:700;font-size:1.2rem"><?php echo $info['percentage']; ?>%</div>
            <div style="font-size:0.8rem;color:var(--admin-text-muted)"><?php echo ucfirst( htmlspecialchars( $category )); ?></div>
            <div style="font-size:0.75rem;color:var(--admin-text-muted)"><?php echo number_format( $info['count']); ?> visits</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
