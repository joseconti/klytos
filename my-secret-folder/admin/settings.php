<?php
/**
 * Klytos Admin — Settings
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

$pageTitle = __( 'settings.title' );
$auth      = $app->getAuth();
$success   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $section = $_POST['section'] ?? '';

    if ($section === 'general') {
        $app->getSiteConfig()->set([
            'site_name'        => trim($_POST['site_name'] ?? ''),
            'tagline'          => trim($_POST['tagline'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'default_language' => $_POST['default_language'] ?? 'es',
        ]);
        $success = __( 'common.success' );
    } elseif ($section === 'social') {
        $app->getSiteConfig()->set([
            'social' => [
                'twitter'   => trim($_POST['twitter'] ?? ''),
                'github'    => trim($_POST['github'] ?? ''),
                'linkedin'  => trim($_POST['linkedin'] ?? ''),
                'instagram' => trim($_POST['instagram'] ?? ''),
                'youtube'   => trim($_POST['youtube'] ?? ''),
                'mastodon'  => trim($_POST['mastodon'] ?? ''),
            ],
        ]);
        $success = __( 'common.success' );
    } elseif ($section === 'analytics') {
        $app->getSiteConfig()->set([
            'analytics' => [
                'google_analytics_id'  => trim($_POST['google_analytics_id'] ?? ''),
                'custom_head_scripts'  => $_POST['custom_head_scripts'] ?? '',
                'custom_body_scripts'  => $_POST['custom_body_scripts'] ?? '',
            ],
        ]);
        $success = __( 'common.success' );
    } elseif ($section === 'ai') {
        $generator = new \Klytos\Core\AiImageGenerator(
            $app->getStorage(),
            $app->getAssets(),
            $app->getConfigPath()
        );
        $generator->setApiKey(trim($_POST['gemini_api_key'] ?? ''));
        $success = __( 'ai_images.api_key_saved' );
    }
}

$siteConfig = $app->getSiteConfig()->get();
$csrf       = $auth->getCsrfToken();

// Get AI config
$aiGenerator = new \Klytos\Core\AiImageGenerator(
    $app->getStorage(),
    $app->getAssets(),
    $app->getConfigPath()
);
$aiApiKey = $aiGenerator->getApiKey();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>

<!-- General Settings -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'settings.title' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="general">
        <div class="form-group">
            <label><?php echo __( 'settings.site_name' ); ?></label>
            <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars( $siteConfig['site_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label><?php echo __( 'settings.tagline' ); ?></label>
            <input type="text" name="tagline" class="form-control" value="<?php echo htmlspecialchars( $siteConfig['tagline'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label><?php echo __( 'settings.site_description' ); ?></label>
            <textarea name="description" class="form-control"><?php echo htmlspecialchars( $siteConfig['description'] ?? ''); ?></textarea>
        </div>
        <div class="form-group">
            <label><?php echo __( 'settings.default_language' ); ?></label>
            <select name="default_language" class="form-control">
                <option value="es" <?php echo ($siteConfig['default_language'] ?? '') === 'es' ? 'selected' : ''; ?>>Espanol</option>
                <option value="en" <?php echo ($siteConfig['default_language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                <option value="ca" <?php echo ($siteConfig['default_language'] ?? '') === 'ca' ? 'selected' : ''; ?>>Catala</option>
                <option value="fr" <?php echo ($siteConfig['default_language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Francais</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<!-- Social -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'settings.social' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="social">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <?php foreach (['twitter','github','linkedin','instagram','youtube','mastodon'] as $social): ?>
                <div class="form-group">
                    <label><?php echo ucfirst($social); ?></label>
                    <input type="text" name="<?php echo $social; ?>" class="form-control" value="<?php echo htmlspecialchars( $siteConfig['social'][$social] ?? ''); ?>" placeholder="https://...">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<!-- Analytics -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'settings.analytics' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="analytics">
        <div class="form-group">
            <label><?php echo __( 'settings.google_analytics_id' ); ?></label>
            <input type="text" name="google_analytics_id" class="form-control" value="<?php echo htmlspecialchars( $siteConfig['analytics']['google_analytics_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX">
        </div>
        <div class="form-group">
            <label><?php echo __( 'settings.custom_head_scripts' ); ?></label>
            <textarea name="custom_head_scripts" class="form-control mono" rows="4"><?php echo htmlspecialchars( $siteConfig['analytics']['custom_head_scripts'] ?? ''); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<!-- AI API Key -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'ai_images.title' ); ?> — API</h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="ai">
        <div class="form-group">
            <label><?php echo __( 'ai_images.api_key' ); ?> (Gemini)</label>
            <input type="password" name="gemini_api_key" class="form-control" value="<?php echo htmlspecialchars( $aiApiKey ); ?>" placeholder="AIza...">
            <p class="form-help">Get your API key from Google AI Studio (aistudio.google.com)</p>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
