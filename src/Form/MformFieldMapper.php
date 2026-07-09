<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Form;

use Middag\Ui\Form\FieldDefinition;
use Middag\Ui\Shared\Enum\FieldType;
use Middag\Ui\Shared\ValueObject\Translatable;

/**
 * Maps a FieldDefinition to a MformElementSpec ready for MformRenderer.
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
    public function map(FieldDefinition $def): MformElementSpec
    {
        $label = $this->resolveLabel($def);

        return match ($def->type) {
            FieldType::Text => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_TEXT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Textarea => new MformElementSpec(
                element: 'textarea',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_TEXT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Password => new MformElementSpec(
                element: 'passwordunmask',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Email => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_EMAIL,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Url => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_URL,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Int => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_INT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Float => new MformElementSpec(
                element: 'text',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_FLOAT,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Select => new MformElementSpec(
                element: 'select',
                name: $def->name,
                label_html: $label,
                options: $def->options,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Multiselect => new MformElementSpec(
                element: 'autocomplete',
                name: $def->name,
                label_html: $label,
                options: $def->options,
                element_args: ['multiple' => true],
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Radio => new MformElementSpec(
                element: 'radio',
                name: $def->name,
                label_html: $label,
                options: $def->options,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Checkbox => new MformElementSpec(
                element: 'advcheckbox',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_INT,
                default: $def->default,
            ),
            FieldType::Switch => new MformElementSpec(
                element: 'advcheckbox',
                name: $def->name,
                label_html: $label,
                param_type: PARAM_INT,
                default: $def->default,
            ),
            FieldType::Date => new MformElementSpec(
                element: 'date_selector',
                name: $def->name,
                label_html: $label,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Datetime => new MformElementSpec(
                element: 'date_time_selector',
                name: $def->name,
                label_html: $label,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Duration => new MformElementSpec(
                element: 'duration',
                name: $def->name,
                label_html: $label,
                default: $def->default,
            ),
            FieldType::File => new MformElementSpec(
                element: 'filepicker',
                name: $def->name,
                label_html: $label,
            ),
            FieldType::EntityPicker => new MformElementSpec(
                element: 'autocomplete',
                name: $def->name,
                label_html: $label,
                options: [],
                element_args: $def->attributes,
                param_type: PARAM_RAW,
                default: $def->default,
                rule: $def->constraints->required ? ['required'] : null,
            ),
            FieldType::Hidden => new MformElementSpec(
                element: 'hidden',
                name: $def->name,
                label_html: '',
                param_type: PARAM_RAW,
                default: $def->default,
            ),
            FieldType::Static => new MformElementSpec(
                element: 'static',
                name: $def->name,
                label_html: $label,
            ),
            FieldType::Header => new MformElementSpec(
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

    /** Derive a display label from FieldDefinition, passing the key through. */
    private function resolveLabel(FieldDefinition $def): string
    {
        if ($def->label instanceof Translatable) {
            return $def->label->key;
        }

        if (is_string($def->label) && $def->label !== '') {
            return $def->label;
        }

        return ucfirst(str_replace('_', ' ', $def->name));
    }
}
