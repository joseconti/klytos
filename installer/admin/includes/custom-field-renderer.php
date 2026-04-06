<?php

/**
 * Klytos Admin — Custom Field Renderer
 * Renders HTML form inputs for each custom field type in the page/entry editor.
 *
 * @package Klytos
 * @since   0.7.0
 *
 * @license    GPL-3.0-or-later — https://www.gnu.org/licenses/gpl-3.0.html
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             This program is free software: you can redistribute it and/or modify
 *             it under the terms of the GNU General Public License v3 or later.
 *             Plugins and templates are NOT derivative works — see LICENSE.
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

    $html = '<div class="form-group mb-2">';
    $html .= '<label for="' . $htmlId . '">' . klytos_esc_html($label);
    if ($required) {
        $html .= ' <span class="text-error">*</span>';
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
            $html .= '<textarea id="' . $htmlId . '" name="' . $name . '" class="form-control" rows="8" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . '>' . klytos_esc_html((string) ($value ?? '')) . '</textarea>';
            $html .= '<p class="form-help text-xs">HTML content is supported.</p>';
            break;

        case 'code':
            $lang = $validation['language'] ?? '';
            $html .= '<textarea id="' . $htmlId . '" name="' . $name . '" class="form-control text-mono text-sm" rows="8" placeholder="' . klytos_esc_attr($placeholder) . '"' . $reqAttr . '>' . klytos_esc_html((string) ($value ?? '')) . '</textarea>';
            if ($lang) {
                $html .= '<p class="form-help text-xs">Language: ' . klytos_esc_html($lang) . '</p>';
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
            $html .= '<div class="flex flex-center flex-gap-sm">';
            $html .= '<input type="range" id="' . $htmlId . '" name="' . $name . '" min="' . klytos_esc_attr((string) $min) . '" max="' . klytos_esc_attr((string) $max) . '" step="' . klytos_esc_attr((string) $step) . '" value="' . klytos_esc_attr((string) $val) . '" class="flex-1" data-range-output="' . $htmlId . '-val">';
            $html .= '<span id="' . $htmlId . '-val" class="text-center" style="min-width:3rem;">' . klytos_esc_html((string) $val) . '</span>';
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
            $html .= '<div class="flex flex-center flex-gap-sm">';
            $html .= '<input type="color" id="' . $htmlId . '" name="' . $name . '" value="' . klytos_esc_attr($colorVal) . '" style="width:50px;height:36px;padding:2px;border:1px solid var(--klytos-border);border-radius:var(--klytos-radius);">';
            $html .= '<input type="text" value="' . klytos_esc_attr($colorVal) . '" class="form-control" style="width:120px;" data-color-sync="' . $htmlId . '">';
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
            $html .= '<p class="form-help text-xs">Hold Ctrl/Cmd to select multiple.</p>';
            break;

        case 'checkbox':
            $checked = !empty($value) ? ' checked' : '';
            $html .= '<input type="hidden" name="' . $name . '" value="0">';
            $html .= '<label class="flex flex-center flex-gap-sm">';
            $html .= '<input type="checkbox" id="' . $htmlId . '" name="' . $name . '" value="1"' . $checked . '>';
            $html .= klytos_esc_html($label);
            $html .= '</label>';
            break;

        case 'checkbox_group':
            $selectedVals = is_array($value) ? $value : [];
            $html .= '<div class="flex-col flex-gap-xs">';
            foreach ($options as $opt) {
                $optVal   = $opt['value'] ?? '';
                $optLabel = $opt['label'] ?? $optVal;
                $checked  = in_array($optVal, $selectedVals, true) ? ' checked' : '';
                $html .= '<label class="flex flex-center flex-gap-sm">';
                $html .= '<input type="checkbox" name="' . $name . '[]" value="' . klytos_esc_attr($optVal) . '"' . $checked . '>';
                $html .= klytos_esc_html($optLabel);
                $html .= '</label>';
            }
            $html .= '</div>';
            break;

        case 'radio':
            $html .= '<div class="flex-col flex-gap-xs">';
            foreach ($options as $opt) {
                $optVal   = $opt['value'] ?? '';
                $optLabel = $opt['label'] ?? $optVal;
                $checked  = ((string) $value === (string) $optVal) ? ' checked' : '';
                $html .= '<label class="flex flex-center flex-gap-sm">';
                $html .= '<input type="radio" name="' . $name . '" value="' . klytos_esc_attr($optVal) . '"' . $checked . '>';
                $html .= klytos_esc_html($optLabel);
                $html .= '</label>';
            }
            $html .= '</div>';
            break;

        case 'toggle':
            $checked = !empty($value) ? ' checked' : '';
            $html .= '<input type="hidden" name="' . $name . '" value="0">';
            $html .= '<label class="flex flex-center flex-gap-sm">';
            $html .= '<input type="checkbox" id="' . $htmlId . '" name="' . $name . '" value="1"' . $checked . ' style="width:2.5rem;height:1.25rem;">';
            $html .= '<span class="text-sm">' . klytos_esc_html($label) . '</span>';
            $html .= '</label>';
            break;

        case 'image':
            $html .= '<input type="url" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder ?: 'Image URL') . '"' . $reqAttr . '>';
            if (!empty($value)) {
                $html .= '<div class="mt-1"><img src="' . klytos_esc_attr((string) $value) . '" style="max-width:200px;max-height:150px;border-radius:var(--klytos-radius);border:1px solid var(--klytos-border);" alt="Preview"></div>';
            }
            break;

        case 'file':
            $html .= '<input type="url" id="' . $htmlId . '" name="' . $name . '" class="form-control" value="' . klytos_esc_attr((string) ($value ?? '')) . '" placeholder="' . klytos_esc_attr($placeholder ?: 'File URL') . '"' . $reqAttr . '>';
            break;

        case 'gallery':
            $images = is_array($value) ? $value : [];
            $html .= '<div id="' . $htmlId . '-list">';
            foreach ($images as $i => $img) {
                $html .= '<div class="flex flex-center flex-gap-sm mb-1">';
                $html .= '<input type="url" name="' . $name . '[]" class="form-control" value="' . klytos_esc_attr((string) $img) . '" placeholder="Image URL" class="flex-1">';
                $html .= '<button type="button" class="btn btn-danger btn-sm" data-action="remove-parent">x</button>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-outline btn-sm" data-action="add-gallery-row" data-target="' . $htmlId . '-list" data-name="' . klytos_esc_attr($name) . '">+ Add Image</button>';
            break;

        case 'json':
            $jsonStr = is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $html .= '<textarea id="' . $htmlId . '" name="' . $name . '" class="form-control text-mono text-sm" rows="6" placeholder="' . klytos_esc_attr($placeholder ?: '{}') . '"' . $reqAttr . '>' . klytos_esc_html((string) ($jsonStr ?? '')) . '</textarea>';
            break;

        case 'repeater':
            $rows      = is_array($value) ? $value : [];
            $subFields = $fieldDef['sub_fields'] ?? [];
            $html .= '<div id="' . $htmlId . '-rows" style="margin-bottom:0.5rem;">';
            foreach ($rows as $rowIdx => $row) {
                $html .= renderRepeaterRow($name, $htmlId, $subFields, $rowIdx, $row);
            }
            $html .= '</div>';
            $html .= '<button type="button" class="btn btn-outline btn-sm" data-action="add-repeater-row" data-target="' . $htmlId . '-rows" data-name="' . klytos_esc_attr($name) . '" data-subfields="' . klytos_esc_attr(json_encode($subFields)) . '">+ Add Row</button>';
            break;

        case 'relationship':
            $relValues = is_array($value) ? $value : ($value ? [$value] : []);
            $html .= '<div id="' . $htmlId . '-list">';
            foreach ($relValues as $i => $slug) {
                $html .= '<div class="flex flex-center flex-gap-sm mb-1">';
                $html .= '<input type="text" name="' . $name . '[]" class="form-control" value="' . klytos_esc_attr((string) $slug) . '" placeholder="Entry slug" class="flex-1">';
                $html .= '<button type="button" class="btn btn-danger btn-sm" data-action="remove-parent">x</button>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $relConfig = $fieldDef['relationship'] ?? [];
            $ptNames   = !empty($relConfig['post_types']) ? implode(', ', $relConfig['post_types']) : 'any';
            $html .= '<button type="button" class="btn btn-outline btn-sm" data-action="add-rel-row" data-target="' . $htmlId . '-list" data-name="' . klytos_esc_attr($name) . '">+ Add Reference</button>';
            $html .= '<p class="form-help text-xs">References: ' . klytos_esc_html($ptNames) . '</p>';
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
    $html = '<div class="repeater-row rounded mb-1" style="padding:0.75rem;background:var(--klytos-bg);border:1px solid var(--klytos-border);">';
    $html .= '<div class="flex-between mb-1">';
    $html .= '<strong class="text-sm">Row ' . ($rowIdx + 1) . '</strong>';
    $html .= '<button type="button" class="btn btn-danger btn-sm" data-action="remove-closest" data-target=".repeater-row">x</button>';
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

    $html = '<div class="form-group mb-1">';
    if ($label) {
        $html .= '<label class="text-sm">' . klytos_esc_html($label) . '</label>';
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
