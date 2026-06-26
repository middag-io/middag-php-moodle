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

use core\output\html_writer as core_html_writer;
use core\url as moodle_url;
use core_table\output\html_table;

/**
 * Utility functions for generating HTML output.
 *
 * @internal
 */
class HtmlWriterSupport
{
    /**
     * Generates a full HTML tag.
     *
     * @param string     $tagname    Tag name
     * @param string     $contents   The tag contents
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated HTML tag
     */
    public static function tag(string $tagname, string $contents, ?array $attributes = null): string
    {
        return core_html_writer::tag($tagname, $contents, $attributes);
    }

    /**
     * Generates an opening HTML tag.
     *
     * @param string     $tagname    Tag name
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated opening tag
     */
    public static function startTag(string $tagname, ?array $attributes = null): string
    {
        return core_html_writer::start_tag($tagname, $attributes);
    }

    /**
     * Generates a closing HTML tag.
     *
     * @param string $tagname Tag name
     *
     * @return string the generated closing tag
     */
    public static function endTag(string $tagname): string
    {
        return core_html_writer::end_tag($tagname);
    }

    /**
     * Generates an empty (self-closing) HTML tag.
     *
     * @param string     $tagname    Tag name
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated empty tag
     */
    public static function emptyTag(string $tagname, ?array $attributes = null): string
    {
        return core_html_writer::empty_tag($tagname, $attributes);
    }

    /**
     * Generates a non-empty HTML tag, even if contents are empty.
     *
     * @param string     $tagname    Tag name
     * @param mixed      $contents   The tag contents
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated HTML tag
     */
    public static function nonemptyTag(string $tagname, $contents, ?array $attributes = null): string
    {
        return core_html_writer::nonempty_tag($tagname, $contents, $attributes);
    }

    /**
     * Generates a single HTML attribute.
     *
     * @param string $name  Attribute name
     * @param mixed  $value Attribute value
     *
     * @return string the generated HTML attribute
     */
    public static function attribute(string $name, $value): string
    {
        return core_html_writer::attribute($name, $value);
    }

    /**
     * Generates multiple HTML attributes.
     *
     * @param null|array $attributes Map of attribute names to values
     *
     * @return string the generated HTML attributes
     */
    public static function attributes(?array $attributes = null): string
    {
        return core_html_writer::attributes($attributes);
    }

    /**
     * Generates an HTML img tag.
     *
     * @param moodle_url|string $src        The image source URL
     * @param string            $alt        The image alt text
     * @param null|array        $attributes Optional tag attributes
     *
     * @return string the generated img tag
     */
    public static function img($src, $alt, ?array $attributes = null): string
    {
        return core_html_writer::img($src, $alt, $attributes);
    }

    /**
     * Generates a random unique ID.
     *
     * @param string $base the ID base prefix
     *
     * @return string the generated ID
     */
    public static function randomId($base = 'random'): string
    {
        return core_html_writer::random_id($base);
    }

    /**
     * Generates an HTML anchor (link) tag.
     *
     * @param moodle_url|string $url        The link destination
     * @param string            $text       The link text
     * @param null|array        $attributes Optional tag attributes
     *
     * @return string the generated anchor tag
     */
    public static function link($url, string $text, ?array $attributes = null): string
    {
        return core_html_writer::link($url, $text, $attributes);
    }

    /**
     * Generates an HTML checkbox input.
     *
     * @param string     $name            The input name
     * @param string     $value           The input value
     * @param bool       $checked         Whether the checkbox is checked
     * @param string     $label           The checkbox label text
     * @param null|array $attributes      Optional tag attributes
     * @param null|array $labelattributes Optional label tag attributes
     *
     * @return string the generated checkbox HTML
     */
    public static function checkbox(
        string $name,
        string $value,
        bool $checked = true,
        string $label = '',
        ?array $attributes = null,
        ?array $labelattributes = null
    ): string {
        return core_html_writer::checkbox($name, $value, $checked, $label, $attributes, $labelattributes);
    }

    /**
     * Generates a yes/no select box.
     *
     * @param string     $name       The input name
     * @param bool       $selected   Whether 'yes' is selected
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated select HTML
     */
    public static function selectYesNo(string $name, bool $selected = true, ?array $attributes = null): string
    {
        return core_html_writer::select_yes_no($name, $selected, $attributes);
    }

    /**
     * Generates an HTML select box.
     *
     * @param array        $options    Map of option values to labels
     * @param string       $name       The input name
     * @param array|string $selected   Selected value(s)
     * @param array        $nothing    Value for the 'nothing selected' option
     * @param null|array   $attributes Optional tag attributes
     * @param array        $disabled   Disabled option values
     *
     * @return string the generated select HTML
     */
    public static function select(
        array $options,
        string $name,
        $selected = '',
        $nothing = ['' => 'choosedots'],
        ?array $attributes = null,
        array $disabled = []
    ): string {
        return core_html_writer::select($options, $name, $selected, $nothing, $attributes, $disabled);
    }

    /**
     * Generates date/time selection boxes.
     *
     * @param string     $type        The selection type (e.g., 'days', 'months', 'years').
     * @param string     $name        The input name
     * @param int        $currenttime The current timestamp
     * @param int        $step        The increment step
     * @param null|array $attributes  Optional tag attributes
     * @param float|int  $timezone    The timezone offset
     *
     * @return string the generated select HTML
     */
    public static function selectTime(
        string $type,
        string $name,
        int $currenttime = 0,
        int $step = 5,
        ?array $attributes = null,
        $timezone = 99
    ): string {
        return core_html_writer::select_time($type, $name, $currenttime, $step, $attributes, $timezone);
    }

    /**
     * Generates an HTML list (ul/ol).
     *
     * @param array      $items      List of items
     * @param null|array $attributes Optional tag attributes
     * @param string     $tag        The list tag (ul or ol)
     *
     * @return string the generated list HTML
     */
    public static function alist(array $items, ?array $attributes = null, string $tag = 'ul'): string
    {
        return core_html_writer::alist($items, $attributes, $tag);
    }

    /**
     * Generates hidden input tags for URL parameters.
     *
     * @param moodle_url $url     The URL containing parameters
     * @param null|array $exclude Parameter names to exclude
     *
     * @return string the generated hidden input tags
     */
    public static function inputHiddenParams(moodle_url $url, ?array $exclude = null): string
    {
        return core_html_writer::input_hidden_params($url, $exclude);
    }

    /**
     * Generates an HTML script tag.
     *
     * @param string                 $jscode the JavaScript code
     * @param null|moodle_url|string $url    the script source URL
     *
     * @return string the generated script tag
     */
    public static function script(string $jscode, $url = null): string
    {
        return core_html_writer::script($jscode, $url);
    }

    /**
     * Generates an HTML table.
     *
     * @param html_table $table the table object
     *
     * @return string the generated table HTML
     */
    public static function table(html_table $table): string
    {
        return core_html_writer::table($table);
    }

    /**
     * Generates an HTML label tag.
     *
     * @param string      $text       Label text
     * @param null|string $for        ID of the associated input
     * @param bool        $colonize   whether to add a colon after the text
     * @param array       $attributes Optional tag attributes
     *
     * @return string the generated label tag
     */
    public static function label(string $text, ?string $for, bool $colonize = true, array $attributes = []): string
    {
        return core_html_writer::label($text, $for, $colonize, $attributes);
    }

    /**
     * Generates an HTML div tag.
     *
     * @param string     $content    The div contents
     * @param string     $class      The CSS class
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated div tag
     */
    public static function div(string $content, string $class = '', ?array $attributes = null): string
    {
        return core_html_writer::div($content, $class, $attributes);
    }

    /**
     * Generates an opening HTML div tag.
     *
     * @param string     $class      The CSS class
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated opening div tag
     */
    public static function startDiv(string $class = '', ?array $attributes = null): string
    {
        return core_html_writer::start_div($class, $attributes);
    }

    /**
     * Generates a closing HTML div tag.
     *
     * @return string the generated closing div tag
     */
    public static function endDiv(): string
    {
        return core_html_writer::end_div();
    }

    /**
     * Generates an HTML span tag.
     *
     * @param string     $content    The span contents
     * @param string     $class      The CSS class
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated span tag
     */
    public static function span(string $content, string $class = '', ?array $attributes = null): string
    {
        return core_html_writer::span($content, $class, $attributes);
    }

    /**
     * Generates an opening HTML span tag.
     *
     * @param string     $class      The CSS class
     * @param null|array $attributes Optional tag attributes
     *
     * @return string the generated opening span tag
     */
    public static function startSpan(string $class = '', ?array $attributes = null): string
    {
        return core_html_writer::start_span($class, $attributes);
    }

    /**
     * Generates a closing HTML span tag.
     *
     * @return string the generated closing span tag
     */
    public static function endSpan(): string
    {
        return core_html_writer::end_span();
    }

    /**
     * Converts HTML content to plain text.
     *
     * @param string $html the HTML content
     *
     * @return string the converted plain text
     */
    public static function htmlToText(string $html): string
    {
        return html_to_text($html);
    }
}
