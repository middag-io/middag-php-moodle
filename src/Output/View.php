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

use core\url as moodle_url;
use Middag\Moodle\Output\Contract\ViewAdapterInterface as view_adapter_interface;
use navigation_node;

/**
 * Adapter that provides View rendering using Moodle's native $PAGE and $OUTPUT.
 *
 * @internal
 */
class View implements view_adapter_interface
{
    /**
     * {@inheritDoc}
     */
    public function setTitle(string $title): void
    {
        global $PAGE;
        $PAGE->set_title($title);
    }

    /**
     * {@inheritDoc}
     */
    public function setHeading(string $heading): void
    {
        global $PAGE;
        $PAGE->set_heading($heading);
    }

    /**
     * {@inheritDoc}
     */
    public function setLayout(string $layout): void
    {
        global $PAGE;
        $PAGE->set_pagelayout($layout);
    }

    /**
     * {@inheritDoc}
     */
    public function addBreadcrumb(string $text, mixed $url = null): void
    {
        global $PAGE;
        $moodle_url = $url instanceof moodle_url ? $url : (is_string($url) ? new moodle_url($url) : null);
        $PAGE->navbar->add($text, $moodle_url, navigation_node::TYPE_CUSTOM);
    }

    /**
     * {@inheritDoc}
     */
    public function renderTemplate(string $template_name, array $data = []): string
    {
        global $OUTPUT;

        // template name is component/template
        return $OUTPUT->render_from_template($template_name, $data);
    }

    /**
     * {@inheritDoc}
     */
    public function renderPage(string $content): string
    {
        global $OUTPUT;

        $out = $OUTPUT->header();
        $out .= $content;

        return $out . $OUTPUT->footer();
    }
}
