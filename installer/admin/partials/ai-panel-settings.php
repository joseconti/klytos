<?php
/**
 * Klytos — AI Panel: Settings
 * Settings content rendered inside the AI chat interface.
 *
 * @package Klytos
 * @since   0.9.0
 */

if (!isset($app)) { return; }

$settingsSuccess = '';
$siteConfig = $app->getSiteConfig()->get();
$csrf = $app->getAuth()->getCsrfToken();
$tab = $_GET['tab'] ?? 'general';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_panel_section']) && klytos_verify_csrf()) {
    $section = $_POST['ai_panel_section'];

    if ($section === 'general') {
        $app->getSiteConfig()->set([
            'site_name'        => trim($_POST['site_name'] ?? ''),
            'tagline'          => trim($_POST['tagline'] ?? ''),
            'description'      => trim($_POST['description'] ?? ''),
            'default_language' => $_POST['default_language'] ?? 'es',
        ]);
        $settingsSuccess = __('common.success');
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
        $settingsSuccess = __('common.success');
    } elseif ($section === 'analytics') {
        $app->getSiteConfig()->set([
            'analytics' => [
                'google_analytics_id' => trim($_POST['google_analytics_id'] ?? ''),
                'custom_head_scripts' => $_POST['custom_head_scripts'] ?? '',
            ],
        ]);
        $settingsSuccess = __('common.success');
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
        $settingsSuccess = __('common.success');
    } elseif ($section === 'editor') {
        $editorValue = $_POST['editor'] ?? 'gutenberg';
        if (!in_array($editorValue, ['gutenberg', 'tinymce'], true)) {
            $editorValue = 'gutenberg';
        }
        $app->getSiteConfig()->set(['editor' => $editorValue]);
        $settingsSuccess = __('common.success');
    }

    // Reload config after save
    $siteConfig = $app->getSiteConfig()->get();
}

$tabs = [
    'general'   => __('settings.title'),
    'social'    => __('settings.social'),
    'analytics' => __('settings.analytics'),
    'email'     => __('settings.email_title'),
    'editor'    => __('editor.title'),
];

$panelUrl = klytos_esc_url($basePath . 'admin/ai-chat.php?panel=settings');
?>

<div class="ai-chat-panel">
    <div class="ai-chat-panel-sidebar">
        <a href="<?php echo klytos_esc_url($basePath . 'admin/ai-chat.php'); ?>" class="ai-chat-panel-back">
            <i class="fa-solid fa-chevron-left"></i> <?php echo klytos_esc_html(__('ai_chat.chats')); ?>
        </a>
        <div class="ai-chat-panel-title"><?php echo klytos_esc_html(__('ai_chat.settings')); ?></div>
        <?php foreach ($tabs as $tabKey => $tabLabel): ?>
            <a href="<?php echo $panelUrl; ?>&tab=<?php echo klytos_esc_attr($tabKey); ?>"
               class="ai-chat-panel-tab <?php echo $tab === $tabKey ? 'active' : ''; ?>">
                <?php echo klytos_esc_html($tabLabel); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="ai-chat-panel-content">

        <?php if ($settingsSuccess): ?>
            <div class="ai-panel-alert ai-panel-alert-success"><?php echo klytos_esc_html($settingsSuccess); ?></div>
        <?php endif; ?>

        <?php if ($tab === 'general'): ?>
            <h2><?php echo klytos_esc_html(__('settings.title')); ?></h2>
            <form method="post" action="<?php echo $panelUrl; ?>&tab=general">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="ai_panel_section" value="general">
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.site_name')); ?></label>
                    <input type="text" name="site_name" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['site_name'] ?? ''); ?>">
                </div>
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.tagline')); ?></label>
                    <input type="text" name="tagline" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['tagline'] ?? ''); ?>">
                </div>
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.site_description')); ?></label>
                    <textarea name="description" class="ai-panel-form-control"><?php echo klytos_esc_html($siteConfig['description'] ?? ''); ?></textarea>
                </div>
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.default_language')); ?></label>
                    <select name="default_language" class="ai-panel-form-control">
                        <option value="es" <?php echo ($siteConfig['default_language'] ?? '') === 'es' ? 'selected' : ''; ?>>Espanol</option>
                        <option value="en" <?php echo ($siteConfig['default_language'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                        <option value="ca" <?php echo ($siteConfig['default_language'] ?? '') === 'ca' ? 'selected' : ''; ?>>Catala</option>
                        <option value="fr" <?php echo ($siteConfig['default_language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Francais</option>
                    </select>
                </div>
                <button type="submit" class="ai-panel-btn ai-panel-btn-primary"><?php echo klytos_esc_html(__('common.save')); ?></button>
            </form>

        <?php elseif ($tab === 'social'): ?>
            <h2><?php echo klytos_esc_html(__('settings.social')); ?></h2>
            <form method="post" action="<?php echo $panelUrl; ?>&tab=social">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="ai_panel_section" value="social">
                <div class="ai-panel-grid-2">
                    <?php foreach (['twitter','github','linkedin','instagram','youtube','mastodon'] as $social): ?>
                        <div class="ai-panel-form-group">
                            <label><?php echo ucfirst($social); ?></label>
                            <input type="text" name="<?php echo $social; ?>" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['social'][$social] ?? ''); ?>" placeholder="https://...">
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="ai-panel-btn ai-panel-btn-primary"><?php echo klytos_esc_html(__('common.save')); ?></button>
            </form>

        <?php elseif ($tab === 'analytics'): ?>
            <h2><?php echo klytos_esc_html(__('settings.analytics')); ?></h2>
            <form method="post" action="<?php echo $panelUrl; ?>&tab=analytics">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="ai_panel_section" value="analytics">
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.google_analytics_id')); ?></label>
                    <input type="text" name="google_analytics_id" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['analytics']['google_analytics_id'] ?? ''); ?>" placeholder="G-XXXXXXXXXX">
                </div>
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.custom_head_scripts')); ?></label>
                    <textarea name="custom_head_scripts" class="ai-panel-form-control" rows="4"><?php echo klytos_esc_html($siteConfig['analytics']['custom_head_scripts'] ?? ''); ?></textarea>
                </div>
                <button type="submit" class="ai-panel-btn ai-panel-btn-primary"><?php echo klytos_esc_html(__('common.save')); ?></button>
            </form>

        <?php elseif ($tab === 'email'): ?>
            <h2><?php echo klytos_esc_html(__('settings.email_title')); ?></h2>
            <form method="post" action="<?php echo $panelUrl; ?>&tab=email">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="ai_panel_section" value="email">
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.email_transport')); ?></label>
                    <select name="email_transport" class="ai-panel-form-control">
                        <option value="mail" <?php echo ($siteConfig['email']['transport'] ?? '') === 'mail' ? 'selected' : ''; ?>>PHP mail()</option>
                        <option value="smtp" <?php echo ($siteConfig['email']['transport'] ?? '') === 'smtp' ? 'selected' : ''; ?>>SMTP</option>
                    </select>
                </div>
                <div class="ai-panel-grid-2">
                    <div class="ai-panel-form-group">
                        <label><?php echo klytos_esc_html(__('settings.email_from_name')); ?></label>
                        <input type="text" name="email_from_name" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['from_name'] ?? ''); ?>">
                    </div>
                    <div class="ai-panel-form-group">
                        <label><?php echo klytos_esc_html(__('settings.email_from_email')); ?></label>
                        <input type="email" name="email_from_email" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['from_email'] ?? ''); ?>">
                    </div>
                </div>
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('settings.email_reply_to')); ?></label>
                    <input type="email" name="email_reply_to" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['reply_to'] ?? ''); ?>" placeholder="<?php echo klytos_esc_attr(__('common.optional')); ?>">
                </div>
                <div class="ai-panel-grid-2">
                    <div class="ai-panel-form-group">
                        <label><?php echo klytos_esc_html(__('settings.smtp_host')); ?></label>
                        <input type="text" name="smtp_host" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['smtp_host'] ?? ''); ?>" placeholder="smtp.example.com">
                    </div>
                    <div class="ai-panel-form-group">
                        <label><?php echo klytos_esc_html(__('settings.smtp_port')); ?></label>
                        <input type="number" name="smtp_port" class="ai-panel-form-control" value="<?php echo (int)($siteConfig['email']['smtp_port'] ?? 587); ?>">
                    </div>
                </div>
                <div class="ai-panel-grid-3">
                    <div class="ai-panel-form-group">
                        <label><?php echo klytos_esc_html(__('settings.smtp_user')); ?></label>
                        <input type="text" name="smtp_user" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['smtp_user'] ?? ''); ?>">
                    </div>
                    <div class="ai-panel-form-group">
                        <label><?php echo klytos_esc_html(__('settings.smtp_pass')); ?></label>
                        <input type="password" name="smtp_pass" class="ai-panel-form-control" value="<?php echo klytos_esc_attr($siteConfig['email']['smtp_pass'] ?? ''); ?>">
                    </div>
                    <div class="ai-panel-form-group">
                        <label><?php echo klytos_esc_html(__('settings.smtp_security')); ?></label>
                        <select name="smtp_security" class="ai-panel-form-control">
                            <option value="tls" <?php echo ($siteConfig['email']['smtp_security'] ?? '') === 'tls' ? 'selected' : ''; ?>>STARTTLS (587)</option>
                            <option value="ssl" <?php echo ($siteConfig['email']['smtp_security'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL/TLS (465)</option>
                            <option value="" <?php echo ($siteConfig['email']['smtp_security'] ?? 'tls') === '' ? 'selected' : ''; ?>><?php echo klytos_esc_html(__('settings.smtp_none')); ?></option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="ai-panel-btn ai-panel-btn-primary"><?php echo klytos_esc_html(__('common.save')); ?></button>
            </form>

        <?php elseif ($tab === 'editor'): ?>
            <h2><?php echo klytos_esc_html(__('editor.title')); ?></h2>
            <form method="post" action="<?php echo $panelUrl; ?>&tab=editor">
                <?php echo klytos_csrf_field(); ?>
                <input type="hidden" name="ai_panel_section" value="editor">
                <div class="ai-panel-form-group">
                    <label><?php echo klytos_esc_html(__('editor.choose')); ?></label>
                    <div class="ai-panel-grid-2" style="margin-top:0.5rem;">
                        <label class="ai-panel-card" style="cursor:pointer;border:2px solid <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'gutenberg' ? 'var(--chat-accent)' : 'var(--chat-border)'; ?>;">
                            <input type="radio" name="editor" value="gutenberg" <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'gutenberg' ? 'checked' : ''; ?> style="margin-right:0.5rem;">
                            <strong>Gutenberg</strong>
                            <p style="margin:0.5rem 0 0;font-size:0.82rem;color:var(--chat-text-muted);"><?php echo klytos_esc_html(__('editor.gutenberg_desc')); ?></p>
                        </label>
                        <label class="ai-panel-card" style="cursor:pointer;border:2px solid <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'tinymce' ? 'var(--chat-accent)' : 'var(--chat-border)'; ?>;">
                            <input type="radio" name="editor" value="tinymce" <?php echo ($siteConfig['editor'] ?? 'gutenberg') === 'tinymce' ? 'checked' : ''; ?> style="margin-right:0.5rem;">
                            <strong>TinyMCE</strong>
                            <p style="margin:0.5rem 0 0;font-size:0.82rem;color:var(--chat-text-muted);"><?php echo klytos_esc_html(__('editor.tinymce_desc')); ?></p>
                        </label>
                    </div>
                </div>
                <button type="submit" class="ai-panel-btn ai-panel-btn-primary"><?php echo klytos_esc_html(__('common.save')); ?></button>
            </form>

        <?php endif; ?>

    </div>
</div>
