<?php

/**
 * Klytos — Form Conditional Engine
 * Evaluates conditional logic rules for form fields and notifications.
 *
 * Supports show/hide actions with AND/OR logic across 14 operators.
 * Used both during form submission (backend validation) and by the
 * JavaScript engine (frontend reactivity).
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

namespace Klytos\Core;

class FormConditionalEngine
{
    /**
     * Evaluate whether a conditional is met given form data.
     *
     * @param  array|null $conditional Conditional object from field/notification.
     * @param  array      $formData   Submitted data (field_id => value).
     * @return bool       true if the field should be visible / notification should send.
     */
    public function evaluate( ?array $conditional, array $formData ): bool
    {
        if ( $conditional === null ) {
            return true; // No conditional = always visible/active
        }

        $action = $conditional['action'] ?? 'show';
        $logic  = $conditional['logic'] ?? 'all';
        $rules  = $conditional['rules'] ?? [];

        if ( empty( $rules ) ) {
            return true;
        }

        $results = [];
        foreach ( $rules as $rule ) {
            $results[] = $this->evaluateRule( $rule, $formData );
        }

        $passed = ( $logic === 'all' )
            ? !in_array( false, $results, true )
            : in_array( true, $results, true );

        // If action is "show", return $passed directly.
        // If action is "hide", invert.
        return ( $action === 'show' ) ? $passed : !$passed;
    }

    /**
     * Evaluate a single conditional rule.
     *
     * @param  array $rule     Rule definition with field_id, operator, value.
     * @param  array $formData Submitted data.
     * @return bool
     */
    private function evaluateRule( array $rule, array $formData ): bool
    {
        $fieldValue = $formData[$rule['field_id']] ?? null;
        $ruleValue  = $rule['value'] ?? null;
        $operator   = $rule['operator'] ?? 'is';

        return match ( $operator ) {
            'is'             => (string) $fieldValue === (string) $ruleValue,
            'is_not'         => (string) $fieldValue !== (string) $ruleValue,
            'contains'       => str_contains( (string) $fieldValue, (string) $ruleValue ),
            'not_contains'   => !str_contains( (string) $fieldValue, (string) $ruleValue ),
            'starts_with'    => str_starts_with( (string) $fieldValue, (string) $ruleValue ),
            'ends_with'      => str_ends_with( (string) $fieldValue, (string) $ruleValue ),
            'greater_than'   => (float) $fieldValue > (float) $ruleValue,
            'less_than'      => (float) $fieldValue < (float) $ruleValue,
            'is_empty'       => $fieldValue === null || $fieldValue === '' || $fieldValue === [],
            'is_not_empty'   => $fieldValue !== null && $fieldValue !== '' && $fieldValue !== [],
            'is_checked'     => (bool) $fieldValue === true || $fieldValue === '1' || $fieldValue === 'on',
            'is_not_checked' => (bool) $fieldValue === false || $fieldValue === '0' || $fieldValue === '' || $fieldValue === null,
            'in'             => is_array( $ruleValue ) && in_array( (string) $fieldValue, $ruleValue, true ),
            'not_in'         => is_array( $ruleValue ) && !in_array( (string) $fieldValue, $ruleValue, true ),
            default          => true,
        };
    }
}
