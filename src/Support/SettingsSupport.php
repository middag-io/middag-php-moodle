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

use BackedEnum;
use Middag\Moodle\Settings\SettingsResolver as settings_resolver;
use ReflectionEnum;

/**
 * Typed access to admin settings via canonical config keys (ADR-311).
 *
 * Accepts a string-backed enum whose class name follows the convention
 * `{slug}_config` — the slug is extracted automatically to resolve the
 * canonical key via `settings_resolver::resolve_config_key()`.
 *
 * Usage:
 *   settings_support::get(core_config::debugmode)          // read
 *   settings_support::set(core_config::debugmode, '2')     // write
 *   settings_support::get(ecommerce_config::sendfromwoo)   // cross-extension
 *
 * @internal
 */
class SettingsSupport
{
    /**
     * Read a setting value.
     *
     * @param BackedEnum $key a string-backed enum case from a {slug}_config enum
     *
     * @return mixed the stored value, or false if not found
     */
    public static function get(BackedEnum $key): mixed
    {
        [$name, $extension] = self::resolve($key);

        return ConfigSupport::get(
            settings_resolver::resolveConfigKey($name, $extension),
        );
    }

    /**
     * Write a setting value.
     *
     * @param BackedEnum $key   a string-backed enum case
     * @param mixed      $value the value to store
     *
     * @return bool true on success
     */
    public static function set(BackedEnum $key, mixed $value): bool
    {
        [$name, $extension] = self::resolve($key);

        return ConfigSupport::setConfig(
            settings_resolver::resolveConfigKey($name, $extension),
            $value,
        );
    }

    /**
     * Remove a setting value.
     *
     * @param BackedEnum $key a string-backed enum case
     *
     * @return bool true on success
     */
    public static function unset(BackedEnum $key): bool
    {
        [$name, $extension] = self::resolve($key);

        return ConfigSupport::unsetConfig(
            settings_resolver::resolveConfigKey($name, $extension),
        );
    }

    /**
     * Extract setting name and extension slug from a backed enum.
     *
     * Convention: enum class `{slug}_config` → extension slug is `{slug}`.
     *
     * @return array{0: string, 1: string} [name, extension]
     */
    private static function resolve(BackedEnum $key): array
    {
        $name = (string) $key->value;

        // Extract short class name: "{component}\extensions\core\core_config" → "core_config".
        $class = (new ReflectionEnum($key))->getShortName();

        // Strip "_config" suffix to get the extension slug.
        $extension = str_ends_with($class, '_config')
            ? substr($class, 0, -7)
            : $class;

        if ($extension === 'framework') {
            $extension = 'core';
        }

        return [$name, $extension];
    }
}
