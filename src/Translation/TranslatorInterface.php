<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Translation;

/**
 * Interface for translation services.
 * Decouples the framework from Moodle's get_string() global.
 *
 * @api
 */
interface TranslatorInterface
{
    /**
     * Translate a string identifier.
     *
     * @param string      $identifier The string key
     * @param null|string $component  The component (plugin) name
     * @param mixed       $args       Arguments to inject into the string
     *
     * @return string The translated string
     */
    public function trans(string $identifier, ?string $component = null, mixed $args = null): string;

    /**
     * Check if a translation exists.
     *
     * @param string      $identifier
     * @param null|string $component
     *
     * @return bool
     */
    public function has(string $identifier, ?string $component = null): bool;
}
