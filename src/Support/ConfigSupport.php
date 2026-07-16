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

use Middag\Moodle\Config\ComponentContext;
use Middag\Moodle\Domain\Platform\SiteInfoDto;
use Middag\Moodle\Shared\Enum\TextFormat;
use Middag\Moodle\Shared\Util\Debug;
use Throwable;

/**
 * Configuration utility wrapper for Moodle's config API.
 *
 * Centralizes access to get_config/set_config/unset_config for the plugin as a
 * deliberate SAFE FACADE: every read/write method catches Throwable, routes it
 * to {@see Debug::traceException()} (emitted only when the runtime debug mode is
 * enabled) and returns a predictable failure value. By design, config access
 * never propagates a host exception into caller flow.
 *
 * CONTRACT (amends QG-MDL-06): the mixed getters DO distinguish the two failure
 * modes. Moodle's get_config() returns `false` for an absent key; the getters
 * normalise that to `null`, and reserve `false` for a genuine host read failure
 * (the Throwable path). So a single-key read yields the value when present,
 * `null` when the key was never set, and `false` only on error — letting a
 * caller safely write `ConfigSupport::get($k) ?? $default`. The boolean mutators
 * return `false` on failure. (The prior QG-MDL-06 rule collapsed both absent and
 * error into `false`; it was superseded because the normalisation restores the
 * distinction its own consumers assumed via `?? default`.)
 *
 * @api
 */
class ConfigSupport
{
    /**
     * Component name used for plugin configuration.
     *
     * Resolved from the composition-root {@see ComponentContext} seam instead of
     * a hard-coded plugin constant, keeping the adapter product-agnostic.
     */
    public static function pluginName(): string
    {
        return ComponentContext::name();
    }

    /**
     * Retrieves a configuration value for this plugin.
     *
     * @param null|string $name the config key name
     *
     * @return mixed the value if found, null if not set, or false on error
     */
    public static function get(?string $name = null): mixed
    {
        try {
            if (is_null($name)) {
                return get_config(self::pluginName());
            }

            $value = get_config(self::pluginName(), $name);

            // Moodle's get_config() yields false (never null) for an absent
            // key. Normalise that to null so an absent key is distinguishable
            // from a read failure (which returns false via the catch below),
            // matching the documented contract (see class docblock).
            return $value === false ? null : $value;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Retrieves configuration for any component or a single named key.
     *
     * @param string      $plugin The component name (e.g., 'local_example' or 'core').
     * @param null|string $name   Optional key name. If null, returns stdClass with all settings for the component.
     *
     * @return mixed stdClass for all settings, a single value, null if not set, or false on error
     */
    public static function getConfig(string $plugin, ?string $name = null): mixed
    {
        try {
            $value = get_config($plugin, $name);

            // See get(): normalise the host's false-for-absent into null so
            // callers can tell an unset key (null) from a read failure (false).
            return $value === false ? null : $value;
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Sets a configuration value for a specific plugin or component.
     *
     * @param string      $name   the config key name
     * @param mixed       $value  The value to set. Will be converted to string by Moodle.
     * @param null|string $plugin Component name; defaults to this plugin when null
     *
     * @return bool True on success, false on failure
     */
    public static function setConfig(string $name, mixed $value, ?string $plugin = null): bool
    {
        try {
            return set_config($name, $value, $plugin ?? self::pluginName());
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Unsets (removes) a configuration value.
     *
     * @param string      $name   the config key name to remove
     * @param null|string $plugin Component name; defaults to this plugin when null
     *
     * @return bool True on success, false on failure
     */
    public static function unsetConfig(string $name, ?string $plugin = null): bool
    {
        try {
            return unset_config($name, $plugin ?? self::pluginName());
        } catch (Throwable $throwable) {
            Debug::traceException($throwable);

            return false;
        }
    }

    /**
     * Retrieves a value from the global Moodle configuration $CFG.
     *
     * @param string $name the property name
     *
     * @return mixed the value or null if not set
     */
    public static function getGlobal(string $name): mixed
    {
        global $CFG;

        return $CFG->{$name} ?? null;
    }

    /**
     * Returns site information as a typed DTO.
     */
    public static function getSiteInfo(): SiteInfoDto
    {
        global $SITE;

        return new SiteInfoDto(
            id: (int) $SITE->id,
            fullname: (string) ($SITE->fullname ?? ''),
            shortname: (string) ($SITE->shortname ?? ''),
            summary: (string) ($SITE->summary ?? ''),
            summaryformat: TextFormat::resolve((int) ($SITE->summaryformat ?? 1)),
            format: (string) ($SITE->format ?? ''),
            lang: (string) ($SITE->lang ?? ''),
            theme: (string) ($SITE->theme ?? ''),
            timecreated: (int) ($SITE->timecreated ?? 0),
            timemodified: (int) ($SITE->timemodified ?? 0),
        );
    }

    /**
     * Retrieves the SITEID constant value.
     *
     * @return int Site ID
     */
    public static function getSiteId(): int
    {
        return (int) SITEID;
    }
}
