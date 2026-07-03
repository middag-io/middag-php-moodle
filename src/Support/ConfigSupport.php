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
use Middag\Moodle\Domain\Platform\SiteInfoDto as site_info_dto;
use Middag\Moodle\Shared\Enum\TextFormat as text_format;
use Middag\Moodle\Shared\Util\Debug as debug;
use Throwable;

/**
 * Configuration utility wrapper for Moodle's config API.
 *
 * This class centralizes access to get_config/set_config/unset_config for the plugin,
 * providing a safe façade that catches exceptions and returns predictable values.
 *
 * @internal
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

            return get_config(self::pluginName(), $name);
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

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
            return get_config($plugin, $name);
        } catch (Throwable $throwable) {
            debug::traceException($throwable);

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
            debug::traceException($throwable);

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
            debug::traceException($throwable);

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
    public static function getSiteInfo(): site_info_dto
    {
        global $SITE;

        return new site_info_dto(
            id: (int) $SITE->id,
            fullname: (string) ($SITE->fullname ?? ''),
            shortname: (string) ($SITE->shortname ?? ''),
            summary: (string) ($SITE->summary ?? ''),
            summaryformat: text_format::resolve((int) ($SITE->summaryformat ?? 1)),
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
