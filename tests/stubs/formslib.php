<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

if (!class_exists('MoodleQuickForm', false)) {
    /**
     * Recording stand-in for Moodle's MoodleQuickForm ($this->_form). Captures the
     * element/type/default/rule/error calls MformRenderer::definition() makes.
     */
    class MoodleQuickForm
    {
        /** @var array<int, array> */
        public array $elements = [];

        /** @var array<string, mixed> */
        public array $types = [];

        /** @var array<string, mixed> */
        public array $defaults = [];

        /** @var array<int, array> */
        public array $rules = [];

        /** @var array<string, mixed> */
        public array $elementErrors = [];

        public function addElement(mixed ...$args): mixed
        {
            $this->elements[] = $args;

            return $args;
        }

        public function setType(string $name, mixed $type): void
        {
            $this->types[$name] = $type;
        }

        public function setDefault(string $name, mixed $default): void
        {
            $this->defaults[$name] = $default;
        }

        public function addRule(string $name, mixed $message, mixed $type): void
        {
            $this->rules[] = [$name, $type];
        }

        public function setElementError(string $name, mixed $message): void
        {
            $this->elementErrors[$name] = $message;
        }
    }
}

if (!class_exists('moodleform', false)) {
    /**
     * Stand-in for Moodle's moodleform. The real base calls definition() from its
     * constructor after wiring $this->_form; this stub mirrors that so the
     * anonymous subclass MformRenderer builds runs its definition() immediately.
     */
    abstract class moodleform
    {
        public MoodleQuickForm $_form;

        public mixed $_submitted_data = null;

        public function __construct(
            mixed $action = null,
            public mixed $_customdata = null,
            string $method = 'post',
            string $target = '',
            mixed $attributes = null,
            bool $editable = true,
            mixed $ajaxformdata = null,
        ) {
            $this->_form = new MoodleQuickForm();
            $this->definition();
        }

        abstract public function definition();

        public function set_data(mixed $data): void
        {
            $this->_submitted_data = $data;
        }

        public function display(): void
        {
            echo '<form class="mform">rendered</form>';
        }
    }
}
