<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Output\Contract;

/**
 * Interface for View rendering.
 * Abstracts Moodle's $PAGE and $OUTPUT.
 *
 * @api
 */
interface ViewAdapterInterface
{
    /**
     * Set the page title.
     *
     * @param string $title
     */
    public function setTitle(string $title): void;

    /**
     * Set the page heading.
     *
     * @param string $heading
     */
    public function setHeading(string $heading): void;

    /**
     * Set the page layout (e.g., 'admin', 'standard').
     *
     * @param string $layout
     */
    public function setLayout(string $layout): void;

    /**
     * Add a navigation node to the breadcrumbs.
     *
     * @param string $text
     * @param mixed  $url
     */
    public function addBreadcrumb(string $text, mixed $url = null): void;

    /**
     * Render a template with data.
     *
     * @param string $template_name mustache template name (component/name)
     * @param array  $data          template data
     *
     * @return string the rendered HTML
     */
    public function renderTemplate(string $template_name, array $data = []): string;

    /**
     * Render raw HTML as a full page.
     *
     * @param string $content
     *
     * @return string the full page HTML
     */
    public function renderPage(string $content): string;
}
