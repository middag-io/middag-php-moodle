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

// THIS file and ONLY this file in the form subsystem may reference moodleform.
global $CFG;

require_once $CFG->libdir . '/formslib.php';

use Middag\Ui\Block\Contract\LayoutElementInterface as layout_element_interface;
use Middag\Ui\Form\Contract\FieldInterface as field_interface;
use Middag\Ui\Form\Contract\FormInterface as form_interface;
use Middag\Ui\Form\Contract\FormRendererInterface as form_renderer_interface;
use Middag\Ui\Shared\Enum\RenderTarget as render_target;
use Middag\Ui\Shared\ValueObject\RendererOutput as renderer_output;
use moodleform;

/**
 * Moodle-native (mform) form renderer adapter (ADR-805).
 *
 * Iterates the form schema, delegates field mapping to MformFieldMapper,
 * builds an anonymous moodleform subclass, captures its HTML output and
 * returns a renderer_output::html().
 *
 * @internal
 */
final readonly class MformRenderer implements form_renderer_interface
{
    public function __construct(private MformFieldMapper $mapper) {}

    /** {@inheritdoc} */
    public static function target(): render_target
    {
        return render_target::HTML;
    }

    /** {@inheritdoc} */
    public function render(form_interface $form): renderer_output
    {
        $state = $form->state();
        $mapper = $this->mapper;

        // Collect mform_element_specs from the schema in document order.
        $specs = [];
        foreach ($this->walk($form->schema()) as $item) {
            if ($item instanceof field_interface) {
                $specs[] = $mapper->map($item->toDefinition());
            }
            // layout_element_interface nodes (section / group) do not produce
            // separate mform calls here; walk() recurses into their children.
        }

        $state_values = $state->values();
        $state_errors = $state->errors();

        // Build an anonymous moodleform that applies the collected specs.
        $mform_instance = new class(null, ['specs' => $specs, 'state_values' => $state_values, 'state_errors' => $state_errors]) extends moodleform {
            public function definition(): void
            {
                $mform = $this->_form;
                $params = $this->_customdata;

                /** @var MformElementSpec $spec */
                foreach ($params['specs'] as $spec) {
                    $args = [$spec->element, $spec->name, $spec->label_html];
                    if ($spec->options !== []) {
                        $args[] = $spec->options;
                    }
                    if ($spec->element_args !== []) {
                        $args[] = $spec->element_args;
                    }
                    $mform->addElement(...$args);

                    if ($spec->param_type !== null) {
                        $mform->setType($spec->name, $spec->param_type);
                    }
                    if ($spec->default !== null) {
                        $mform->setDefault($spec->name, $spec->default);
                    }
                    if ($spec->rule !== null) {
                        $mform->addRule($spec->name, null, $spec->rule[0]);
                    }
                }

                // Apply per-field errors from form_state.
                foreach ($params['state_errors'] as $field => $msg) {
                    $mform->setElementError(
                        $field,
                        is_array($msg) ? implode(' ', $msg) : $msg
                    );
                }

                // Populate existing values.
                if ($params['state_values'] !== []) {
                    $this->set_data((object) $params['state_values']);
                }
            }
        };

        ob_start();
        $mform_instance->display();
        $html = ob_get_clean() ?: '';

        return renderer_output::html(render_target::HTML, $html);
    }

    /**
     * Depth-first traversal of a schema array.
     *
     * Yields every field_interface and layout_element_interface node,
     * recursing into children of layout elements.
     *
     * @param array<int, field_interface|layout_element_interface> $schema
     *
     * @return iterable<field_interface|layout_element_interface>
     */
    private function walk(array $schema): iterable
    {
        foreach ($schema as $item) {
            yield $item;
            if ($item instanceof layout_element_interface) {
                yield from $this->walk($item->children());
            }
        }
    }
}
