<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Output;

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Support\HtmlWriterSupport as html_writer_support;

/**
 * Base class for internal UI Blocks/Widgets.
 *
 * NOTE: This is NOT a standard Moodle Block (block_base).
 * It is a reusable UI component/driver used by block_middag or middag pages.
 *
 * This allows the 'local' plugin to drive content dynamically into
 * 'block_middag' without modifying the block plugin itself.
 *
 * @internal
 */
abstract class AbstractBlock
{
    /**
     * The template associated with this block.
     *
     * This constant holds the name the template
     * used to render the output of the block. If empty, a
     * default template might be used.
     *
     * Override in child classes.
     */
    public const TEMPLATE = '';

    /** @var string The title of the block. */
    protected string $title = '';

    /** @var array<string, mixed> The content of the block. */
    protected array $content = [];

    /** @var array<string, string> Additional HTML attributes for the block container. */
    protected array $attributes = [];

    /** @var bool Flag to check if content has been processed to allow caching (Memoization) */
    private bool $processed = false;

    /**
     * Logic to determine and set the block title.
     * Called automatically by get_title() if needed.
     */
    abstract public function setTitle(): void;

    /**
     * Get the block title, lazily loading it if empty.
     *
     * @return string the title of the block
     */
    public function getTitle(): string
    {
        if ($this->title === '') {
            $this->setTitle();
        }

        return $this->title;
    }

    /**
     * Main logic to fetch data for the block.
     *
     * @return array<string, mixed> data to be passed to the template
     */
    abstract public function processContent(): array;

    /**
     * Retrieves all the content of the block with memoization.
     *
     * Using a boolean flag ($processed) is safer than checking for empty array,
     * as the content might legitimately be empty after processing.
     *
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        if (!$this->processed) {
            $this->content = $this->processContent();
            $this->processed = true;
        }

        return $this->content;
    }

    /**
     * Sets an HTML attribute for the block wrapper.
     *
     * @param string $key   the attribute name
     * @param string $value the attribute value
     */
    public function setAttribute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Renders the widget using the specified template and processed content.
     *
     * @return string the rendered output of the widget
     */
    public function render(): string
    {
        global $OUTPUT;

        // Ensure dependencies are loaded
        if (!class_exists(Widget::class)) {
            return ''; // Fail silently or log error if widget class is missing
        }

        // Prepare data using get_content() to leverage memoization
        $data = $this->getContent();

        // Ensure title is available in data if the template needs it
        if (!isset($data['title'])) {
            $data['title'] = $this->getTitle();
        }

        // We wrap the data in the widget class responsible for the '{component}/widget' structure
        $widget = new Widget(static::TEMPLATE, ['data' => $data]);

        // Export for template usually requires the renderer instance
        return $OUTPUT->render_from_template(ComponentContext::name() . '/widget', $widget->export_for_template($OUTPUT));
    }

    /**
     * Gets the attributes string safely using Moodle's html_writer.
     * This handles escaping and boolean attributes correctly.
     *
     * @return string the attributes formatted for HTML
     */
    protected function getAttributes(): string
    {
        if ($this->attributes === []) {
            return '';
        }

        return html_writer_support::attributes($this->attributes);
    }
}
