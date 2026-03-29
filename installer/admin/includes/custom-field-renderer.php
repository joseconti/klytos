<?php
/**
 * Klytos Admin — Custom Field Renderer
 * Renders HTML form inputs for each custom field type in the page/entry editor.
 *
 * @package Klytos
 * @since   0.7.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2025 Jose Conti — https://joseconti.com
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare(strict_types=1);

/**
 * Render a custom field HTML input.
 *
 * @param array  $fieldDef     The field definition from the post type.
 * @param mixed  $currentValue The current stored value (or null).
 * @param string $namePrefix   HTML name prefix (default 'cf').
 * @return string HTML output.
 */
function renderCustomField(array $fieldDef, mixed $currentValue, string $namePrefix = 'cf'): string
{
    $id          = $fieldDef['id'] ?? '';
    $type        = $fieldDef['type'] ?? 'text';
    $label       = $fieldDef['label'] ?? ucfirst($id);
    $description = $fieldDef['description'] ?? '';
    $placeholder = $fieldDef['placeholder'] ?? '';
    $required    = $fieldDef['required'] ?? false;
    $options     = $fieldDef['options'] ?? [];
    $validation  = $fieldDef['validation'] ?? [];
    $default     = $fieldDef['default_value'] ?? null;

    $value    = $currentValue ?? $default;
    $name     = $namePrefix . '[' . klytos_esc_attr($id) . ']';
    $htmlId   = 'cf-' . klytos_esc_attr($id);
    $reqAttr  = $required ? ' required' : '';

    $html = '<div class="form-group" style="margin-bottom:1rem;">';
    $html .= '<label for="' . $htmlId . '">' . klytos_esc_html($label);
    if ($required) {
        $html .= ' <span style="color:var(--admin-error);">*</span>';
    }
    $html .= '</label>';

    switch ($type) {
        case 'text':
        case 'email':
        case 'url':
        case 'phone':
        case 'password':
            $inputType = ($type === 'phone') ? 'tel' : $type;
            $extraAttrs = '';
            if (isset($validation['min_length'])) {
                $extraAttrs .= ' minlength="' . (int) $validation['min_length'] . '"';
            }
            if (isset($validation['max_length'])) {
                $extraAttrs .= ' maxlength="' . (int) $validation['max_length'] . '"';
            }
            if (isset($validation['pattern'])) {
                $extraAttrs .= ' pattern="' . klytos_esc_attr($validation['pattern']) . '"';
            }
            $html .= '<input type="' . klytos_esc_attr($inputType) . '" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . $extraAttrs . '>';
            break;

        case 'textarea':
            $maxLen = isset($validation['max_length']) ? ' maxlength="' . (int) $validation['max_length'] . '"' : '';
            $html .= '<textarea id="' . $htmlId . '" name="' . $name . '" class="form-control" rows="4" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . $maxLen . '>' . klytos_esc_html((string) ($value ?? '')) . '</textarea>';
            break;

        case 'richtext':
            $html .= '<textarea id="' . $htmlId . '" name="' . $name . '" class="form-control" rows="8" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . ' style="font-family:inherit;">' . klytos_esc_html((string) ($value ?? '')) . '</textarea>';
            $html .= '<p class="form-help" style="font-size:0.8rem;">HTML content is supported.</p>';
            break;

        case 'code':
            $lang = $validation['language'] ?? '';
            $html .= '<textarea id="' . $htmlId . '" name="' . $name . '" class="form-control" rows="8" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . ' style="font-family:monospace;font-size:0.85rem;">' . klytos_esc_html((string) ($value ?? '')) . '</textarea>';
            if ($lang) {
                $html .= '<p class="form-help" style="font-size:0.8rem;">Language: ' . klytos_esc_html($lang) . '</p>';
            }
            break;

        case 'number':
            $extraAttrs = '';
            if (isset($validation['min'])) {
                $extraAttrs .= ' min="' . klytos_esc_attr((string) $validation['min']) . '"';
            }
            if (isset($validation['max'])) {
                $extraAttrs .= ' max="' . klytos_esc_attr((string) $validation['max']) . '"';
            }
            if (isset($validation['step'])) {
                $extraAttrs .= ' step="' . klytos_esc_attr((string) $validation['step']) . '"';
            }
            $html .= '<input type="number" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . $extraAttrs . '>';
            break;

        case 'range':
            $min  = $validation['min'] ?? 0;
            $max  = $validation['max'] ?? 100;
            $step = $validation['step'] ?? 1;
            $val  = $value ?? $min;
            $html .= '<div style="display:flex;align-items:center;gap:0.5rem;">';
            $html .= '<input type="range" id="' . $htmlId . '" name="' . $name . '" min="' . klytos_esc_attr((string) $min) . '" max="' . klytos_esc_attr((string) $max) . '" step="' . klytos_esc_attr((string) $step) . '" value="' . klytos_esc_attr((string) $val) . '" style="flex:1;" oninput="document.getElementById(\'' . $htmlId . '-val\').textContent=this.value">';
            $html .= '<span id="' . $htmlId . '-val" style="min-width:3rem;text-align:center;">' . klytos_esc_html((string) $val) . '</span>';
            $html .= '</div>';
            break;

        case 'date':
            $html .= '<input type="date" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '"' . $reqAttr . '>';
            break;

        case 'datetime':
            $html .= '<input type="datetime-local" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '"' . $reqAttr . '>';
            break;

        case 'time':
            $html .= '<input type="time" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '"' . $reqAttr . '>';
            break;

        case 'color':
            $colorVal = (string) ($value ?? '#000000');
            $html .= '<div style="display:flex;align-items:center;gap:0.5rem;">';
            $html .= '<input type="color" id="' . $htmlId . '" name="' . $name . '" value="' . klytos_esc_attr($colorVal) . '" style="width:50px;height:36px;padding:2px;border:1px solid var(--admin-border);border-radius:var(--admin-radius);">';
            $html .= '<input type="text" value="' . klytos_esc_attr($colorVal) . '" class="form-control" style="width:120px;" oninput="document.getElementById(\'' . $htmlId . '\').value=this.value" onfocus="this.previousElementSibling.addEventListener(\'input\',function(e){this.nextElementSibling&&(this.nextElementSibling.value=e.target.value)}.bind(this.previousElementSibling),{once:false})">';
            $html .= '</div>';
            break;

        case 'select':
            $html .= '<select id="' . $htmlId . '" name="' . $name . '" class="form-control"' . $reqAttr . '>';
            $html .= '<option value="">— Select —</option>';
            foreach ($options as $opt) {
                $optVal   = $opt['value'] ?? '';
                $optLabel = $opt['label'] ?? $optVal;
                $selected = ((string) $value === (string) $optVal) ? ' selected' : '';
                $html .= '<option value="' . klytos_esc_attr($optVal) . '"' . $selected . '>' . klytos_esc_html($optLabel) . '</option>';
            }
            $html .= '</select>';
            break;

        case 'multiselect':
            $selectedVals = is_array($value) ? $value : [];
            $html .= '<select id="' . $htmlId . '" name="' . $name . '[]" class="form-control" multiple size="5"' . $reqAttr . '>';
            foreach ($options as $opt) {
                $optVal   = $opt['value'] ?? '';
                $optLabel = $opt['label'] ?? $optVal;
                $selected = in_array($optVal, $selectedVals, true) ? ' selected' : '';
                $html .= '<option value="' . klytos_esc_attr($optVal) . '"' . $selected . '>' . klytos_esc_html($optLabel) . '</option>';
            }
            $html .= '</select>';
            $html .= '<p class="form-help" style="font-size:0.8rem;">Hold Ctrl/Cmd to select multiple.</p>';
            break;

        case 'checkbox':
            $checked = !empty($value) ? ' checked' : '';
            $html .= '<input type="hidden" name="' . $name . '" value="0">';
            $html .= '<label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">';
            $html .= '<input type="checkbox" id="' . $htmlId . '" name="' . $name . '" value="1"' . $checked . '>';
            $html .= klytos_esc_html($label);
            $html .= '</label>';
            break;

        case 'checkbox_group':
            $selectedVals = is_array($value) ? $value : [];
            $html .= '<div style="display:flex;flex-direction:column;gap:0.25rem;">';
            foreach ($options as $opt) {
                $optVal   = $opt['value'] ?? '';
                $optLabel = $opt['label'] ?? $optVal;
                $checked  = in_array($optVal, $selectedVals, true) ? ' checked' : '';
                $html .= '<label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">';
                $html .= '<input type="checkbox" name="' . $name . '[]" value="' . klytos_esc_attr($optVal) . '"' . $checked . '>';
                $html .= klytos_esc_html($optLabel);
                $html .= '</label>';
            }
            $html .= '</div>';
            break;

        case 'radio':
            $html .= '<div style="display:flex;flex-direction:column;gap:0.25rem;">';
            foreach ($options as $opt) {
                $optVal   = $opt['value'] ?? '';
                $optLabel = $opt['label'] ?? $optVal;
                $checked  = ((string) $value === (string) $optVal) ? ' checked' : '';
                $html .= '<label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">';
                $html .= '<input type="radio" name="' . $name . '" value="' . klytos_esc_attr($optVal) . '"' . $checked . '>';
                $html .= klytos_esc_html($optLabel);
                $html .= '</label>';
            }
            $html .= '</div>';
            break;

        case 'toggle':
            $checked = !empty($value) ? ' checked' : '';
            $html .= '<input type="hidden" name="' . $name . '" value="0">';
            $html .= '<label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">';
            $html .= '<input type="checkbox" id="' . $htmlId . '" name="' . $name . '" value="1"' . $checked . ' style="width:2.5rem;height:1.25rem;">';
            $html .= '<span style="font-size:0.9rem;">' . klytos_esc_html($label) . '</span>';
            $html .= '</label>';
            break;

        case 'image':
            $html .= '<input type="url" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder ?: 'Image URL') . '"' . $reqAttr . '>';
            if (!empty($value)) {
                $html .= '<div style="margin-top:0.5rem;"><img src="' . klytos_esc_attr((string) $value) . '" style="max-width:200px;max-height:150px;border-radius:var(--admin-radius);border:1px solid var(--admin-border);" alt="Preview"></div>';
            }
            break;

        case 'file':
            $html .= '<input type="url" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder ?: 'File URL') . '"' . $reqAttr . '>';
            break;

        case 'gallery':
            $images = is_array($value) ? $value : [];
            $html .= '<div id="' . $htmlId . '-list">';
            foreach ($images as $i => $img) {
                $html .= '<div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.5rem;">';
                $html .= '<input type="url" name="' . $name . '[]" class="form-control" value="' . klytos_esc_attr((string) $img) . '" placeholder="Image URL" style="flex:1;">';
                $html .= '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">x</button>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-outline btn-sm" onclick="addGalleryRow(\'' . $htmlId . '\',\'' . klytos_esc_attr($name) . '\')">+ Add Image</button>';
            break;

        case 'json':
            $jsonStr = is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $html .= '<textarea id="' . $htmlId . '" name="' . $name . '" class="form-control" rows="6" placeholder="' . klytos_esc_attr($placeholder ?: '{}') . '"' . $reqAttr . ' style="font-family:monospace;font-size:0.85rem;">' . klytos_esc_html((string) ($jsonStr ?? '')) . '</textarea>';
            break;

        case 'repeater':
            $rows      = is_array($value) ? $value : [];
            $subFields = $fieldDef['sub_fields'] ?? [];
            $html .= '<div id="' . $htmlId . '-rows" style="margin-bottom:0.5rem;">';
            foreach ($rows as $rowIdx => $row) {
                $html .= renderRepeaterRow($name, $htmlId, $subFields, $rowIdx, $row);
            }
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-outline btn-sm" onclick="addRepeaterRow(\'' . $htmlId . '\',\'' . klytos_esc_attr($name) . '\',' . json_encode($subFields) . ')">+ Add Row</button>';
            break;

        case 'relationship':
            $relValues = is_array($value) ? $value : ($value ? [$value] : []);
            $html .= '<div id="' . $htmlId . '-list">';
            foreach ($relValues as $i => $slug) {
                $html .= '<div style="display:flex;gap:0.5rem;align-items:center;margin-bottom:0.5rem;">';
                $html .= '<input type="text" name="' . $name . '[]" class="form-control" value="' . klytos_esc_attr((string) $slug) . '" placeholder="Entry slug" style="flex:1;">';
                $html .= '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">x</button>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $relConfig = $fieldDef['relationship'] ?? [];
            $ptNames   = !empty($relConfig['post_types']) ? implode(', ', $relConfig['post_types']) : 'any';
            $html .= '<button type="button" class="btn btn-outline btn-sm" onclick="addRelRow(\'' . $htmlId . '\',\'' . klytos_esc_attr($name) . '\')">+ Add Reference</button>';
            $html .= '<p class="form-help" style="font-size:0.8rem;">References: ' . klytos_esc_html($ptNames) . '</p>';
            break;

        default:
            $html .= '<input type="text" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . '>';
            break;
    }

    if ($description && $type !== 'checkbox') {
        $html .= '<p class="form-help">' . klytos_esc_html($description) . '</p>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render a single repeater row.
 */
function renderRepeaterRow(string $name, string $htmlId, array $subFields, int $rowIdx, array $row): string
{
    $html = '<div class="repeater-row" style="padding:0.75rem;margin-bottom:0.5rem;background:var(--admin-bg);border-radius:var(--admin-radius);border:1px solid var(--admin-border);">';
    $html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">';
    $html .= '<strong style="font-size:0.85rem;">Row ' . ($rowIdx + 1) . '</strong>';
    $html .= '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'.repeater-row\').remove()">x</button>';
    $html .= '</div>';

    foreach ($subFields as $subDef) {
        $subId    = $subDef['id'] ?? '';
        $subName  = $name . '[' . $rowIdx . '][' . $subId . ']';
        $subValue = $row[$subId] ?? null;
        // Render sub-field as a simple input (no nested repeaters).
        $subDef['id'] = $subId;
        $html .= renderSubField($subDef, $subValue, $subName);
    }

    $html .= '</div>';
    return $html;
}

/**
 * Render a simplified sub-field (for repeater rows).
 */
function renderSubField(array $fieldDef, mixed $value, string $name): string
{
    $type        = $fieldDef['type'] ?? 'text';
    $label       = $fieldDef['label'] ?? '';
    $placeholder = $fieldDef['placeholder'] ?? '';
    $required    = ($fieldDef['required'] ?? false) ? ' required' : '';

    $html = '<div class="form-group" style="margin-bottom:0.5rem;">';
    if ($label) {
        $html .= '<label style="font-size:0.85rem;">' . klytos_esc_html($label) . '</label>';
    }

    switch ($type) {
        case 'textarea':
        case 'richtext':
            $html .= '<textarea name="' . klytos_esc_attr($name) . '" class="form-control" rows="3" placeholder="' . klytos_esc_attr($placeholder) . '"' . $required . '>' . klytos_esc_html((string) ($value ?? '')) . '</textarea>';
            break;

        case 'number':
            $html .= '<input type="number" name="' . klytos_esc_attr($name) . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder) . '"' . $required . '>';
            break;

        case 'checkbox':
        case 'toggle':
            $checked = !empty($value) ? ' checked' : '';
            $html .= '<input type="hidden" name="' . klytos_esc_attr($name) . '" value="0">';
            $html .= '<input type="checkbox" name="' . klytos_esc_attr($name) . '" value="1"' . $checked . '>';
            break;

        case 'select':
            $options = $fieldDef['options'] ?? [];
            $html .= '<select name="' . klytos_esc_attr($name) . '" class="form-control"' . $required . '>';
            $html .= '<option value="">—</option>';
            foreach ($options as $opt) {
                $optVal   = $opt['value'] ?? '';
                $selected = ((string) $value === (string) $optVal) ? ' selected' : '';
                $html .= '<option value="' . klytos_esc_attr($optVal) . '"' . $selected . '>' . klytos_esc_html($opt['label'] ?? $optVal) . '</option>';
            }
            $html .= '</select>';
            break;

        case 'date':
            $html .= '<input type="date" name="' . klytos_esc_attr($name) . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '"' . $required . '>';
            break;

        case 'color':
            $html .= '<input type="color" name="' . klytos_esc_attr($name) . '" value="' . klytos_esc_attr((string) ($value ?? '#000000')) . '">';
            break;

        default:
            $html .= '<input type="text" name="' . klytos_esc_attr($name) . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder) . '"' . $required . '>';
            break;
    }

    $html .= '</div>';
    return $html;
}
