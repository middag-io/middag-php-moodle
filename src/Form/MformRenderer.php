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
// File-scope host-library include: runs at autoload, before any test's coverage window.
// @codeCoverageIgnoreStart
global $CFG;

require_once $CFG->libdir . '/formslib.php';
// @codeCoverageIgnoreEnd

use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Form\Contract\FieldInterface;
use Middag\Ui\Form\Contract\FormInterface;
use Middag\Ui\Form\Contract\FormRendererInterface;
use Middag\Ui\Form\FieldMessage;
use Middag\Ui\Shared\Enum\RenderTarget;
use Middag\Ui\Shared\ValueObject\RendererOutput;
use moodleform;

/**
 * Moodle-native (mform) form renderer adapter.
 *
 * Iterates the form schema, delegates field mapping to MformFieldMapper,
 * builds an anonymous moodleform subclass, captures its HTML output and
 * returns a RendererOutput::html().
 *
 * @internal
 */
final readonly class MformRenderer implements FormRendererInterface
{
    public function __construct(private MformFieldMapper $mapper) {}

    /** {@inheritdoc} */
    public static function target(): RenderTarget
    {
        return RenderTarget::Html;
    }

    /** {@inheritdoc} */
    public function render(FormInterface $form): RendererOutput
    {
        $state = $form->state();
        $mapper = $this->mapper;

        // Collect mform_element_specs from the schema in document order.
        $specs = [];
        foreach ($this->walk($form->schema()) as $item) {
            if ($item instanceof FieldInterface) {
                $specs[] = $mapper->map($item->toDefinition());
            }
            // LayoutElementInterface nodes (section / group) do not produce
            // separate mform calls here; walk() recurses into their children.
        }

        $state_values = $state->values();
        $state_errors = $this->errorTexts($state->errors());

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

                // Apply per-field errors from form_state (already reduced to text).
                foreach ($params['state_errors'] as $field => $msg) {
                    $mform->setElementError($field, $msg);
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

        return RendererOutput::html(RenderTarget::Html, $html);
    }

    /**
     * Reduce each field's error to the text mform shows.
     *
     * A structured message ({@see FieldMessage} or its serialized array) shows
     * its server-resolved `message`; a list joins its messages with a space.
     *
     * @param array<string, array{message: string, key: string, domain: string, params?: array<string, mixed>}|FieldMessage|list<array{message: string, key: string, domain: string, params?: array<string, mixed>}|FieldMessage|string>|string> $errors
     *
     * @return array<string, string>
     */
    private function errorTexts(array $errors): array
    {
        return array_map(
            static fn (array|FieldMessage|string $error): string => is_array($error) && array_is_list($error)
                ? implode(' ', array_map(self::messageText(...), $error))
                : self::messageText($error),
            $errors,
        );
    }

    /**
     * The text of one message: a string as given, a structured message by its `message`.
     *
     * @param array{message: string, key: string, domain: string, params?: array<string, mixed>}|FieldMessage|string $message
     */
    private static function messageText(array|FieldMessage|string $message): string
    {
        if ($message instanceof FieldMessage) {
            return $message->message;
        }

        return is_array($message) ? $message['message'] : $message;
    }

    /**
     * Depth-first traversal of a schema array.
     *
     * Yields every FieldInterface and LayoutElementInterface node,
     * recursing into children of layout elements.
     *
     * @param array<int, FieldInterface|LayoutElementInterface> $schema
     *
     * @return iterable<FieldInterface|LayoutElementInterface>
     */
    private function walk(array $schema): iterable
    {
        foreach ($schema as $item) {
            yield $item;
            if ($item instanceof LayoutElementInterface) {
                yield from $this->walk($item->children());
            }
        }
    }
}
