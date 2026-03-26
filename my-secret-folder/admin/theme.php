<?php
/**
 * Klytos Admin — Theme Editor
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

$pageTitle = __( 'theme.title' );
$auth      = $app->getAuth();
$theme     = $app->getTheme();
$success   = '';
$error     = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $section = $_POST['section'] ?? '';

    if ($section === 'colors') {
        $colors = [];
        $colorKeys = ['primary','secondary','accent','background','surface','text','text_muted','border','success','warning','error'];
        foreach ($colorKeys as $key) {
            if (!empty($_POST[$key])) $colors[$key] = $_POST[$key];
        }
        $theme->setColors($colors);
        $success = __( 'common.success' );
    } elseif ($section === 'fonts') {
        $theme->setFonts([
            'heading'          => $_POST['heading'] ?? '',
            'body'             => $_POST['body'] ?? '',
            'code'             => $_POST['code'] ?? '',
            'base_size'        => $_POST['base_size'] ?? '16px',
            'google_fonts_url' => $_POST['google_fonts_url'] ?? '',
        ]);
        $success = __( 'common.success' );
    } elseif ($section === 'layout') {
        $theme->setLayout([
            'max_width'    => $_POST['max_width'] ?? '1200px',
            'header_style' => $_POST['header_style'] ?? 'sticky',
            'border_radius'=> $_POST['border_radius'] ?? '8px',
        ]);
        $success = __( 'common.success' );
    }
}

$themeData = $theme->get();
$csrf      = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>

<!-- Colors -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'theme.colors' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="colors">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:1rem;">
            <?php
            $colorLabels = [
                'primary' => __( 'theme.primary_color' ),
                'secondary' => __( 'theme.secondary_color' ),
                'accent' => __( 'theme.accent_color' ),
                'background' => __( 'theme.background_color' ),
                'surface' => __( 'theme.surface_color' ),
                'text' => __( 'theme.text_color' ),
                'text_muted' => __( 'theme.text_muted_color' ),
                'border' => __( 'theme.border_color' ),
            ];
            foreach ($colorLabels as $key => $label): ?>
                <div class="form-group">
                    <label><?php echo $label; ?></label>
                    <div class="color-row">
                        <input type="color" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars( $themeData['colors'][$key] ?? '#000000'); ?>">
                        <input type="text" name="<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars( $themeData['colors'][$key] ?? ''); ?>" pattern="#[0-9a-fA-F]{3,8}">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<!-- Fonts -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'theme.fonts' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="fonts">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label><?php echo __( 'theme.heading_font' ); ?></label>
                <input type="text" name="heading" class="form-control" value="<?php echo htmlspecialchars( $themeData['fonts']['heading'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __( 'theme.body_font' ); ?></label>
                <input type="text" name="body" class="form-control" value="<?php echo htmlspecialchars( $themeData['fonts']['body'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __( 'theme.code_font' ); ?></label>
                <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars( $themeData['fonts']['code'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __( 'theme.base_size' ); ?></label>
                <input type="text" name="base_size" class="form-control" value="<?php echo htmlspecialchars( $themeData['fonts']['base_size'] ?? '16px'); ?>">
            </div>
        </div>
        <div class="form-group">
            <label><?php echo __( 'theme.google_fonts_url' ); ?></label>
            <input type="text" name="google_fonts_url" class="form-control" value="<?php echo htmlspecialchars( $themeData['fonts']['google_fonts_url'] ?? ''); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<!-- Layout -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'theme.layout' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="layout">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label><?php echo __( 'theme.max_width' ); ?></label>
                <input type="text" name="max_width" class="form-control" value="<?php echo htmlspecialchars( $themeData['layout']['max_width'] ?? '1200px'); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __( 'theme.header_style' ); ?></label>
                <select name="header_style" class="form-control">
                    <option value="sticky" <?php echo ($themeData['layout']['header_style'] ?? '') === 'sticky' ? 'selected' : ''; ?>>Sticky</option>
                    <option value="fixed" <?php echo ($themeData['layout']['header_style'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                    <option value="static" <?php echo ($themeData['layout']['header_style'] ?? '') === 'static' ? 'selected' : ''; ?>>Static</option>
                </select>
            </div>
            <div class="form-group">
                <label><?php echo __( 'theme.border_radius' ); ?></label>
                <input type="text" name="border_radius" class="form-control" value="<?php echo htmlspecialchars( $themeData['layout']['border_radius'] ?? '8px'); ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
