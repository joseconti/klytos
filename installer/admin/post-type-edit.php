<?php

/**
 * Klytos Admin — Edit Post Type
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
$currentPage = 'pt-' . $ptId;
$auth        = $app->getAuth();
$error       = '';
$success     = '';

// Get configured languages for slug_i18n fields.
$siteConfig = $app->getSiteConfig()->get();
$languages  = $siteConfig['languages'] ?? [];

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && klytos_verify_csrf()) {
    $action = $_POST['action'] ?? 'update';

    try {
        if ($action === 'update') {
            $editorValue = $_POST['editor'] ?? 'gutenberg';
            if (!in_array($editorValue, ['gutenberg', 'tinymce'], true)) {
                $editorValue = 'gutenberg';
            }

            $updateData = [
                'name'   => trim($_POST['name'] ?? ''),
                'slug'   => trim($_POST['slug'] ?? ''),
                'editor' => $editorValue,
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

        } elseif ($action === 'add_custom_field') {
            $cfOptions = [];
            if (!empty($_POST['cf_opt_value']) && is_array($_POST['cf_opt_value'])) {
                foreach ($_POST['cf_opt_value'] as $i => $optVal) {
                    $optLabel = $_POST['cf_opt_label'][$i] ?? '';
                    if (trim($optVal) !== '') {
                        $cfOptions[] = [
                            'value' => trim($optVal),
                            'label' => trim($optLabel) !== '' ? trim($optLabel) : trim($optVal),
                        ];
                    }
                }
            }

            $cfData = [
                'id'          => trim($_POST['cf_id'] ?? ''),
                'type'        => trim($_POST['cf_type'] ?? 'text'),
                'label'       => trim($_POST['cf_label'] ?? ''),
                'description' => trim($_POST['cf_description'] ?? ''),
                'placeholder' => trim($_POST['cf_placeholder'] ?? ''),
                'required'    => isset($_POST['cf_required']),
                'options'     => $cfOptions,
            ];

            $postType = $ptManager->addCustomField($ptId, $cfData);
            $success  = __('common.success');

        } elseif ($action === 'remove_custom_field') {
            $postType = $ptManager->removeCustomField($ptId, $_POST['cf_field_id'] ?? '');
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
    <div class="alert alert-success"><?php echo klytos_esc_html($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?php echo klytos_esc_html($error); ?></div>
<?php endif; ?>

<!-- Post Type Settings -->
<div class="card">
    <div class="card-header">
        <h3>Post Type: <?php echo klytos_esc_html($postType['name'] ?? $ptId); ?></h3>
        <a href="post-types.php" class="btn btn-outline btn-sm">Back</a>
    </div>
    <form method="post" class="p-3">
        <input type="hidden" name="action" value="update">
        <?php echo klytos_csrf_field(); ?>

        <div class="grid-2">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" class="form-control" value="<?php echo klytos_esc_attr($postType['name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Slug</label>
                <input type="text" name="slug" class="form-control" value="<?php echo klytos_esc_attr($postType['slug'] ?? ''); ?>" required
                    <?php echo ($postType['builtin'] ?? false) ? '' : ''; ?>>
                <p class="form-help">URL prefix. Use <code>/</code> for root (pages only).</p>
            </div>
        </div>

        <?php if (!empty($languages)): ?>
        <h4 class="mt-3 mb-1">Slug per Language</h4>
        <div class="grid-3">
            <?php foreach ($languages as $lang):
                $code = $lang['code'] ?? '';
                $langSlug = $postType['slug_i18n'][$code] ?? '';
                ?>
                <div class="form-group">
                    <label><?php echo klytos_esc_html($lang['name'] ?? $code); ?> (<?php echo klytos_esc_html($code); ?>)</label>
                    <input type="text" name="slug_i18n_<?php echo klytos_esc_attr($code); ?>" class="form-control" value="<?php echo klytos_esc_attr($langSlug); ?>" placeholder="<?php echo klytos_esc_attr($postType['slug'] ?? ''); ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Content Editor -->
        <h4 class="mt-3 mb-1"><?php echo __('editor.title'); ?></h4>
        <div class="form-group">
            <label><?php echo __('editor.choose'); ?></label>
            <div class="selection-cards cols-2 mt-1">
                <label class="selection-card">
                    <input type="radio" name="editor" value="gutenberg" <?php echo ($postType['editor'] ?? 'gutenberg') === 'gutenberg' ? 'checked' : ''; ?>>
                    <div class="selection-card-body">
                        <span class="selection-card-title">Gutenberg</span>
                        <span class="selection-card-desc"><?php echo __('editor.gutenberg_desc'); ?></span>
                    </div>
                </label>
                <label class="selection-card">
                    <input type="radio" name="editor" value="tinymce" <?php echo ($postType['editor'] ?? 'gutenberg') === 'tinymce' ? 'checked' : ''; ?>>
                    <div class="selection-card-body">
                        <span class="selection-card-title">TinyMCE</span>
                        <span class="selection-card-desc"><?php echo __('editor.tinymce_desc'); ?></span>
                    </div>
                </label>
            </div>
        </div>

        <button type="submit" class="btn btn-primary"><?php echo __('common.save'); ?></button>
    </form>
</div>

<!-- Taxonomies -->
<div class="card mt-3">
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
                    <td class="mono"><?php echo klytos_esc_html($tax['id'] ?? ''); ?></td>
                    <td><?php echo klytos_esc_html($tax['name'] ?? ''); ?></td>
                    <td class="mono"><?php echo klytos_esc_html($tax['slug'] ?? ''); ?></td>
                    <td>
                        <span class="badge-status badge-<?php echo ($tax['hierarchical'] ?? false) ? 'published' : 'draft'; ?>">
                            <?php echo ($tax['hierarchical'] ?? false) ? 'Yes' : 'No'; ?>
                        </span>
                    </td>
                    <td class="flex-center flex-gap-sm">
                        <a href="taxonomy.php?post_type=<?php echo urlencode($ptId); ?>&taxonomy=<?php echo urlencode($tax['id'] ?? ''); ?>" class="btn btn-outline btn-sm">Terms</a>
                        <form method="post" class="inline-form form-confirm-delete">
                            <input type="hidden" name="action" value="remove_taxonomy">
                            <input type="hidden" name="tax_id" value="<?php echo klytos_esc_attr($tax['id'] ?? ''); ?>">
                            <?php echo klytos_csrf_field(); ?>
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

    <form method="post" class="p-3 border-t">
        <h4 class="mb-2">Add Taxonomy</h4>
        <input type="hidden" name="action" value="add_taxonomy">
        <?php echo klytos_csrf_field(); ?>
        <div class="grid-3">
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
        <button type="submit" class="btn btn-primary">Add Taxonomy</button>
    </form>
</div>

<!-- Custom Fields -->
<?php
$customFields = $postType['custom_fields'] ?? [];
usort($customFields, fn(array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

$fieldTypeGroups = [
    'Text'      => ['text', 'textarea', 'richtext', 'code', 'password'],
    'Number'    => ['number', 'range'],
    'Date/Time' => ['date', 'datetime', 'time'],
    'Choice'    => ['select', 'multiselect', 'checkbox', 'checkbox_group', 'radio', 'toggle'],
    'Media'     => ['image', 'file', 'gallery'],
    'Data'      => ['email', 'url', 'phone', 'color', 'json'],
    'Advanced'  => ['repeater', 'relationship'],
];

$optionTypes = ['select', 'multiselect', 'radio', 'checkbox_group'];
?>
<div class="card mt-3">
    <div class="card-header">
        <h3>Custom Fields (<?php echo count($customFields); ?>)</h3>
    </div>

    <?php if (!empty($customFields)): ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Label</th>
                    <th>Type</th>
                    <th>Required</th>
                    <th><?php echo __('common.actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customFields as $cf): ?>
                <tr>
                    <td class="mono"><?php echo klytos_esc_html($cf['id'] ?? ''); ?></td>
                    <td><?php echo klytos_esc_html($cf['label'] ?? ''); ?></td>
                    <td><span class="badge-status badge-published"><?php echo klytos_esc_html($cf['type'] ?? ''); ?></span></td>
                    <td>
                        <span class="badge-status badge-<?php echo ($cf['required'] ?? false) ? 'published' : 'draft'; ?>">
                            <?php echo ($cf['required'] ?? false) ? 'Yes' : 'No'; ?>
                        </span>
                    </td>
                    <td class="flex-center flex-gap-sm">
                        <?php if (!empty($cf['options'])): ?>
                            <span class="text-sm text-muted"><?php echo count($cf['options']); ?> opts</span>
                        <?php endif; ?>
                        <form method="post" class="inline-form form-confirm-delete">
                            <input type="hidden" name="action" value="remove_custom_field">
                            <input type="hidden" name="cf_field_id" value="<?php echo klytos_esc_attr($cf['id'] ?? ''); ?>">
                            <?php echo klytos_csrf_field(); ?>
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
        <p>No custom fields defined for this post type.</p>
    </div>
    <?php endif; ?>

    <form method="post" class="p-3 border-t">
        <h4 class="mb-2">Add Custom Field</h4>
        <input type="hidden" name="action" value="add_custom_field">
        <?php echo klytos_csrf_field(); ?>

        <div class="grid-3">
            <div class="form-group">
                <label>ID</label>
                <input type="text" name="cf_id" class="form-control" required pattern="[a-z0-9_-]+" placeholder="e.g. price">
                <p class="form-help">Lowercase, hyphens/underscores only.</p>
            </div>
            <div class="form-group">
                <label>Label</label>
                <input type="text" name="cf_label" class="form-control" required placeholder="e.g. Price">
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="cf_type" class="form-control" id="cf-type-select" required>
                    <?php foreach ($fieldTypeGroups as $group => $types): ?>
                        <optgroup label="<?php echo klytos_esc_attr($group); ?>">
                            <?php foreach ($types as $t): ?>
                                <option value="<?php echo klytos_esc_attr($t); ?>"><?php echo klytos_esc_html($t); ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="cf_description" class="form-control" placeholder="Help text shown below the field">
            </div>
            <div class="form-group">
                <label>Placeholder</label>
                <input type="text" name="cf_placeholder" class="form-control" placeholder="Placeholder text">
            </div>
        </div>

        <div class="form-group">
            <label><input type="checkbox" name="cf_required" value="1"> Required</label>
        </div>

        <!-- Options section (for select, multiselect, radio, checkbox_group) -->
        <div id="cf-options-section" class="hidden mt-2 p-2 rounded" style="background:var(--klytos-bg);">
            <h5 class="mb-1">Options</h5>
            <div id="cf-options-list"></div>
            <button type="button" class="btn btn-outline btn-sm mt-1" id="btn-add-option">+ Add Option</button>
        </div>

        <button type="submit" class="btn btn-primary mt-2">Add Custom Field</button>
    </form>
</div>

<script nonce="<?php echo $cspNonce; ?>">
(function() {
    document.querySelectorAll('.form-confirm-delete').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to remove this?')) {
                e.preventDefault();
            }
        });
    });

    // Show/hide options section based on field type.
    var typeSelect = document.getElementById('cf-type-select');
    var optionsSection = document.getElementById('cf-options-section');
    var optionTypes = <?php echo json_encode($optionTypes); ?>;

    function updateOptionsVisibility() {
        if (optionTypes.indexOf(typeSelect.value) !== -1) {
            optionsSection.style.display = 'block';
        } else {
            optionsSection.style.display = 'none';
        }
    }

    typeSelect.addEventListener('change', updateOptionsVisibility);
    updateOptionsVisibility();

    /* Add option rows */
    var addOptionBtn = document.getElementById('btn-add-option');
    if (addOptionBtn) {
        addOptionBtn.addEventListener('click', function() {
            var list = document.getElementById('cf-options-list');
            var div = document.createElement('div');
            div.className = 'flex-center flex-gap-sm mb-1';
            div.innerHTML = '<input type="text" name="cf_opt_value[]" class="form-control flex-1" placeholder="Value" required>' +
                            '<input type="text" name="cf_opt_label[]" class="form-control flex-1" placeholder="Label">' +
                            '<button type="button" class="btn btn-danger btn-sm btn-remove-option">×</button>';
            list.appendChild(div);
            div.querySelector('.btn-remove-option').addEventListener('click', function() {
                div.remove();
            });
        });
    }
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
