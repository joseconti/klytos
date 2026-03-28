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
    } elseif ($section === 'email') {
        $app->getSiteConfig()->set([
            'email' => [
                'transport'     => $_POST['email_transport'] ?? 'mail',
                'from_name'     => trim($_POST['email_from_name'] ?? ''),
                'from_email'    => trim($_POST['email_from_email'] ?? ''),
                'reply_to'      => trim($_POST['email_reply_to'] ?? ''),
                'smtp_host'     => trim($_POST['smtp_host'] ?? ''),
                'smtp_port'     => (int) ($_POST['smtp_port'] ?? 587),
                'smtp_user'     => trim($_POST['smtp_user'] ?? ''),
                'smtp_pass'     => $_POST['smtp_pass'] ?? '',
                'smtp_security' => $_POST['smtp_security'] ?? 'tls',
            ],
        ]);
        // Reset cached mailer so it picks up new config.
        if (isset($_POST['test_email'])) {
            $mailer = $app->getMailer();
            $adminEmail = $app->getConfig()['admin_email'] ?? '';
            if ($adminEmail && $mailer->sendTest($adminEmail)) {
                $success = __('settings.email_test_sent', ['email' => $adminEmail]);
            } else {
                $success = __('settings.email_test_failed');
            }
        } else {
            $success = __('common.success');
        }
    } elseif ($section === 'languages') {
        $languages = [];
        $langCodes = $_POST['lang_code'] ?? [];
        $langNames = $_POST['lang_name'] ?? [];
        for ($i = 0; $i < count($langCodes); $i++) {
            $code = trim($langCodes[$i] ?? '');
            $name = trim($langNames[$i] ?? '');
            if ($code !== '' && $name !== '') {
                $languages[] = ['code' => $code, 'name' => $name];
            }
        }
        $app->getSiteConfig()->set(['languages' => $languages]);
        $success = __( 'common.success' );
    } elseif ($section === 'editor') {
        $editorValue = $_POST['editor'] ?? 'gutenberg';
        if (!in_array($editorValue, ['gutenberg', 'tinymce'], true)) {
            $editorValue = 'gutenberg';
        }
        $app->getSiteConfig()->set(['editor' => $editorValue]);
        $success = __('common.success');
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

<!-- Email / SMTP -->
<div class="card">
    <div class="card-header"><h3><?php echo __('settings.email_title'); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="email">
        <div class="form-group">
            <label><?php echo __('settings.email_transport'); ?></label>
            <select name="email_transport" class="form-control">
                <option value="mail" <?php echo ($siteConfig['email']['transport'] ?? '') === 'mail' ? 'selected' : ''; ?>>PHP mail()</option>
                <option value="smtp" <?php echo ($siteConfig['email']['transport'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
            </select>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label><?php echo __('settings.email_from_name'); ?></label>
                <input type="text" name="email_from_name" class="form-control" value="<?php echo htmlspecialchars($siteConfig['email']['from_name'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars($siteConfig['site_name'] ?? 'Klytos'); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __('settings.email_from_email'); ?></label>
                <input type="email" name="email_from_email" class="form-control" value="<?php echo htmlspecialchars($siteConfig['email']['from_email'] ?? ''); ?>" placeholder="noreply@example.com">
            </div>
        </div>
        <div class="form-group">
            <label><?php echo __('settings.email_reply_to'); ?></label>
            <input type="email" name="email_reply_to" class="form-control" value="<?php echo htmlspecialchars($siteConfig['email']['reply_to'] ?? ''); ?>" placeholder="<?php echo __('common.optional'); ?>">
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem;">
            <div class="form-group">
                <label><?php echo __('settings.smtp_host'); ?></label>
                <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($siteConfig['email']['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label><?php echo __('settings.smtp_port'); ?></label>
                <input type="number" name="smtp_port" class="form-control" value="<?php echo (int)($siteConfig['email']['smtp_port'] ?? 587); ?>" placeholder="587">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label><?php echo __('settings.smtp_user'); ?></label>
                <input type="text" name="smtp_user" class="form-control" value="<?php echo htmlspecialchars($siteConfig['email']['smtp_user'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __('settings.smtp_pass'); ?></label>
                <input type="password" name="smtp_pass" class="form-control" value="<?php echo htmlspecialchars($siteConfig['email']['smtp_pass'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __('settings.smtp_security'); ?></label>
                <select name="smtp_security" class="form-control">
                    <option value="tls" <?php echo ($siteConfig['email']['smtp_security'] ?? '') === 'tls' ? 'selected' : ''; ?>>STARTTLS (587)</option>
                    <option value="ssl" <?php echo ($siteConfig['email']['smtp_security'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL/TLS (465)</option>
                    <option value="" <?php echo ($siteConfig['email']['smtp_security'] ?? 'tls') === '' ? 'selected' : ''; ?>><?php echo __('settings.smtp_none'); ?></option>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <button type="submit" class="btn btn-primary"><?php echo __('common.save'); ?></button>
            <button type="submit" name="test_email" value="1" class="btn btn-outline"><?php echo __('settings.email_test'); ?></button>
        </div>
    </form>
</div>

<!-- Languages -->
<div class="card">
    <div class="card-header"><h3>Languages</h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="languages">
        <p style="font-size:0.85rem;color:var(--admin-text-muted);margin-bottom:1rem;">Define the languages available on your site. These will be used for post type slug translations and content localization.</p>
        <div id="languages-list">
            <?php
            $languages = $siteConfig['languages'] ?? [];
            if (empty($languages)) {
                $languages = [['code' => '', 'name' => '']];
            }
            foreach ($languages as $i => $lang): ?>
                <div class="form-group" style="display:flex;gap:0.5rem;align-items:end;">
                    <div>
                        <?php if ($i === 0): ?><label>Code</label><?php endif; ?>
                        <input type="text" name="lang_code[]" class="form-control" value="<?php echo htmlspecialchars($lang['code'] ?? ''); ?>" placeholder="es" style="width:80px;">
                    </div>
                    <div style="flex:1;">
                        <?php if ($i === 0): ?><label>Name</label><?php endif; ?>
                        <input type="text" name="lang_name[]" class="form-control" value="<?php echo htmlspecialchars($lang['name'] ?? ''); ?>" placeholder="Espanol">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="addLanguageRow()" style="margin-bottom:1rem;">+ Add Language</button>
        <br>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<script nonce="<?php echo $cspNonce; ?>">
function addLanguageRow() {
    var list = document.getElementById('languages-list');
    var div = document.createElement('div');
    div.className = 'form-group';
    div.style.cssText = 'display:flex;gap:0.5rem;align-items:end;';
    div.innerHTML = '<div><input type="text" name="lang_code[]" class="form-control" placeholder="en" style="width:80px;"></div>' +
                    '<div style="flex:1;"><input type="text" name="lang_name[]" class="form-control" placeholder="English"></div>';
    list.appendChild(div);
}
</script>

<!-- Content Editor -->
<div class="card">
    <div class="card-header"><h3><?php echo __('editor.title'); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <input type="hidden" name="section" value="editor">
        <div class="form-group">
            <label><?php echo __('editor.choose'); ?></label>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:0.5rem;">
                <label style="display:block;padding:1rem;border:2px solid <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'gutenberg' ? 'var(--admin-primary)' : 'var(--admin-border)'; ?>;border-radius:8px;cursor:pointer;">
                    <input type="radio" name="editor" value="gutenberg" <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'gutenberg' ? 'checked' : ''; ?> style="margin-right:0.5rem;">
                    <strong>Gutenberg</strong>
                    <p style="margin:0.5rem 0 0;font-size:0.85rem;color:var(--admin-text-muted);"><?php echo __('editor.gutenberg_desc'); ?></p>
                </label>
                <label style="display:block;padding:1rem;border:2px solid <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'tinymce' ? 'var(--admin-primary)' : 'var(--admin-border)'; ?>;border-radius:8px;cursor:pointer;">
                    <input type="radio" name="editor" value="tinymce" <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'tinymce' ? 'checked' : ''; ?> style="margin-right:0.5rem;">
                    <strong>TinyMCE</strong>
                    <p style="margin:0.5rem 0 0;font-size:0.85rem;color:var(--admin-text-muted);"><?php echo __('editor.tinymce_desc'); ?></p>
                </label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __('common.save'); ?></button>
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
