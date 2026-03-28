<?php
/**
 * Klytos Admin — Edit Post Type
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

$ptId = $_GET['id'] ?? '';
if ($ptId === '') {
    header('Location: post-types.php');
    exit;
}

$ptManager = $app->getPostTypeManager();

try {
    $postType = $ptManager->get($ptId);
} catch (\RuntimeException $e) {
    header('Location: post-types.php');
    exit;
}

$pageTitle   = 'Edit: ' . ($postType['name'] ?? $ptId);
$currentPage = 'post-types';
$auth        = $app->getAuth();
$error       = '';
$success     = '';

// Get configured languages for slug_i18n fields.
$siteConfig = $app->getSiteConfig()->get();
$languages  = $siteConfig['languages'] ?? [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCsrf($_POST['csrf'] ?? '')) {
    $action = $_POST['action'] ?? 'update';

    try {
        if ($action === 'update') {
            $updateData = [
                'name' => trim($_POST['name'] ?? ''),
                'slug' => trim($_POST['slug'] ?? ''),
            ];

            // Build slug_i18n from posted language fields.
            $slugI18n = [];
            foreach ($languages as $lang) {
                $code = $lang['code'] ?? '';
                $val  = trim($_POST['slug_i18n_' . $code] ?? '');
                if ($code !== '' && $val !== '') {
                    $slugI18n[$code] = $val;
                }
            }
            $updateData['slug_i18n'] = $slugI18n;

            $postType = $ptManager->update($ptId, $updateData);
            $success  = __('common.success');

        } elseif ($action === 'add_taxonomy') {
            $postType = $ptManager->addTaxonomy($ptId, [
                'id'           => trim($_POST['tax_id'] ?? ''),
                'name'         => trim($_POST['tax_name'] ?? ''),
                'slug'         => trim($_POST['tax_slug'] ?? ''),
                'hierarchical' => isset($_POST['hierarchical']),
            ]);
            $success = __('common.success');

        } elseif ($action === 'remove_taxonomy') {
            $postType = $ptManager->removeTaxonomy($ptId, $_POST['tax_id'] ?? '');
            $success  = __('common.success');
        }
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

$csrf = $auth->getCsrfToken();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Post Type Settings -->
<div class="card">
    <div class="card-header">
        <h3>Post Type: <?php echo htmlspecialchars($postType['name'] ?? $ptId); ?></h3>
        <a href="post-types.php" class="btn btn-outline btn-sm">Back</a>
    </div>
    <form method="post" style="padding:1.5rem;">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($postType['name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($postType['slug'] ?? ''); ?>" required
                    <?php echo ($postType['builtin'] ?? false) ? '' : ''; ?>>
                <p class="form-help">URL prefix. Use <code>/</code> for root (pages only).</p>
            </div>
        </div>

        <?php if (!empty($languages)): ?>
        <h4 style="margin-top:1.5rem;margin-bottom:0.5rem;">Slug per Language</h4>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:1rem;">
            <?php foreach ($languages as $lang):
                $code = $lang['code'] ?? '';
                $langSlug = $postType['slug_i18n'][$code] ?? '';
            ?>
                <div class="form-group">
                    <label><?php echo htmlspecialchars($lang['name'] ?? $code); ?> (<?php echo htmlspecialchars($code); ?>)</label>
                    <input type="text" name="slug_i18n_<?php echo htmlspecialchars($code); ?>" class="form-control" value="<?php echo htmlspecialchars($langSlug); ?>" placeholder="<?php echo htmlspecialchars($postType['slug'] ?? ''); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary"><?php echo __('common.save'); ?></button>
    </form>
</div>

<!-- Taxonomies -->
<div class="card" style="margin-top:1.5rem;">
    <div class="card-header">
        <h3>Taxonomies (<?php echo count($postType['taxonomies'] ?? []); ?>)</h3>
    </div>

    <?php $taxonomies = $postType['taxonomies'] ?? []; ?>

    <?php if (!empty($taxonomies)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Hierarchical</th>
                    <th><?php echo __('common.actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($taxonomies as $tax): ?>
                <tr>
                    <td class="mono"><?php echo htmlspecialchars($tax['id'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($tax['name'] ?? ''); ?></td>
                    <td class="mono"><?php echo htmlspecialchars($tax['slug'] ?? ''); ?></td>
                    <td>
                        <span class="badge-status badge-<?php echo ($tax['hierarchical'] ?? false) ? 'published' : 'draft'; ?>">
                            <?php echo ($tax['hierarchical'] ?? false) ? 'Yes' : 'No'; ?>
                        </span>
                    </td>
                    <td style="display:flex;gap:0.5rem;align-items:center;">
                        <a href="taxonomy.php?post_type=<?php echo urlencode($ptId); ?>&taxonomy=<?php echo urlencode($tax['id'] ?? ''); ?>" class="btn btn-outline btn-sm">Terms</a>
                        <form method="post" style="display:inline;" class="form-confirm-delete">
                            <input type="hidden" name="action" value="remove_taxonomy">
                            <input type="hidden" name="tax_id" value="<?php echo htmlspecialchars($tax['id'] ?? ''); ?>">
                            <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p>No taxonomies registered for this post type.</p>
    </div>
    <?php endif; ?>

    <form method="post" style="padding:1.5rem;border-top:1px solid var(--admin-border, #e2e8f0);">
        <h4 style="margin-bottom:1rem;">Add Taxonomy</h4>
        <input type="hidden" name="action" value="add_taxonomy">
        <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label>ID</label>
                <input type="text" name="tax_id" class="form-control" required pattern="[a-z0-9_-]+" placeholder="e.g. color">
            </div>
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="tax_name" class="form-control" required placeholder="e.g. Colors">
            </div>
            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="tax_slug" class="form-control" required placeholder="e.g. colors">
            </div>
        </div>
        <div class="form-group">
            <label><input type="checkbox" name="hierarchical" value="1"> Hierarchical</label>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Add Taxonomy</button>
    </form>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    document.querySelectorAll('.form-confirm-delete').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to remove this taxonomy?')) {
                e.preventDefault();
            }
        });
    });
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
