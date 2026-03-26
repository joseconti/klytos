<?php
/**
 * Klytos Admin — AI Image Generator
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

use Klytos\Core\AiImageGenerator;
use Klytos\Core\Helpers;

$pageTitle = __( 'ai_images.title' );
$auth      = $app->getAuth();
$success   = '';
$error     = '';
$generated = null;

$generator = new AiImageGenerator(
    $app->getStorage(),
    $app->getAssets(),
    $app->getConfigPath()
);

// Handle generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $prompt = trim($_POST['prompt'] ?? '');
    $model  = $_POST['model'] ?? '';

    if (empty($prompt)) {
        $error = __( 'ai_images.prompt' ) . ' is required.';
    } elseif (!$generator->isConfigured()) {
        $error = __( 'ai_images.no_api_key' );
    } else {
        try {
            $options = [];
            if (!empty($model)) $options['model'] = $model;
            if (!empty($_POST['filename'])) $options['filename'] = $_POST['filename'];

            $generated = $generator->generate($prompt, $options);
            $success   = __( 'ai_images.generated' );
        } catch (\RuntimeException $e) {
            $error = __( 'ai_images.error', ['error' => $e->getMessage()]);
        }
    }
}

$history = $generator->getHistory(20);
$models  = $generator->getAvailableModels();
$csrf    = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars( $success ); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars( $error ); ?></div>
<?php endif; ?>

<?php if (!$generator->isConfigured()): ?>
    <div class="alert alert-warning">
        <?php echo __( 'ai_images.no_api_key' ); ?>
        — <a href="settings.php"><?php echo __( 'settings.title' ); ?></a>
    </div>
<?php endif; ?>

<!-- Generated Image Result -->
<?php if ($generated): ?>
<div class="card">
    <div class="card-header"><h3><?php echo __( 'ai_images.generated' ); ?></h3></div>
    <div style="text-align:center;">
        <img src="<?php echo htmlspecialchars(Helpers::url($generated['asset']['path'] ?? '')); ?>" alt="AI Generated" style="max-width:100%;border-radius:8px;margin-bottom:1rem;">
        <p class="mono" style="font-size:0.85rem;color:var(--admin-text-muted);"><?php echo htmlspecialchars( $generated['asset']['path'] ?? ''); ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Generator Form -->
<div class="card">
    <div class="card-header"><h3><?php echo __( 'ai_images.generate' ); ?></h3></div>
    <form method="post">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <div class="form-group">
            <label><?php echo __( 'ai_images.prompt' ); ?></label>
            <textarea name="prompt" class="form-control" rows="4" required placeholder="<?php echo __( 'ai_images.prompt' ); ?>"><?php echo htmlspecialchars( $_POST['prompt'] ?? ''); ?></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label><?php echo __( 'ai_images.model' ); ?></label>
                <select name="model" class="form-control">
                    <?php foreach ($models as $model): ?>
                        <option value="<?php echo htmlspecialchars( $model['id'] ); ?>"><?php echo htmlspecialchars( $model['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Filename (<?php echo __( 'common.optional' ); ?>)</label>
                <input type="text" name="filename" class="form-control" placeholder="auto-generated">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" <?php echo !$generator->isConfigured() ? 'disabled' : ''; ?>><?php echo __( 'ai_images.generate' ); ?></button>
    </form>
</div>

<!-- History -->
<?php if (!empty($history)): ?>
<div class="card">
    <div class="card-header"><h3><?php echo __( 'ai_images.history' ); ?></h3></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?php echo __( 'ai_images.prompt' ); ?></th>
                    <th><?php echo __( 'ai_images.model' ); ?></th>
                    <th>File</th>
                    <th><?php echo __( 'common.date' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $item): ?>
                <tr>
                    <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars( $item['prompt'] ?? ''); ?></td>
                    <td class="mono" style="font-size:0.8rem;"><?php echo htmlspecialchars( $item['model'] ?? ''); ?></td>
                    <td class="mono" style="font-size:0.8rem;"><?php echo htmlspecialchars( $item['filename'] ?? ''); ?></td>
                    <td><?php echo !empty($item['created_at']) ? date( 'Y-m-d H:i', strtotime($item['created_at'])) : ''; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
