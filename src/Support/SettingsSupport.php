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
use InvalidArgumentException;
use Middag\Moodle\Settings\SettingsResolver;
use ReflectionEnum;

/**
 * Typed access to admin settings via canonical config keys (ADR-311).
 *
 * Accepts a string-backed enum whose class name follows the convention
 * `{slug}_config` — the slug is extracted automatically to resolve the
 * canonical key via `SettingsResolver::resolve_config_key()`. PascalCase
 * spellings (e.g. `FrameworkConfig`) are normalised to snake_case before
 * the slug is derived; an enum whose name cannot be mapped onto
 * `{slug}_config` is rejected instead of silently resolving a dead key.
 *
 * Usage:
 *   settings_support::get(core_config::debugmode)          // read
 *   settings_support::set(core_config::debugmode, '2')     // write
 *   settings_support::get(ecommerce_config::sendfromwoo)   // cross-extension
 *
 * @api
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
            SettingsResolver::resolveConfigKey($name, $extension),
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
            SettingsResolver::resolveConfigKey($name, $extension),
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
            SettingsResolver::resolveConfigKey($name, $extension),
        );
    }

    /**
     * Extract setting name and extension slug from a backed enum.
     *
     * Convention: enum class `{slug}_config` → extension slug is `{slug}`.
     * PascalCase spellings are normalised (`FrameworkConfig` → `framework_config`)
     * before the suffix check.
     *
     * @return array{0: string, 1: string} [name, extension]
     *
     * @throws InvalidArgumentException when the enum name cannot be mapped onto `{slug}_config`
     */
    private static function resolve(BackedEnum $key): array
    {
        $name = (string) $key->value;

        // Extract short class name: "{component}\extensions\core\core_config" → "core_config".
        $class = (new ReflectionEnum($key))->getShortName();

        // Normalise PascalCase/camelCase spellings to snake_case ("FrameworkConfig"
        // → "framework_config") so both spellings derive the same slug.
        $normalized = strtolower(
            (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '_', $class),
        );

        if (!str_ends_with($normalized, '_config')) {
            throw new InvalidArgumentException(sprintf(
                'Settings enum %s does not follow the {slug}_config naming convention; refusing to derive a config key for case "%s".',
                $key::class,
                $key->name,
            ));
        }

        // Strip "_config" suffix to get the extension slug.
        $extension = substr($normalized, 0, -7);

        if ($extension === 'framework') {
            $extension = 'core';
        }

        return [$name, $extension];
    }
}
