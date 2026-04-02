<?php

/**
 * Klytos Admin — Settings
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $section = $_POST['section'] ?? '';
    klytos_do_action('admin.settings.before_save', $section, $_POST);

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
    } elseif ($section === 'appearance') {
        $themeValue = $_POST['admin_theme'] ?? 'dark';
        if (!in_array($themeValue, ['light', 'dark'], true)) {
            $themeValue = 'dark';
        }
        $app->getSiteConfig()->set(['admin_theme' => $themeValue]);
        $success = __('common.success');
    } elseif ($section === 'developer') {
        $app->getSiteConfig()->set([
            'developer' => [
                'developer_mode'            => (bool) ($_POST['developer_mode'] ?? false),
                'devbar_show_performance'   => (bool) ($_POST['devbar_show_performance'] ?? true),
                'devbar_show_queries'       => (bool) ($_POST['devbar_show_queries'] ?? true),
                'devbar_show_hooks'         => (bool) ($_POST['devbar_show_hooks'] ?? true),
                'devbar_show_assets'        => (bool) ($_POST['devbar_show_assets'] ?? true),
                'devbar_show_request'       => (bool) ($_POST['devbar_show_request'] ?? true),
                'devbar_show_environment'   => (bool) ($_POST['devbar_show_environment'] ?? true),
                'devbar_log_slow_threshold' => max( 10, (int) ($_POST['devbar_log_slow_threshold'] ?? 200) ),
            ],
        ]);
        $success = __( 'common.success' );
    } elseif ($section === 'ai') {
        $generator = new \Klytos\Core\AiImageGenerator(
            $app->getStorage(),
            $app->getAssets()
        );
        $generator->setApiKey(trim($_POST['gemini_api_key'] ?? ''));
        $success = __( 'ai_images.api_key_saved' );
    }

    klytos_do_action('admin.settings.after_save', $section, $_POST);
}

$siteConfig = $app->getSiteConfig()->get();
$csrf       = $auth->getCsrfToken();

// Get AI config
$aiGenerator = new \Klytos\Core\AiImageGenerator(
    $app->getStorage(),
    $app->getAssets()
);
$aiApiKey = $aiGenerator->getApiKey();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>
<?php klytos_do_action( 'admin.settings.before' ); ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo klytos_esc_html( $success ); ?></div>
<?php endif; ?>

<?php klytos_do_action('admin.settings.before_section', 'general'); ?>
<!-- General Settings -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'settings.title' ); ?></h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="general">
        <div class="form-group">
            <label><?php echo __( 'settings.site_name' ); ?></label>
            <input type="text" name="site_name" class="form-control" value="<?php echo klytos_esc_attr( $siteConfig['site_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label><?php echo __( 'settings.tagline' ); ?></label>
            <input type="text" name="tagline" class="form-control" value="<?php echo klytos_esc_attr( $siteConfig['tagline'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label><?php echo __( 'settings.site_description' ); ?></label>
            <textarea name="description" class="form-control"><?php echo klytos_esc_html( $siteConfig['description'] ?? ''); ?></textarea>
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
<?php klytos_do_action('admin.settings.after_section', 'general'); ?>

<?php klytos_do_action('admin.settings.before_section', 'social'); ?>
<!-- Social -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'settings.social' ); ?></h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="social">
        <div class="grid-2">
            <?php foreach (['twitter','github','linkedin','instagram','youtube','mastodon'] as $social): ?>
                <div class="form-group">
                    <label><?php echo ucfirst($social); ?></label>
                    <input type="text" name="<?php echo $social; ?>" class="form-control" value="<?php echo klytos_esc_attr( $siteConfig['social'][$social] ?? ''); ?>" placeholder="https://...">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>
<?php klytos_do_action('admin.settings.after_section', 'social'); ?>

<?php klytos_do_action('admin.settings.before_section', 'analytics'); ?>
<!-- Analytics -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'settings.analytics' ); ?></h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="analytics">
        <div class="form-group">
            <label><?php echo __( 'settings.google_analytics_id' ); ?></label>
            <input type="text" name="google_analytics_id" class="form-control" value="<?php echo klytos_esc_attr( $siteConfig['analytics']['google_analytics_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX">
        </div>
        <div class="form-group">
            <label><?php echo __( 'settings.custom_head_scripts' ); ?></label>
            <textarea name="custom_head_scripts" class="form-control mono" rows="4"><?php echo klytos_esc_html( $siteConfig['analytics']['custom_head_scripts'] ?? ''); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>
<?php klytos_do_action('admin.settings.after_section', 'analytics'); ?>

<?php klytos_do_action('admin.settings.before_section', 'email'); ?>
<!-- Email / SMTP -->
<div class="card">
    <div class="card-header"><h3><?php echo __('settings.email_title'); ?></h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="email">
        <div class="form-group">
            <label><?php echo __('settings.email_transport'); ?></label>
            <select name="email_transport" class="form-control">
                <option value="mail" <?php echo ($siteConfig['email']['transport'] ?? '') === 'mail' ? 'selected' : ''; ?>>PHP mail()</option>
                <option value="smtp" <?php echo ($siteConfig['email']['transport'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
            </select>
        </div>
        <div class="grid-2">
            <div class="form-group">
                <label><?php echo __('settings.email_from_name'); ?></label>
                <input type="text" name="email_from_name" class="form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['from_name'] ?? ''); ?>" placeholder="<?php echo klytos_esc_attr($siteConfig['site_name'] ?? 'Klytos'); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __('settings.email_from_email'); ?></label>
                <input type="email" name="email_from_email" class="form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['from_email'] ?? ''); ?>" placeholder="noreply@example.com">
            </div>
        </div>
        <div class="form-group">
            <label><?php echo __('settings.email_reply_to'); ?></label>
            <input type="email" name="email_reply_to" class="form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['reply_to'] ?? ''); ?>" placeholder="<?php echo __('common.optional'); ?>">
        </div>
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:1rem">
            <div class="form-group">
                <label><?php echo __('settings.smtp_host'); ?></label>
                <input type="text" name="smtp_host" class="form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com">
            </div>
            <div class="form-group">
                <label><?php echo __('settings.smtp_port'); ?></label>
                <input type="number" name="smtp_port" class="form-control" value="<?php echo (int)($siteConfig['email']['smtp_port'] ?? 587); ?>" placeholder="587">
            </div>
        </div>
        <div class="grid-3">
            <div class="form-group">
                <label><?php echo __('settings.smtp_user'); ?></label>
                <input type="text" name="smtp_user" class="form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['smtp_user'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label><?php echo __('settings.smtp_pass'); ?></label>
                <input type="password" name="smtp_pass" class="form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['smtp_pass'] ?? ''); ?>">
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
        <div class="flex flex-gap-sm">
            <button type="submit" class="btn btn-primary"><?php echo __('common.save'); ?></button>
            <button type="submit" name="test_email" value="1" class="btn btn-outline"><?php echo __('settings.email_test'); ?></button>
        </div>
    </form>
</div>
<?php klytos_do_action('admin.settings.after_section', 'email'); ?>

<?php klytos_do_action('admin.settings.before_section', 'languages'); ?>
<!-- Languages -->
<div class="card">
    <div class="card-header"><h3>Languages</h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="languages">
        <p class="text-sm text-muted mb-2">Define the languages available on your site. These will be used for post type slug translations and content localization.</p>
        <div id="languages-list">
            <?php
            $languages = $siteConfig['languages'] ?? [];
            if (empty($languages)) {
                $languages = [['code' => '', 'name' => '']];
            }
            foreach ($languages as $i => $lang): ?>
                <div class="form-group flex flex-gap-sm" style="align-items:end">
                    <div>
                        <?php if ($i === 0):
                            ?><label>Code</label><?php
                        endif; ?>
                        <input type="text" name="lang_code[]" class="form-control" value="<?php echo klytos_esc_attr($lang['code'] ?? ''); ?>" placeholder="es" style="width:80px;">
                    </div>
                    <div class="flex-1">
                        <?php if ($i === 0):
                            ?><label>Name</label><?php
                        endif; ?>
                        <input type="text" name="lang_name[]" class="form-control" value="<?php echo klytos_esc_attr($lang['name'] ?? ''); ?>" placeholder="Espanol">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline btn-sm mb-2" id="btn-add-language">+ Add Language</button>
        <br>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function() {
    var btn = document.getElementById('btn-add-language');
    if (btn) {
        btn.addEventListener('click', function() {
            var list = document.getElementById('languages-list');
            var div = document.createElement('div');
            div.className = 'form-group flex flex-gap-sm';
            div.style.cssText = 'align-items:end;';
            div.innerHTML = '<div><input type="text" name="lang_code[]" class="form-control" placeholder="en" style="width:80px;"></div>' +
                            '<div class="flex-1"><input type="text" name="lang_name[]" class="form-control" placeholder="English"></div>';
            list.appendChild(div);
        });
    }
})();
</script>
<?php klytos_do_action('admin.settings.after_section', 'languages'); ?>

<?php klytos_do_action('admin.settings.before_section', 'appearance'); ?>
<!-- Appearance -->
<div class="card">
    <div class="card-header"><h3><?php echo __('settings.appearance_title'); ?></h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="appearance">
        <div class="form-group">
            <label><?php echo __('settings.appearance_choose'); ?></label>
            <div class="selection-cards cols-2 mt-1">
                <label class="selection-card">
                    <input type="radio" name="admin_theme" value="light" <?php echo ($siteConfig['admin_theme'] ?? 'dark') === 'light' ? 'checked' : ''; ?>>
                    <div class="selection-card-body">
                        <span class="selection-card-title"><i class="fa-solid fa-sun mr-1"></i> <?php echo __('settings.appearance_light'); ?></span>
                        <span class="selection-card-desc"><?php echo __('settings.appearance_light_desc'); ?></span>
                    </div>
                </label>
                <label class="selection-card">
                    <input type="radio" name="admin_theme" value="dark" <?php echo ($siteConfig['admin_theme'] ?? 'dark') === 'dark' ? 'checked' : ''; ?>>
                    <div class="selection-card-body">
                        <span class="selection-card-title"><i class="fa-solid fa-moon mr-1"></i> <?php echo __('settings.appearance_dark'); ?></span>
                        <span class="selection-card-desc"><?php echo __('settings.appearance_dark_desc'); ?></span>
                    </div>
                </label>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __('common.save'); ?></button>
    </form>
</div>
<?php klytos_do_action('admin.settings.after_section', 'appearance'); ?>

<?php klytos_do_action('admin.settings.before_section', 'ai'); ?>
<!-- AI API Key -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'ai_images.title' ); ?> — API</h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="ai">
        <div class="form-group">
            <label><?php echo __( 'ai_images.api_key' ); ?> (Gemini)</label>
            <input type="password" name="gemini_api_key" class="form-control" value="<?php echo klytos_esc_attr( $aiApiKey ); ?>" placeholder="AIza...">
            <p class="form-help">Get your API key from Google AI Studio (aistudio.google.com)</p>
        </div>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>
<?php klytos_do_action('admin.settings.after_section', 'ai'); ?>

<?php if (klytos_has_permission( 'site.configure' )): ?>
<?php klytos_do_action('admin.settings.before_section', 'developer'); ?>
<?php $devConfig = $siteConfig['developer'] ?? []; ?>
<!-- Developer -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'settings.developer' ); ?></h3></div>
    <form method="post">
        <?php echo klytos_csrf_field(); ?>
        <input type="hidden" name="section" value="developer">
        <div class="form-group">
            <label class="flex flex-center flex-gap-sm" style="cursor:pointer">
                <input type="checkbox" name="developer_mode" value="1" <?php echo !empty( $devConfig['developer_mode'] ) ? 'checked' : ''; ?>>
                <?php echo __( 'settings.developer_mode' ); ?>
            </label>
            <p class="form-help"><?php echo __( 'settings.developer_mode_help' ); ?></p>
        </div>
        <?php if (!empty( $devConfig['developer_mode'] )): ?>
            <div class="text-sm mb-2" style="padding:0.75rem;background:var(--klytos-warning-subtle);border:1px solid var(--klytos-warning);border-radius:6px">
                <i class="fa-solid fa-triangle-exclamation" style="color:var(--klytos-warning)"></i>
                <?php echo __( 'settings.developer_mode_warning' ); ?>
            </div>
            <h4 class="mt-3 mb-1" style="font-size:0.95rem"><?php echo __( 'settings.devbar_panels' ); ?></h4>
            <div class="grid-2" style="gap:0.5rem 1.5rem">
                <?php
                $devbarToggles = [
                    'devbar_show_performance'  => __( 'settings.devbar_performance' ),
                    'devbar_show_queries'      => __( 'settings.devbar_queries' ),
                    'devbar_show_hooks'        => __( 'settings.devbar_hooks' ),
                    'devbar_show_assets'       => __( 'settings.devbar_assets' ),
                    'devbar_show_request'      => __( 'settings.devbar_request' ),
                    'devbar_show_environment'  => __( 'settings.devbar_environment' ),
                ];
                foreach ( $devbarToggles as $key => $label ): ?>
                    <label class="flex flex-center flex-gap-sm" style="cursor:pointer;padding:0.25rem 0">
                        <input type="checkbox" name="<?php echo $key; ?>" value="1" <?php echo ( $devConfig[$key] ?? true ) ? 'checked' : ''; ?>>
                        <?php echo klytos_esc_html( $label ); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="form-group mt-2">
                <label><?php echo __( 'settings.devbar_slow_threshold' ); ?></label>
                <div class="flex flex-center flex-gap-sm">
                    <input type="number" name="devbar_log_slow_threshold" class="form-control" style="width:120px" value="<?php echo (int) ( $devConfig['devbar_log_slow_threshold'] ?? 200 ); ?>" min="10" step="10">
                    <span class="text-sm text-muted">ms</span>
                </div>
                <p class="form-help"><?php echo __( 'settings.devbar_slow_threshold_help' ); ?></p>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><?php echo __( 'common.save' ); ?></button>
    </form>
</div>
<?php klytos_do_action('admin.settings.after_section', 'developer'); ?>
<?php endif; ?>

<?php klytos_do_action('admin.settings.render_custom_sections', $siteConfig); ?>

<?php klytos_do_action( 'admin.settings.after' ); ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>
