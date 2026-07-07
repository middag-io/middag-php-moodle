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

use core\lang_string;
use core_string_manager;
use Exception;
use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Shared\Util\Debug;

/**
 * Utility functions for Moodle language strings.
 *
 * @internal
 */
class LangSupport
{
    /**
     * Retrieves a localized string.
     *
     * @param string $identifier the string identifier
     * @param mixed  $a          variable to replace in the string
     * @param bool   $lazyload   whether to return a lang_string object
     * @param string $component  Component name
     *
     * @return lang_string|string the localized string or object
     */
    public static function get(string $identifier, mixed $a = null, bool $lazyload = false, ?string $component = null): lang_string|string
    {
        $component ??= ComponentContext::name();

        try {
            if ($lazyload) {
                return new lang_string($identifier, $component, $a);
            }

            return get_string($identifier, $component, $a, $lazyload);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return sprintf('[[%s]]', $identifier);
        }
    }

    /**
     * Retrieves a localized string (alias for get).
     *
     * @param string $identifier the string identifier
     * @param string $component  Component name
     * @param mixed  $a          variable to replace in the string
     * @param bool   $lazyload   whether to return a lang_string object
     *
     * @return lang_string|string the localized string or object
     */
    public static function getString(string $identifier, string $component = '', mixed $a = null, bool $lazyload = false): lang_string|string
    {
        try {
            return self::get($identifier, $a, $lazyload, $component);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return sprintf('[[%s]]', $identifier);
        }
    }

    /**
     * Retrieves a localized string if it exists, otherwise returns the identifier.
     *
     * @param string $identifier the string identifier
     * @param string $component  Component name
     * @param mixed  $a          variable to replace in the string
     * @param bool   $lazyload   whether to return a lang_string object
     *
     * @return lang_string|string the localized string or the identifier
     */
    public static function getStringOrIdentifier(string $identifier, string $component = '', mixed $a = null, bool $lazyload = false): lang_string|string
    {
        try {
            if (self::stringExists($identifier, $component)) {
                return self::get($identifier, $a, $lazyload, $component);
            }

            return $identifier;
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return $identifier;
        }
    }

    /**
     * Checks if a language string exists in the component.
     *
     * @param string $identifier Language string identifier
     * @param string $component  Component name (default: local_example)
     *
     * @return bool True if string exists, false otherwise
     */
    public static function stringExists(string $identifier, ?string $component = null): bool
    {
        $component ??= ComponentContext::name();

        try {
            $stringmanager = get_string_manager();

            if (!$stringmanager instanceof core_string_manager) {
                return false;
            }

            return $stringmanager->string_exists($identifier, $component);
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return false;
        }
    }

    /**
     * Get the current language code.
     *
     * @return string language code (e.g. 'en', 'pt_br')
     */
    public static function currentLanguage(): string
    {
        try {
            return current_language();
        } catch (Exception $exception) {
            Debug::traceException($exception);

            return 'en';
        }
    }

    /**
     * Retrieves the plugin name from language strings.
     *
     * @return string The plugin name
     */
    public static function pluginname(): string
    {
        return self::get('pluginname');
    }
}
