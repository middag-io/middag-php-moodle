<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Support;

use core\output\renderable;

/**
 * Output support wrapper for Moodle's $OUTPUT global.
 *
 * @api
 */
class OutputSupport
{
    /**
     * Outputs a box.
     *
     * @param string $contents   The contents of the box
     * @param string $classes    A space-separated list of CSS classes
     * @param string $id         An optional ID
     * @param array  $attributes an array of other attributes to give the box
     *
     * @return string the HTML to output
     */
    public static function box($contents, $classes = 'generalbox', $id = null, $attributes = [])
    {
        global $OUTPUT;

        return $OUTPUT->box($contents, $classes, $id, $attributes);
    }

    /**
     * Prints the page header HTML.
     *
     * @param mixed $return
     */
    public static function header($return = false): ?string
    {
        global $OUTPUT;

        $h = $OUTPUT->header();
        if ($return) {
            return $h;
        }

        echo $h;

        return null;
    }

    /**
     * Prints the page footer HTML.
     *
     * @param mixed $return
     */
    public static function footer($return = false): ?string
    {
        global $OUTPUT;

        $r = $OUTPUT->footer();
        if ($return) {
            return $r;
        }

        echo $r;

        return null;
    }

    /**
     * Renders a renderable object.
     *
     * @param renderable $renderable Object to render
     *
     * @return string Rendered HTML
     */
    public static function render(renderable $renderable): string
    {
        global $OUTPUT;

        return $OUTPUT->render($renderable);
    }

    /**
     * Renders a template with a given context.
     *
     * @param string $templatename Template name (e.g., 'component/name').
     * @param array  $context      Data context for the template
     *
     * @return string Rendered HTML
     */
    public static function renderFromTemplate(string $templatename, array $context): string
    {
        global $OUTPUT;

        return $OUTPUT->render_from_template($templatename, $context);
    }

    /**
     * Displays a notification message.
     *
     * @param string $message Message text
     * @param string $type    Notification type
     *
     * @return string Rendered notification HTML
     */
    public static function notification(string $message, string $type = 'notifyinfo'): string
    {
        global $OUTPUT;

        return $OUTPUT->notification($message, $type);
    }
}
