<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Infrastructure\Form;

use Middag\Ui\Form\FieldDefinition as field_definition;
use Middag\Ui\Shared\Data\Translatable as translatable;
use Middag\Ui\Shared\Enum\FieldType as field_type;

/**
 * Maps a field_definition to a MformElementSpec ready for MformRenderer.
 *
 * Does NOT import or depend on moodleform. PARAM_* constants are resolved at
 * call time inside MformRenderer, which runs inside a Moodle bootstrap context.
 * The mapper only constructs specs using PHP constants that are defined when
 * the Moodle lib is loaded.
 *
 * @internal
 */
final class MformFieldMapper
{
    /**
     * Produce a MformElementSpec for the given field definition.
     *
     * Label: passes the raw label key (or a fallback derived from name) as
     * label_html. The MformRenderer is responsible for lang string resolution
     * inside a live Moodle request context.
     */
    public function map(field_definition $def): MformElementSpec
    {
        $label = $this->resolveLabel($def);

        return match ($def->type) {
            field_type::TEXT => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_TEXT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::TEXTAREA => new MformElementSpec(
                element: 'textarea',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_TEXT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::PASSWORD => new MformElementSpec(
                element: 'passwordunmask',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::EMAIL => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_EMAIL,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::URL => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_URL,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::INT => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_INT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::FLOAT => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_FLOAT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::SELECT => new MformElementSpec(
                element: 'select',
                name: $def->name,
                label_html: $label,
                options: $def->options,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::MULTISELECT => new MformElementSpec(
                element: 'autocomplete',
                name: $def->name,
                label_html: $label,
                options: $def->options,
                element_args: ['multiple' => true],
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::RADIO => new MformElementSpec(
                element: 'radio',
                name: $def->name,
                label_html: $label,
                options: $def->options,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::CHECKBOX => new MformElementSpec(
                element: 'advcheckbox',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_INT,
                default: $def->default,
            ),
            field_type::SWITCH => new MformElementSpec(
                element: 'advcheckbox',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_INT,
                default: $def->default,
            ),
            field_type::DATE => new MformElementSpec(
                element: 'date_selector',
                name: $def->name,
                label_html: $label,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::DATETIME => new MformElementSpec(
                element: 'date_time_selector',
                name: $def->name,
                label_html: $label,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::DURATION => new MformElementSpec(
                element: 'duration',
                name: $def->name,
                label_html: $label,
                default: $def->default,
            ),
            field_type::FILE => new MformElementSpec(
                element: 'filepicker',
                name: $def->name,
                label_html: $label,
            ),
            field_type::ENTITY_PICKER => new MformElementSpec(
                element: 'autocomplete',
                name: $def->name,
                label_html: $label,
                options: [],
                element_args: $def->attributes,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            field_type::HIDDEN => new MformElementSpec(
                element: 'hidden',
                name: $def->name,
                label_html: '',
                param_type: PARAM_RAW,
                default: $def->default,
            ),
            field_type::STATIC => new MformElementSpec(
                element: 'static',
                name: $def->name,
                label_html: $label,
            ),
            field_type::HEADER => new MformElementSpec(
                element: 'header',
                name: $def->name,
                label_html: $label,
            ),
            // Field types without a dedicated mform element map to a plain text
            // input (richtext, otp, slider, native_select, time, autocomplete,
            // tags). Keeps the mapper total over the UI field-type catalog.
            default => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_TEXT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
        };
    }

    /** Derive a display label from field_definition, passing the key through. */
    private function resolveLabel(field_definition $def): string
    {
        if ($def->label instanceof translatable) {
            return $def->label->key;
        }

        if (is_string($def->label) && $def->label !== '') {
            return $def->label;
        }

        return ucfirst(str_replace('_', ' ', $def->name));
    }
}
