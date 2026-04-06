<?php

/**
 * Klytos Admin — Translations Manager
 * View, edit, and auto-translate all translation keys across core, plugins, and templates.
 *
 * @package Klytos
 * @since   0.19.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Klytos\Core\Helpers;
use Klytos\Core\TranslationManager;
use Klytos\Core\Ai\AiKeyManager;

$pageTitle = __( 'translations.title' );

// ─── Permission check ────────────────────────────────────────
if ( !klytos_has_permission( 'site.configure' ) ) {
    header( 'Location: ' . Helpers::url( 'admin/' ) );
    exit;
}

$auth  = $app->getAuth();
$csrf  = $auth->getCsrfToken();
$tm    = new TranslationManager( $app );

// ─── Languages & Sources ─────────────────────────────────────
$languages = $tm->getConfiguredLanguages();
$sources   = $tm->getSources();

// Filter out English from editable languages.
$editableLanguages = array_filter( $languages, function ( $lang ) {
    return $lang['code'] !== 'en';
} );
$editableLanguages = array_values( $editableLanguages );

// Selected source and locale from query params.
$selectedSource = $_GET['source'] ?? 'core';
$selectedLocale = $_GET['locale'] ?? ( $editableLanguages[0]['code'] ?? '' );

// Sanitize selections.
$validSourceIds = array_column( $sources, 'id' );
if ( !in_array( $selectedSource, $validSourceIds, true ) ) {
    $selectedSource = 'core';
}
$validLocaleCodes = array_column( $editableLanguages, 'code' );
if ( !in_array( $selectedLocale, $validLocaleCodes, true ) ) {
    $selectedLocale = $validLocaleCodes[0] ?? '';
}

// ─── Load translation data ──────────────────────────────────
$referenceKeys  = [];
$translations   = [];
$stats          = ['total' => 0, 'translated' => 0, 'missing' => 0, 'percent' => 0];
$hasData        = false;

if ( $selectedLocale !== '' ) {
    $referenceKeys = $tm->getReferenceKeys( $selectedSource );
    $translations  = $tm->getTranslations( $selectedSource, $selectedLocale );
    $total         = count( $referenceKeys );
    $translated    = count( $translations );
    $missing       = max( 0, $total - $translated );
    $percent       = $total > 0 ? round( ( $translated / $total ) * 100 ) : 0;
    $stats         = compact( 'total', 'translated', 'missing', 'percent' );
    $hasData       = $total > 0;
}

// ─── AI providers ────────────────────────────────────────────
$aiProviders = [];
try {
    $aiKeys       = new AiKeyManager( $app->getStorage(), $app->getConfigPath() );
    $allProviders = $aiKeys->listProviders();
    $aiProviders  = array_filter( $allProviders, function ( $p ) {
        return $p['configured'] === true;
    } );
    $aiProviders  = array_values( $aiProviders );
} catch ( \Throwable $e ) {
    // AI not available — proceed without.
}

// Get locale name for column header.
$localeName = $selectedLocale;
foreach ( $languages as $lang ) {
    if ( $lang['code'] === $selectedLocale ) {
        $localeName = $lang['name'] ?? $selectedLocale;
        break;
    }
}

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/templates/sidebar.php';
?>

<?php klytos_do_action( 'admin.translations.before' ); ?>

<?php if ( empty( $editableLanguages ) ): ?>
    <div class="alert alert-warning">
        <?php echo __( 'translations.no_languages' ); ?>
    </div>
<?php else: ?>

<?php klytos_do_action( 'admin.translations.before_filters' ); ?>

<!-- Filter bar -->
<div class="card flex flex-gap-sm flex-wrap flex-center" style="padding:1rem;margin-bottom:1.5rem;">
    <!-- Source dropdown -->
    <div>
        <label for="translationSource" class="text-sm text-muted" style="display:block;margin-bottom:0.25rem;">
            <?php echo __( 'translations.source' ); ?>
        </label>
        <select id="translationSource" class="form-control" style="min-width:180px;">
            <?php foreach ( $sources as $source ): ?>
                <option value="<?php echo klytos_esc_attr( $source['id'] ); ?>"
                    <?php echo $source['id'] === $selectedSource ? 'selected' : ''; ?>>
                    <?php echo klytos_esc_html( $source['name'] ); ?>
                    (<?php echo klytos_esc_html( ucfirst( $source['type'] ) ); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Language dropdown -->
    <div>
        <label for="translationLocale" class="text-sm text-muted" style="display:block;margin-bottom:0.25rem;">
            <?php echo __( 'translations.language' ); ?>
        </label>
        <select id="translationLocale" class="form-control" style="min-width:160px;">
            <?php foreach ( $editableLanguages as $lang ): ?>
                <option value="<?php echo klytos_esc_attr( $lang['code'] ); ?>"
                    <?php echo $lang['code'] === $selectedLocale ? 'selected' : ''; ?>>
                    <?php echo klytos_esc_html( $lang['name'] ?? $lang['code'] ); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Search -->
    <div class="flex-1" style="min-width:200px;">
        <label for="translationSearch" class="text-sm text-muted" style="display:block;margin-bottom:0.25rem;">
            &nbsp;
        </label>
        <input type="text" id="translationSearch" class="form-control"
               placeholder="<?php echo klytos_esc_attr( __( 'translations.search_placeholder' ) ); ?>"
               class="w-full">
    </div>

    <!-- Show only missing toggle -->
    <div class="flex" style="align-items:flex-end;">
        <label class="flex flex-center flex-gap-sm text-sm" style="padding-bottom:0.35rem;">
            <input type="checkbox" id="showMissingOnly">
            <?php echo __( 'translations.show_missing_only' ); ?>
        </label>
    </div>

    <!-- Progress bar -->
    <div style="min-width:220px;">
        <div class="text-sm text-muted" style="margin-bottom:0.35rem;" id="progressText">
            <?php echo __( 'translations.progress', [
                'translated' => $stats['translated'],
                'total'      => $stats['total'],
                'percent'    => $stats['percent'],
            ] ); ?>
        </div>
        <div style="background:var(--klytos-border);border-radius:4px;height:8px;overflow:hidden;">
            <div id="progressBar" style="background:var(--klytos-accent);height:100%;border-radius:4px;transition:width 0.3s;width:<?php echo (int) $stats['percent']; ?>%;"></div>
        </div>
    </div>

    <?php if ( !empty( $aiProviders ) ): ?>
    <!-- Translate all missing button -->
    <div class="flex" style="align-items:flex-end;">
        <div class="dropdown" style="position:relative;">
            <button type="button" class="btn btn-sm btn-primary" id="translateAllBtn"
                    <?php echo $stats['missing'] === 0 ? 'disabled' : ''; ?>>
                <i class="fa-solid fa-language"></i>
                <?php echo __( 'translations.translate_all_missing' ); ?>
            </button>
            <div class="dropdown-menu" id="translateAllMenu" style="display:none;position:absolute;right:0;top:100%;background:var(--klytos-card-bg);border:1px solid var(--klytos-border);border-radius:var(--klytos-radius, 6px);box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:100;min-width:180px;">
                <?php foreach ( $aiProviders as $provider ): ?>
                    <button type="button" class="dropdown-item js-translate-all-provider"
                            data-provider="<?php echo klytos_esc_attr( $provider['id'] ); ?>"
                            style="display:block;width:100%;text-align:left;padding:0.5rem 1rem;border:none;background:none;cursor:pointer;color:var(--klytos-text);font-size:0.9rem;">
                        <?php echo klytos_esc_html( $provider['name'] ); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php klytos_do_action( 'admin.translations.after_filters' ); ?>

<?php klytos_do_action( 'admin.translations.before_table' ); ?>

<?php if ( !$hasData ): ?>
    <div class="card p-3 text-center text-muted">
        <?php echo __( 'translations.all_translated' ); ?>
    </div>
<?php else: ?>

<!-- Bulk progress indicator (hidden by default) -->
<div id="bulkProgress" class="hidden mb-2">
    <div class="card p-2">
        <div class="flex flex-center" style="gap:1rem;">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <span id="bulkProgressText"><?php echo __( 'translations.translating' ); ?></span>
            <div class="flex-1" style="background:var(--klytos-border);border-radius:4px;height:8px;overflow:hidden;">
                <div id="bulkProgressBar" style="background:var(--klytos-accent);height:100%;border-radius:4px;transition:width 0.3s;width:0%;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Translations table -->
<div class="card">
    <div class="table-responsive">
        <table class="table" id="translationsTable">
            <thead>
                <tr>
                    <th class="text-sm" style="width:25%;"><?php echo __( 'translations.key' ); ?></th>
                    <th style="width:30%;"><?php echo __( 'translations.english' ); ?></th>
                    <th style="width:45%;"><?php echo klytos_esc_html( $localeName ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $referenceKeys as $key => $englishValue ): ?>
                    <?php
                    $currentTranslation = $translations[$key] ?? '';
                    $isMissing          = $currentTranslation === '';
                    $rowActions         = klytos_apply_filters( 'admin.translations.row_actions', '', $key, $selectedSource, $selectedLocale );
                    ?>
                    <tr data-key="<?php echo klytos_esc_attr( $key ); ?>"
                        data-english="<?php echo klytos_esc_attr( strtolower( $englishValue ) ); ?>"
                        data-translation="<?php echo klytos_esc_attr( strtolower( $currentTranslation ) ); ?>"
                        class="<?php echo $isMissing ? 'translation-missing' : 'translation-done'; ?>">
                        <td class="text-mono text-xs text-muted break-all">
                            <?php echo klytos_esc_html( $key ); ?>
                        </td>
                        <td class="text-sm" style="background:var(--klytos-bg);">
                            <?php echo klytos_esc_html( $englishValue ); ?>
                        </td>
                        <td colspan="2">
                            <div class="translation-cell">
                                <textarea class="form-control js-translation-input"
                                          data-key="<?php echo klytos_esc_attr( $key ); ?>"
                                          data-original="<?php echo klytos_esc_attr( $currentTranslation ); ?>"
                                          placeholder="<?php echo klytos_esc_attr( $englishValue ); ?>"
                                          rows="1"
                                          class="w-full"
                                          style="resize:none;overflow:hidden;font-size:0.85rem;padding:0.35rem 0.5rem;line-height:1.4;border-color:var(--klytos-border);min-height:0;"><?php echo klytos_esc_html( $currentTranslation ); ?></textarea>
                                <div class="translation-actions">
                                    <button type="button" class="btn btn-sm btn-primary js-save-btn" data-key="<?php echo klytos_esc_attr( $key ); ?>" style="display:none;">
                                        <i class="fa-solid fa-floppy-disk"></i> <?php echo __( 'common.save' ); ?>
                                    </button>
                                    <span class="translation-saved-msg" style="display:none;color:#28a745;font-size:0.8rem;">
                                        <i class="fa-solid fa-check"></i> <?php echo __( 'translations.save_success' ); ?>
                                    </span>
                                    <?php if ( !empty( $aiProviders ) ): ?>
                                    <div class="dropdown" style="display:inline-block;position:relative;">
                                        <button type="button" class="btn btn-sm btn-outline js-ai-translate-btn" title="<?php echo klytos_esc_attr( __( 'translations.translate_with' ) ); ?>">
                                            <i class="fa-solid fa-wand-magic-sparkles"></i> IA
                                        </button>
                                        <div class="dropdown-menu js-ai-dropdown" style="display:none;position:absolute;right:0;top:100%;background:var(--klytos-card-bg);border:1px solid var(--klytos-border);border-radius:var(--klytos-radius, 6px);box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:100;min-width:160px;">
                                            <?php foreach ( $aiProviders as $provider ): ?>
                                                <button type="button" class="dropdown-item js-ai-provider-btn"
                                                        data-provider="<?php echo klytos_esc_attr( $provider['id'] ); ?>"
                                                        data-key="<?php echo klytos_esc_attr( $key ); ?>"
                                                        style="display:block;width:100%;text-align:left;padding:0.5rem 1rem;border:none;background:none;cursor:pointer;color:var(--klytos-text);font-size:0.85rem;">
                                                    <?php echo klytos_esc_html( $provider['name'] ); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <?php echo $rowActions; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php klytos_do_action( 'admin.translations.after_table' ); ?>

<?php endif; /* end: has editable languages */ ?>

<?php klytos_do_action( 'admin.translations.after' ); ?>

<style nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
#translationsTable td {
    padding: 0.4rem 0.5rem;
    vertical-align: middle;
}
.translation-cell {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.translation-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-height: 1.5rem;
}
.translation-missing .js-translation-input {
    border-color: #e2a03f !important;
}
.translation-flash {
    animation: flashGreen 0.8s ease;
}
@keyframes flashGreen {
    0%   { background-color: rgba(40, 167, 69, 0.2); }
    100% { background-color: transparent; }
}
.dropdown-item:hover {
    background: var(--admin-bg) !important;
}
.js-save-btn {
    font-size: 0.8rem;
    padding: 0.2rem 0.6rem;
}
.js-ai-translate-btn {
    font-size: 0.8rem;
    padding: 0.2rem 0.5rem;
}
#translateAllBtn {
    white-space: nowrap;
}
@media (max-width: 768px) {
    #translationsTable thead { display: none; }
    #translationsTable tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid var(--admin-border);
        border-radius: var(--admin-radius, 6px);
        padding: 0.75rem;
    }
    #translationsTable tbody td {
        display: block;
        width: 100% !important;
        text-align: left !important;
        padding: 0.35rem 0;
        border: none;
        background: none !important;
    }
}
</style>

<script nonce="<?php echo klytos_esc_attr( $cspNonce ); ?>">
(function() {
    var csrfToken      = '<?php echo klytos_esc_attr( $csrf ); ?>';
    var apiUrl         = '<?php echo klytos_esc_url( $adminPath . 'api/translations.php' ); ?>';
    var aiApiUrl       = '<?php echo klytos_esc_url( $adminPath . 'api/translations-ai.php' ); ?>';
    var currentSource  = '<?php echo klytos_esc_attr( $selectedSource ); ?>';
    var currentLocale  = '<?php echo klytos_esc_attr( $selectedLocale ); ?>';
    var msgSaveSuccess = '<?php echo klytos_esc_attr( __( 'translations.save_success' ) ); ?>';
    var msgSaveError   = '<?php echo klytos_esc_attr( __( 'translations.save_error' ) ); ?>';
    var msgTranslating = '<?php echo klytos_esc_attr( __( 'translations.translating' ) ); ?>';
    var msgTranslatedCount = '<?php echo klytos_esc_attr( __( 'translations.translated_count' ) ); ?>';
    var msgConfirmAll  = '<?php echo klytos_esc_attr( __( 'translations.confirm_translate_all' ) ); ?>';

    // ─── Source/Locale change → reload with query params ─────
    var sourceSelect = document.getElementById('translationSource');
    var localeSelect = document.getElementById('translationLocale');

    if (sourceSelect) {
        sourceSelect.addEventListener('change', function() {
            navigateToParams(this.value, localeSelect ? localeSelect.value : currentLocale);
        });
    }

    if (localeSelect) {
        localeSelect.addEventListener('change', function() {
            navigateToParams(sourceSelect ? sourceSelect.value : currentSource, this.value);
        });
    }

    function navigateToParams(source, locale) {
        var url = new URL(window.location.href);
        url.searchParams.set('source', source);
        url.searchParams.set('locale', locale);
        window.location.href = url.toString();
    }

    // ─── Search filter ───────────────────────────────────────
    var searchInput = document.getElementById('translationSearch');
    var missingToggle = document.getElementById('showMissingOnly');

    if (searchInput) {
        searchInput.addEventListener('input', filterRows);
    }
    if (missingToggle) {
        missingToggle.addEventListener('change', filterRows);
    }

    function filterRows() {
        var query = (searchInput ? searchInput.value.toLowerCase() : '');
        var onlyMissing = missingToggle ? missingToggle.checked : false;
        var rows = document.querySelectorAll('#translationsTable tbody tr');

        rows.forEach(function(row) {
            var key = row.getAttribute('data-key') || '';
            var english = row.getAttribute('data-english') || '';
            var translation = row.getAttribute('data-translation') || '';
            var isMissing = row.classList.contains('translation-missing');

            var matchesSearch = !query ||
                key.toLowerCase().indexOf(query) !== -1 ||
                english.indexOf(query) !== -1 ||
                translation.indexOf(query) !== -1;

            var matchesMissing = !onlyMissing || isMissing;

            row.style.display = (matchesSearch && matchesMissing) ? '' : 'none';
        });
    }

    // ─── Auto-resize textareas ───────────────────────────────
    function autoResize(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    document.querySelectorAll('.js-translation-input').forEach(function(ta) {
        autoResize(ta);
        ta.addEventListener('input', function() {
            autoResize(this);
            // Show/hide save button based on changes.
            var row = this.closest('tr');
            var saveBtn = row.querySelector('.js-save-btn');
            var savedMsg = row.querySelector('.translation-saved-msg');
            if (saveBtn) {
                var hasChanged = this.value !== this.getAttribute('data-original');
                saveBtn.style.display = hasChanged ? 'inline-flex' : 'none';
                if (savedMsg) savedMsg.style.display = 'none';
            }
        });
    });

    // ─── Save individual translation ─────────────────────────
    function saveTranslation(key, value, row) {
        var saveBtn = row.querySelector('.js-save-btn');
        var savedMsg = row.querySelector('.translation-saved-msg');

        // Show saving state.
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }

        return fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                source: currentSource,
                locale: currentLocale,
                key: key,
                value: value
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                if (row) {
                    row.classList.add('translation-flash');
                    setTimeout(function() { row.classList.remove('translation-flash'); }, 800);
                    if (value) {
                        row.classList.remove('translation-missing');
                        row.classList.add('translation-done');
                    } else {
                        row.classList.add('translation-missing');
                        row.classList.remove('translation-done');
                    }
                    row.setAttribute('data-translation', value.toLowerCase());
                    var ta = row.querySelector('.js-translation-input');
                    if (ta) ta.setAttribute('data-original', value);
                }
                // Hide save button, show confirmation.
                if (saveBtn) { saveBtn.style.display = 'none'; saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + msgSaveSuccess.split(' ')[0]; }
                if (savedMsg) { savedMsg.style.display = 'inline'; setTimeout(function() { savedMsg.style.display = 'none'; }, 2500); }
                return true;
            } else {
                if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> ' + msgSaveError; }
                alert(msgSaveError + ': ' + (data.error || ''));
                return false;
            }
        })
        .catch(function() {
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Error'; }
            alert(msgSaveError);
            return false;
        });
    }

    // Save button click.
    document.querySelectorAll('.js-save-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = this.getAttribute('data-key');
            var row = this.closest('tr');
            var ta  = row.querySelector('.js-translation-input');
            if (ta) {
                saveTranslation(key, ta.value, row);
            }
        });
    });

    // ─── AI translate (individual) ───────────────────────────
    // Toggle dropdown.
    document.querySelectorAll('.js-ai-translate-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var dropdown = this.parentElement.querySelector('.js-ai-dropdown');
            // Close all other dropdowns.
            document.querySelectorAll('.js-ai-dropdown').forEach(function(d) {
                if (d !== dropdown) d.style.display = 'none';
            });
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        });
    });

    // Close dropdowns on outside click.
    document.addEventListener('click', function() {
        document.querySelectorAll('.js-ai-dropdown, #translateAllMenu').forEach(function(d) {
            d.style.display = 'none';
        });
    });

    // AI provider button click (individual).
    document.querySelectorAll('.js-ai-provider-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var provider = this.getAttribute('data-provider');
            var key      = this.getAttribute('data-key');
            var row      = this.closest('tr');
            var ta       = row.querySelector('.js-translation-input');
            var englishTd = row.children[1];
            var englishText = englishTd ? englishTd.textContent.trim() : '';

            // Close dropdown.
            this.closest('.js-ai-dropdown').style.display = 'none';

            // Show loading state.
            ta.value = msgTranslating;
            ta.disabled = true;

            fetch(aiApiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    provider: provider,
                    source_text: englishText,
                    source_locale: 'en',
                    target_locale: currentLocale,
                    context: 'CMS admin panel translation - key: ' + key
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                ta.disabled = false;
                if (data.success && data.translation) {
                    ta.value = data.translation;
                    autoResize(ta);
                    // Auto-save the AI translation.
                    saveTranslation(key, data.translation, row);
                } else {
                    ta.value = ta.getAttribute('data-original') || '';
                    alert(data.error || msgSaveError);
                }
            })
            .catch(function() {
                ta.disabled = false;
                ta.value = ta.getAttribute('data-original') || '';
                alert(msgSaveError);
            });
        });
    });

    // ─── Translate all missing ───────────────────────────────
    var translateAllBtn = document.getElementById('translateAllBtn');
    var translateAllMenu = document.getElementById('translateAllMenu');

    if (translateAllBtn && translateAllMenu) {
        translateAllBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            translateAllMenu.style.display = translateAllMenu.style.display === 'none' ? 'block' : 'none';
        });

        document.querySelectorAll('.js-translate-all-provider').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var provider = this.getAttribute('data-provider');
                var providerName = this.textContent.trim();
                translateAllMenu.style.display = 'none';

                // Collect missing rows.
                var missingRows = document.querySelectorAll('tr.translation-missing');
                var count = missingRows.length;
                if (count === 0) return;

                var confirmMsg = msgConfirmAll.replace('{count}', count).replace('{provider}', providerName);
                if (!confirm(confirmMsg)) return;

                // Show bulk progress.
                var bulkProgress    = document.getElementById('bulkProgress');
                var bulkProgressBar = document.getElementById('bulkProgressBar');
                var bulkProgressText = document.getElementById('bulkProgressText');
                bulkProgress.style.display = 'block';
                translateAllBtn.disabled = true;

                var done = 0;

                function translateNext(index) {
                    if (index >= count) {
                        bulkProgressText.textContent = msgTranslatedCount.replace('{count}', done);
                        translateAllBtn.disabled = false;
                        setTimeout(function() { bulkProgress.style.display = 'none'; }, 3000);
                        return;
                    }

                    var row = missingRows[index];
                    var key = row.getAttribute('data-key');
                    var englishTd = row.children[1];
                    var englishText = englishTd ? englishTd.textContent.trim() : '';
                    var ta = row.querySelector('.js-translation-input');

                    bulkProgressText.textContent = msgTranslating + ' (' + (index + 1) + '/' + count + ')';
                    bulkProgressBar.style.width = Math.round(((index + 1) / count) * 100) + '%';

                    fetch(aiApiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({
                            provider: provider,
                            source_text: englishText,
                            source_locale: 'en',
                            target_locale: currentLocale,
                            context: 'CMS admin panel translation - key: ' + key
                        })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.translation) {
                            ta.value = data.translation;
                            autoResize(ta);
                            return saveTranslation(key, data.translation, row);
                        }
                        return false;
                    })
                    .then(function(saved) {
                        if (saved) done++;
                        translateNext(index + 1);
                    })
                    .catch(function() {
                        translateNext(index + 1);
                    });
                }

                translateNext(0);
            });
        });
    }
})();
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
