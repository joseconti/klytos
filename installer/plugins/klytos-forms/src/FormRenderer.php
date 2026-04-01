<?php

/**
 * Klytos — Form Renderer
 * Renders forms as HTML with CSS variables integration, conditional data attributes,
 * multi-step support, honeypot, CSRF, and inline JS config.
 *
 * @package Klytos
 * @since   0.20.0
 *
 * @license    Elastic License 2.0 (ELv2) — https://www.elastic.co/licensing/elastic-license
 * @copyright  Copyright (c) 2026 José Conti — https://plugins.joseconti.com — https://klytos.io
 *             You may use this software under the Elastic License 2.0.
 *             You may NOT provide it as a hosted/managed service.
 *             You may NOT remove or circumvent plugin license key functionality.
 *             See the LICENSE file at the project root for the full license text.
 */

declare( strict_types=1 );

namespace KlytosForms;

use Klytos\Core\Helpers;

class FormRenderer
{
    private FormManager $formManager;

    /** Track whether CSS has already been injected on this page. */
    private static bool $cssInjected = false;

    /** Track whether JS has already been injected on this page. */
    private static bool $jsInjected = false;

    public function __construct( FormManager $formManager )
    {
        $this->formManager = $formManager;
    }

    /**
     * Render a form as complete HTML.
     *
     * @param  string $formId  Form ID.
     * @param  array  $options Rendering options (reserved for future use).
     * @return string HTML output.
     */
    public function render( string $formId, array $options = [] ): string
    {
        $form = $this->formManager->getForm( $formId );
        if ( !$form || $form['status'] !== 'active' ) {
            return '<!-- Klytos Form: formulario no disponible -->';
        }

        $form = klytos_apply_filters( 'form.before_render', $form, $options );

        $settings    = $form['settings'] ?? [];
        $layout      = $settings['layout'] ?? 'stacked';
        $isMultiStep = count( $settings['steps'] ?? [] ) > 1;
        $formClass   = 'klytos-form klytos-form--' . $layout;
        if ( !empty( $settings['css_class'] ) ) {
            $formClass .= ' ' . $settings['css_class'];
        }

        $html = '';

        // CSS (once per page)
        $html .= $this->renderCSS();

        // Form opening
        $html .= '<form class="' . $formClass . '" data-form-id="' . htmlspecialchars( $formId ) . '"';
        $html .= ' method="post" action="/api/forms/submit" enctype="multipart/form-data">';

        // Hidden: form_id
        $html .= '<input type="hidden" name="_form_id" value="' . htmlspecialchars( $formId ) . '">';

        // CSRF token
        $html .= '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars( $this->getCsrfToken() ) . '">';

        // Honeypot
        if ( $form['anti_spam']['honeypot'] ?? true ) {
            $html .= '<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">';
            $html .= '<input type="text" name="_klytos_hp" tabindex="-1" autocomplete="off">';
            $html .= '</div>';
        }

        // Step indicator (multi-step)
        if ( $isMultiStep ) {
            $html .= '<div class="klytos-form-steps"></div>';
        }

        // Fields grouped by step
        $fieldsByStep = [];
        foreach ( $form['fields'] as $field ) {
            $step = $field['step'] ?? 1;
            $fieldsByStep[$step][] = $field;
        }

        foreach ( $fieldsByStep as $step => $fields ) {
            $stepDisplay = ( $isMultiStep && $step > 1 ) ? ' style="display:none"' : '';
            $html .= '<div class="klytos-form-step-content" data-step="' . $step . '"' . $stepDisplay . '>';

            foreach ( $fields as $field ) {
                $html .= $this->renderField( $field );
            }

            $html .= '</div>';
        }

        // Buttons
        $html .= '<div class="klytos-form__actions">';

        if ( $isMultiStep ) {
            $html .= '<button type="button" class="klytos-form__btn klytos-form__btn--secondary" data-action="prev-step" style="display:none">Anterior</button>';
            $html .= '<button type="button" class="klytos-form__btn klytos-form__btn--primary" data-action="next-step">Siguiente</button>';
        }

        $submitDisplay = $isMultiStep ? ' style="display:none"' : '';
        $html .= '<button type="submit" class="klytos-form__btn klytos-form__btn--primary" data-action="submit"' . $submitDisplay . '>';
        $html .= htmlspecialchars( $settings['submit_label'] ?? 'Enviar' );
        $html .= '</button>';

        $html .= '</div>';

        // Success message (hidden)
        $html .= '<div class="klytos-form__success" style="display:none">';
        $html .= htmlspecialchars( $settings['success_message'] ?? '' );
        $html .= '</div>';

        // Error message (hidden)
        $html .= '<div class="klytos-form__error" style="display:none"></div>';

        $html .= '</form>';

        // JS: load engine script (once per page) + form config
        if ( !self::$jsInjected ) {
            self::$jsInjected = true;
            $basePath = \Klytos\Core\Helpers::getBasePath();
            $html .= '<script src="' . $basePath . 'js/klytos-forms.js"></script>';
        }

        $configJson = json_encode( [
            'fields'   => $form['fields'],
            'settings' => $settings,
        ], JSON_UNESCAPED_UNICODE );

        $html .= '<script>';
        $html .= 'document.addEventListener("DOMContentLoaded", function() {';
        $html .= '  var formEl = document.querySelector(\'[data-form-id="' . $formId . '"]\');';
        $html .= '  if (formEl && typeof KlytosFormEngine !== "undefined") {';
        $html .= '    new KlytosFormEngine(formEl, ' . $configJson . ');';
        $html .= '  }';
        $html .= '});';
        $html .= '</script>';

        return $html;
    }

    // ─── Field Rendering ────────────────────────────────────────

    /**
     * Render a single form field with wrapper and label.
     */
    private function renderField( array $field ): string
    {
        $type     = $field['type'] ?? 'text';
        $id       = $field['id'] ?? '';
        $label    = $field['label'] ?? '';
        $required = $field['required'] ?? false;
        $cssClass = 'klytos-form__field klytos-form__field--' . $type;
        if ( !empty( $field['css_class'] ) ) {
            $cssClass .= ' ' . $field['css_class'];
        }

        $conditionalAttr = '';
        if ( !empty( $field['conditional'] ) ) {
            $conditionalAttr = ' data-conditional=\'' . htmlspecialchars( json_encode( $field['conditional'] ), ENT_QUOTES ) . '\'';
        }

        $html = '<div class="' . $cssClass . '" data-field-id="' . htmlspecialchars( $id ) . '"' . $conditionalAttr . '>';

        // Label (except for html, section, hidden)
        if ( !in_array( $type, [ 'html', 'section', 'hidden' ] ) ) {
            $html .= '<label class="klytos-form__label" for="' . htmlspecialchars( $id ) . '">';
            $html .= htmlspecialchars( $label );
            if ( $required ) {
                $html .= ' <span class="klytos-form__required">*</span>';
            }
            $html .= '</label>';
        }

        // Field input by type
        $html .= match ( $type ) {
            'text', 'email', 'url', 'phone', 'password' => $this->renderInputField( $field ),
            'number', 'range'     => $this->renderNumberField( $field ),
            'textarea'            => $this->renderTextarea( $field ),
            'select'              => $this->renderSelect( $field ),
            'radio'               => $this->renderRadioGroup( $field ),
            'checkbox'            => $this->renderCheckbox( $field ),
            'checkbox_group'      => $this->renderCheckboxGroup( $field ),
            'consent'             => $this->renderConsent( $field ),
            'date', 'time'        => $this->renderDateTimeField( $field ),
            'file'                => $this->renderFileField( $field ),
            'hidden'              => $this->renderHiddenField( $field ),
            'html'                => $field['content'] ?? '',
            'section'             => '<div class="klytos-form__section-title">' . htmlspecialchars( $field['content'] ?? $label ) . '</div>',
            default               => $this->renderInputField( $field ),
        };

        // Field error container
        $html .= '<div class="klytos-form__field-error" data-error-for="' . htmlspecialchars( $id ) . '"></div>';

        $html .= '</div>';

        return $html;
    }

    // ─── Individual Field Renderers ─────────────────────────────

    private function renderInputField( array $field ): string
    {
        $type = match ( $field['type'] ) {
            'phone' => 'tel',
            default => $field['type'],
        };

        $attrs = [
            'type'        => $type,
            'id'          => $field['id'],
            'name'        => $field['id'],
            'placeholder' => $field['placeholder'] ?? '',
            'value'       => $field['default_value'] ?? '',
        ];

        if ( $field['required'] ?? false ) $attrs['required'] = 'required';

        $validation = $field['validation'] ?? [];
        if ( isset( $validation['min_length'] ) ) $attrs['minlength'] = $validation['min_length'];
        if ( isset( $validation['max_length'] ) ) $attrs['maxlength'] = $validation['max_length'];
        if ( isset( $validation['pattern'] ) )    $attrs['pattern']   = $validation['pattern'];

        return '<input class="klytos-form__input" ' . $this->buildAttrs( $attrs ) . '>';
    }

    private function renderNumberField( array $field ): string
    {
        $validation = $field['validation'] ?? [];
        $attrs = [
            'type'  => $field['type'],
            'id'    => $field['id'],
            'name'  => $field['id'],
            'value' => $field['default_value'] ?? '',
        ];

        if ( $field['required'] ?? false )    $attrs['required'] = 'required';
        if ( isset( $validation['min'] ) )    $attrs['min']  = $validation['min'];
        if ( isset( $validation['max'] ) )    $attrs['max']  = $validation['max'];
        if ( isset( $validation['step'] ) )   $attrs['step'] = $validation['step'];

        $class = $field['type'] === 'range' ? 'klytos-form__range' : 'klytos-form__input';
        return '<input class="' . $class . '" ' . $this->buildAttrs( $attrs ) . '>';
    }

    private function renderTextarea( array $field ): string
    {
        $validation = $field['validation'] ?? [];
        $attrs = [
            'id'          => $field['id'],
            'name'        => $field['id'],
            'placeholder' => $field['placeholder'] ?? '',
            'rows'        => $validation['rows'] ?? 5,
        ];

        if ( $field['required'] ?? false ) $attrs['required'] = 'required';
        if ( isset( $validation['min_length'] ) ) $attrs['minlength'] = $validation['min_length'];
        if ( isset( $validation['max_length'] ) ) $attrs['maxlength'] = $validation['max_length'];

        return '<textarea class="klytos-form__textarea" ' . $this->buildAttrs( $attrs ) . '>'
            . htmlspecialchars( $field['default_value'] ?? '' ) . '</textarea>';
    }

    private function renderSelect( array $field ): string
    {
        $attrs = [ 'id' => $field['id'], 'name' => $field['id'] ];
        if ( $field['required'] ?? false ) $attrs['required'] = 'required';
        if ( $field['multiple'] ?? false ) {
            $attrs['multiple'] = 'multiple';
            $attrs['name'] = $field['id'] . '[]';
        }

        $html = '<select class="klytos-form__select" ' . $this->buildAttrs( $attrs ) . '>';
        foreach ( $field['options'] ?? [] as $opt ) {
            $selected = ( $field['default_value'] ?? '' ) === $opt['value'] ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars( $opt['value'] ) . '"' . $selected . '>'
                . htmlspecialchars( $opt['label'] ) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    private function renderRadioGroup( array $field ): string
    {
        $html = '<div class="klytos-form__radio-group">';
        foreach ( $field['options'] ?? [] as $i => $opt ) {
            $radioId = $field['id'] . '_' . $i;
            $checked = ( $field['default_value'] ?? '' ) === $opt['value'] ? ' checked' : '';
            $html .= '<label class="klytos-form__radio-label" for="' . $radioId . '">';
            $html .= '<input type="radio" class="klytos-form__radio" id="' . $radioId . '" name="' . $field['id'] . '" value="' . htmlspecialchars( $opt['value'] ) . '"' . $checked;
            if ( $field['required'] ?? false ) $html .= ' required';
            $html .= '>';
            $html .= '<span>' . htmlspecialchars( $opt['label'] ) . '</span></label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderCheckbox( array $field ): string
    {
        $checked = ( $field['default_value'] ?? false ) ? ' checked' : '';
        return '<input type="checkbox" class="klytos-form__checkbox" id="' . $field['id'] . '" name="' . $field['id'] . '"' . $checked
            . ( ( $field['required'] ?? false ) ? ' required' : '' ) . '>';
    }

    private function renderCheckboxGroup( array $field ): string
    {
        $html = '<div class="klytos-form__checkbox-group">';
        foreach ( $field['options'] ?? [] as $i => $opt ) {
            $cbId = $field['id'] . '_' . $i;
            $html .= '<label class="klytos-form__checkbox-label" for="' . $cbId . '">';
            $html .= '<input type="checkbox" class="klytos-form__checkbox" id="' . $cbId . '" name="' . $field['id'] . '[]" value="' . htmlspecialchars( $opt['value'] ) . '">';
            $html .= '<span>' . htmlspecialchars( $opt['label'] ) . '</span></label>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderConsent( array $field ): string
    {
        return '<label class="klytos-form__consent-label">'
            . '<input type="checkbox" class="klytos-form__checkbox" id="' . $field['id'] . '" name="' . $field['id'] . '" required>'
            . '<span>' . ( $field['consent_text'] ?? $field['label'] ?? '' ) . '</span>'
            . '</label>';
    }

    private function renderDateTimeField( array $field ): string
    {
        $type = $field['type'];
        $attrs = [
            'type'  => $type,
            'id'    => $field['id'],
            'name'  => $field['id'],
            'value' => $field['default_value'] ?? '',
        ];
        if ( $field['required'] ?? false ) $attrs['required'] = 'required';

        $validation = $field['validation'] ?? [];
        if ( $type === 'date' ) {
            if ( isset( $validation['min_date'] ) ) $attrs['min'] = $validation['min_date'];
            if ( isset( $validation['max_date'] ) ) $attrs['max'] = $validation['max_date'];
        }

        return '<input class="klytos-form__input" ' . $this->buildAttrs( $attrs ) . '>';
    }

    private function renderFileField( array $field ): string
    {
        $attrs = [
            'type' => 'file',
            'id'   => $field['id'],
            'name' => $field['id'],
        ];
        if ( $field['required'] ?? false ) $attrs['required'] = 'required';

        $validation = $field['validation'] ?? [];
        if ( !empty( $validation['allowed_types'] ) ) {
            $attrs['accept'] = implode( ',', array_map( fn( $t ) => '.' . $t, $validation['allowed_types'] ) );
        }
        if ( isset( $validation['max_files'] ) && $validation['max_files'] > 1 ) {
            $attrs['multiple'] = 'multiple';
            $attrs['name'] = $field['id'] . '[]';
        }

        return '<input class="klytos-form__file" ' . $this->buildAttrs( $attrs ) . '>';
    }

    private function renderHiddenField( array $field ): string
    {
        return '<input type="hidden" name="' . htmlspecialchars( $field['id'] ) . '" value="' . htmlspecialchars( $field['default_value'] ?? '' ) . '">';
    }

    // ─── Helpers ────────────────────────────────────────────────

    private function buildAttrs( array $attrs ): string
    {
        $parts = [];
        foreach ( $attrs as $key => $value ) {
            if ( $value === true || $key === $value ) {
                $parts[] = $key;
            } else {
                $parts[] = $key . '="' . htmlspecialchars( (string) $value ) . '"';
            }
        }
        return implode( ' ', $parts );
    }

    private function getCsrfToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    // ─── CSS ────────────────────────────────────────────────────

    /**
     * Inject base CSS once per page.
     */
    private function renderCSS(): string
    {
        if ( self::$cssInjected ) {
            return '';
        }
        self::$cssInjected = true;

        return '<style>
/* --- Klytos Forms Base Styles --- */
.klytos-form {
    font-family: inherit;
    max-width: 640px;
    margin: 0 auto;
}

.klytos-form--stacked .klytos-form__field {
    margin-bottom: 1.25rem;
}

.klytos-form--inline .klytos-form__field {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.klytos-form__label {
    display: block;
    font-weight: 500;
    margin-bottom: 0.375rem;
    color: var(--klytos-text, #1a1a2e);
    font-size: 0.9375rem;
}

.klytos-form__required {
    color: #dc2626;
    margin-left: 0.125rem;
}

.klytos-form__input,
.klytos-form__textarea,
.klytos-form__select,
.klytos-form__file {
    display: block;
    width: 100%;
    padding: 0.625rem 0.875rem;
    font-size: 0.9375rem;
    line-height: 1.5;
    color: var(--klytos-text, #1a1a2e);
    background-color: var(--klytos-surface, #ffffff);
    border: 1px solid var(--klytos-border, #d1d5db);
    border-radius: var(--klytos-radius, 0.5rem);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    font-family: inherit;
    box-sizing: border-box;
}

.klytos-form__input:focus,
.klytos-form__textarea:focus,
.klytos-form__select:focus {
    outline: none;
    border-color: var(--klytos-primary, #6366f1);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.klytos-form__input:invalid:not(:placeholder-shown),
.klytos-form__textarea:invalid:not(:placeholder-shown) {
    border-color: #dc2626;
}

.klytos-form__textarea {
    resize: vertical;
    min-height: 100px;
}

.klytos-form__select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'12\' viewBox=\'0 0 12 12\'%3E%3Cpath fill=\'%236b7280\' d=\'M6 8L1 3h10z\'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    padding-right: 2.5rem;
}

.klytos-form__radio-group,
.klytos-form__checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.klytos-form__radio-label,
.klytos-form__checkbox-label,
.klytos-form__consent-label {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.9375rem;
    color: var(--klytos-text, #1a1a2e);
}

.klytos-form__radio,
.klytos-form__checkbox {
    margin-top: 0.1875rem;
    accent-color: var(--klytos-primary, #6366f1);
}

.klytos-form__section-title {
    font-size: 1.125rem;
    font-weight: 600;
    padding-top: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--klytos-border, #d1d5db);
    margin-bottom: 1rem;
    color: var(--klytos-text, #1a1a2e);
}

.klytos-form__actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    padding-top: 1rem;
    border-top: 1px solid var(--klytos-border, #e5e7eb);
    margin-top: 0.5rem;
}

.klytos-form__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.625rem 1.5rem;
    font-size: 0.9375rem;
    font-weight: 500;
    border-radius: var(--klytos-radius, 0.5rem);
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color 0.15s ease, box-shadow 0.15s ease;
    font-family: inherit;
}

.klytos-form__btn--primary {
    background-color: var(--klytos-primary, #6366f1);
    color: #ffffff;
}

.klytos-form__btn--primary:hover {
    filter: brightness(1.1);
}

.klytos-form__btn--secondary {
    background-color: transparent;
    color: var(--klytos-text, #1a1a2e);
    border-color: var(--klytos-border, #d1d5db);
}

.klytos-form__btn--secondary:hover {
    background-color: var(--klytos-surface, #f3f4f6);
}

.klytos-form__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.klytos-form__field-error {
    color: #dc2626;
    font-size: 0.8125rem;
    margin-top: 0.25rem;
    min-height: 0;
}

.klytos-form__success {
    padding: 1rem 1.25rem;
    background-color: #ecfdf5;
    border: 1px solid #6ee7b7;
    border-radius: var(--klytos-radius, 0.5rem);
    color: #065f46;
    font-size: 0.9375rem;
}

.klytos-form__error {
    padding: 1rem 1.25rem;
    background-color: #fef2f2;
    border: 1px solid #fca5a5;
    border-radius: var(--klytos-radius, 0.5rem);
    color: #991b1b;
    font-size: 0.9375rem;
}

/* --- Stepper --- */
.klytos-form-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-bottom: 2rem;
    padding: 0 1rem;
}

.klytos-form-step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.klytos-form-step__number {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    font-size: 0.8125rem;
    font-weight: 600;
    flex-shrink: 0;
}

.klytos-form-step--pending .klytos-form-step__number {
    background-color: var(--klytos-surface, #f3f4f6);
    color: var(--klytos-text-muted, #6b7280);
    border: 1px solid var(--klytos-border, #d1d5db);
}

.klytos-form-step--active .klytos-form-step__number {
    background-color: var(--klytos-primary, #6366f1);
    color: #ffffff;
}

.klytos-form-step--completed .klytos-form-step__number {
    background-color: #10b981;
    color: #ffffff;
}

.klytos-form-step__title {
    font-size: 0.8125rem;
    color: var(--klytos-text-muted, #6b7280);
}

.klytos-form-step--active .klytos-form-step__title {
    color: var(--klytos-text, #1a1a2e);
    font-weight: 500;
}

.klytos-form-step__connector {
    flex: 1;
    height: 1px;
    background-color: var(--klytos-border, #d1d5db);
    margin: 0 0.75rem;
    min-width: 1.5rem;
}

/* --- Range slider --- */
.klytos-form__range {
    width: 100%;
    accent-color: var(--klytos-primary, #6366f1);
}

/* --- File input --- */
.klytos-form__file {
    padding: 0.5rem;
}

/* --- Responsive --- */
@media (max-width: 640px) {
    .klytos-form {
        padding: 0 0.5rem;
    }
    .klytos-form__actions {
        flex-direction: column;
    }
    .klytos-form__btn {
        width: 100%;
    }
    .klytos-form-step__title {
        display: none;
    }
}
</style>';
    }
}
