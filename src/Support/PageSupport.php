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

use core\context;
use core\context\system as context_system;
use core\exception\coding_exception;
use core\output\renderer_base;
use core\url as moodle_url;
use Middag\Moodle\Shared\Util\Debug;
use navigation_node;

use function admin_externalpage_setup;

/**
 * Utility functions for admin pages, navigation, and system setup.
 *
 * @api
 */
class PageSupport
{
    /**
     * Sets the current page context.
     *
     * @param context $context Context object
     */
    public static function setContext(context $context): void
    {
        global $PAGE;
        $PAGE->set_context($context);
    }

    /**
     * Sets the page layout.
     *
     * @param string $layout The layout name (e.g., 'admin', 'standard').
     */
    public static function setPagelayout(string $layout): void
    {
        global $PAGE;
        $PAGE->set_pagelayout($layout);
    }

    /**
     * Sets the page title.
     *
     * @param string $title Page title
     */
    public static function setTitle(string $title): void
    {
        global $PAGE;
        $PAGE->set_title($title);
    }

    /**
     * Sets the page heading.
     *
     * @param string $heading Page heading
     */
    public static function setHeading(string $heading): void
    {
        global $PAGE;
        $PAGE->set_heading($heading);
    }

    /**
     * Disable the secondary navigation (Moodle 4.x admin submenu).
     */
    public static function setSecondaryNavigation(bool $enabled): void
    {
        global $PAGE;
        $PAGE->set_secondary_navigation($enabled);
    }

    /**
     * Sets the page URL.
     *
     * @param moodle_url $url Page URL object
     */
    public static function setUrl(moodle_url $url): void
    {
        global $PAGE;
        $PAGE->set_url($url);
    }

    /**
     * Adds an item to the navigation bar.
     *
     * @param string                 $text   item label
     * @param null|moodle_url|string $action item action URL
     */
    public static function navbarAdd(string $text, $action = null): void
    {
        global $PAGE;
        $PAGE->navbar->add($text, $action);
    }

    /**
     * Retrieves a renderer for a specific component.
     *
     * @param string $component component name
     *
     * @return renderer_base renderer instance
     */
    public static function getRenderer(string $component): renderer_base
    {
        global $PAGE;

        return $PAGE->get_renderer($component);
    }

    /**
     * Loads admin navigation for a specific section.
     *
     * @param string $section      admin section identifier
     * @param int    $jump         number of path elements to skip
     * @param bool   $ignoreactive whether to ignore the active node
     */
    public static function adminLoadNavigation($section, $jump = 2, $ignoreactive = true): void
    {
        global $CFG, $PAGE;

        require_once $CFG->libdir . '/adminlib.php';

        $PAGE->set_pagelayout('admin');
        navigation_node::require_admin_tree();
        $adminroot = admin_get_root(false, false); // settings not required for external pages
        $extpage = $adminroot->locate($section, true);
        // locate() returns a reference to NULL for an unknown or capability-gated
        // section; reading ->path on that would warn and then count(null)
        // TypeErrors. Degrade gracefully to an empty path (no extra breadcrumbs).
        $path = is_object($extpage) ? $extpage->path : [];
        $node = $PAGE->settingsnav;
        $i = 0;
        while ($node && count($path) > 0) {
            $node = $node->get(array_pop($path));
            // A path segment from the full admin tree can be missing from the
            // per-user settingsnav (capability-filtered); get() returns false.
            // Re-check before reading ->text/->action so we don't push a blank
            // breadcrumb (false->text) — only the NEXT iteration's guard would.
            if (!$node) {
                break;
            }
            ++$i;
            if ($i > $jump) {
                $PAGE->navbar->add($node->text, $node->action);
            }
        }
        if ($ignoreactive) {
            $PAGE->navbar->ignore_active($ignoreactive);
        }
    }

    /**
     * Performs setup for an admin external page.
     *
     * @param string $section admin section identifier
     */
    public static function adminExternalpageSetup(string $section): void
    {
        global $CFG;

        require_once $CFG->libdir . '/adminlib.php';
        admin_externalpage_setup($section);
    }

    /**
     * Renders a simple page with Markdown content.
     *
     * @param string            $content Markdown content
     * @param moodle_url|string $url     Page URL
     * @param string            $title   Page title
     * @param null|context      $context Page context (default: system)
     */
    public static function pageMarkdown(string $content, moodle_url|string $url, string $title, ?context $context = null): void
    {
        global $PAGE, $OUTPUT;

        if (is_null($context)) {
            $context = context_system::instance();
        }

        $PAGE->set_context($context);

        try {
            $PAGE->set_url($url);
        } catch (coding_exception $codingexception) {
            Debug::traceException($codingexception);
        }
        $PAGE->set_title($title);

        // Creates a JavaScript snippet to add Bootstrap-like class to tables
        $PAGE->requires->js_amd_inline("
            requirejs(['jquery'], function($) {
                $(document).ready(function() {
                    $('#page').find('table').addClass('table');
                });
            });");

        echo $OUTPUT->header();
        echo markdown_to_html($content);
        echo $OUTPUT->footer();
    }
}
