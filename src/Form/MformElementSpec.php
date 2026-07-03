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

/**
 * Immutable spec that describes how MformRenderer should call MoodleQuickForm::addElement().
 *
 * Produced by MformFieldMapper; consumed exclusively by MformRenderer.
 * Does not import moodleform — it is a plain value object.
 *
 * @internal
 */
final readonly class MformElementSpec
{
    /**
     * @param string                   $element      MoodleQuickForm element type ('text', 'select', 'hidden', …)
     * @param string                   $name         Field name attribute
     * @param string                   $label_html   Label string (raw or i18n key — renderer resolves)
     * @param array<int|string, mixed> $options      Options array for select/autocomplete elements
     * @param array<string, mixed>     $element_args Extra args passed after options to addElement()
     * @param null|string              $param_type   PARAM_* constant for setType(); null = skip setType()
     * @param mixed                    $default      Default value; null = skip setDefault()
     * @param null|string[]            $rule         Rule array e.g. ['required']; null = skip addRule()
     */
    public function __construct(
        public string $element,
        public string $name,
        public string $label_html,
        public array $options = [],
        public array $element_args = [],
        public ?string $param_type = null,
        public mixed $default = null,
        public ?array $rule = null,
    ) {}
}
