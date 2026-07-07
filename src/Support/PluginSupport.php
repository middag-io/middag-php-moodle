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

use core\component as core_component;
use core\exception\moodle_exception;
use core\plugin_manager;
use core\plugin_manager as core_plugin_manager;
use Middag\Moodle\Domain\Platform\Frankenstyle;
use Middag\Moodle\Domain\Platform\PluginDto;

/**
 * Unified wrapper for Moodle plugin APIs.
 *
 * Provides stable access to plugin metadata across Moodle versions,
 * encapsulating differences between:
 *   - core_component vs \core\component
 *   - core_plugin_manager vs plugin_manager
 *
 * Returns DTOs instead of core plugininfo objects to ensure stability
 * and avoid exposing Moodle internals directly.
 *
 * @api
 */
class PluginSupport
{
    /** @var core_plugin_manager|plugin_manager The plugin manager instance. */
    private $manager;

    /** @var class-string FQCN for component helper. */
    private readonly string $componentClass;

    /**
     * Initializes the plugin manager and component class based on Moodle version.
     */
    public function __construct()
    {
        $this->manager = core_plugin_manager::instance();
        $this->componentClass = core_component::class;
    }

    /**
     * Retrieves all plugin types installed in Moodle.
     *
     * @return array<string, string> Map of plugin type to directory
     */
    public function getPluginTypes(): array
    {
        return $this->componentClass::get_plugin_types();
    }

    /**
     * Retrieves list of plugins of a specific type.
     *
     * @param string $type The plugin type (e.g., 'mod', 'local').
     *
     * @return array<string, string> Map of plugin name to directory
     */
    public function getPluginsOfType(string $type): array
    {
        return $this->componentClass::get_plugin_list($type);
    }

    /**
     * Checks if a plugin exists on disk.
     *
     * @param string $type   Plugin type
     * @param string $plugin Plugin name
     *
     * @return bool True if plugin exists, false otherwise
     */
    public function pluginExists(string $type, string $plugin): bool
    {
        return isset($this->getPluginsOfType($type)[$plugin]);
    }

    /**
     * Retrieves the plugin directory path on disk.
     *
     * @param string $type   Plugin type
     * @param string $plugin Plugin name
     *
     * @return string the plugin directory path
     *
     * @throws moodle_exception if plugin is not found
     */
    public function getPluginDirectory(string $type, string $plugin): string
    {
        $list = $this->getPluginsOfType($type);

        if (!isset($list[$plugin])) {
            throw new moodle_exception(sprintf('Plugin %s_%s not found.', $type, $plugin));
        }

        return $list[$plugin];
    }

    /**
     * Retrieves the display name of a plugin.
     *
     * @param string $type   Plugin type
     * @param string $plugin Plugin name
     *
     * @return string the plugin display name
     */
    public function pluginDisplayname(string $type, string $plugin): string
    {
        return $this->manager->plugin_name(sprintf('%s_%s', $type, $plugin));
    }

    /**
     * Retrieves a DTO containing complete plugin metadata.
     *
     * @param string $type   Plugin type
     * @param string $plugin Plugin name
     *
     * @return PluginDto the plugin metadata DTO
     *
     * @throws moodle_exception if plugin info cannot be retrieved
     */
    public function getPluginInfo(string $type, string $plugin): PluginDto
    {
        $component = sprintf('%s_%s', $type, $plugin);
        $info = $this->manager->get_plugin_info($component);

        if ($info === null) {
            throw new moodle_exception('Unknown plugin: ' . $component);
        }

        // normalize values
        $rootdir = $info->rootdir ?: null;
        $displayname = $info->displayname ?? $component;
        $source = $info->source ?? null;
        $versiondisk = $info->versiondisk ?? null;
        $versiondb = $info->versiondb ?? null;
        $versionrequires = $info->versionrequires ?? null;
        $release = $info->release ?? null;
        $dependencies = $info->dependencies ?? null;
        $supported = $info->supported ?? null;
        $incompatible = $info->incompatible ?? null;
        $status = method_exists($info, 'get_status') ? $info->get_status() : null;

        return new PluginDto(
            type: $type,
            name: $plugin,
            component: $component,
            rootdir: $rootdir,
            displayname: $displayname,
            source: $source,
            versiondisk: $versiondisk,
            versiondb: $versiondb,
            versionrequires: $versionrequires,
            dependencies: $dependencies,
            enabled: $info->is_enabled(),
            release: $release,
            supported: $supported,
            incompatible: $incompatible,
            status: $status,
        );
    }

    /**
     * Retrieves all plugins as DTOs, grouped by type.
     *
     * @return PluginDto grouped list of plugin DTOs
     */
    public function getAllPlugins(): array
    {
        $out = [];

        foreach (array_keys($this->getPluginTypes()) as $type) {
            $out[$type] = [];

            foreach (array_keys($this->getPluginsOfType($type)) as $plugin) {
                $out[$type][] = $this->getPluginInfo($type, $plugin);
            }
        }

        return $out;
    }

    /**
     * Retrieves all enabled plugins as DTOs.
     *
     * @return PluginDto[] list of enabled plugin DTOs
     */
    public function getEnabledPlugins(): array
    {
        $result = [];

        foreach (array_keys($this->getPluginTypes()) as $type) {
            foreach (array_keys($this->getPluginsOfType($type)) as $plugin) {
                $dto = $this->getPluginInfo($type, $plugin);

                if ($dto->enabled) {
                    $result[] = $dto;
                }
            }
        }

        return $result;
    }

    /**
     * Checks if a specific plugin is enabled.
     *
     * @param string $type   Plugin type
     * @param string $plugin Plugin name
     *
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled(string $type, string $plugin): bool
    {
        $info = $this->manager->get_plugin_info(sprintf('%s_%s', $type, $plugin));

        return $info && $info->is_enabled();
    }

    /**
     * Check if a plugin exists by Frankenstyle component name.
     */
    public function pluginExistsByComponent(Frankenstyle $component): bool
    {
        return $this->pluginExists($component->type, $component->name);
    }

    /**
     * Get plugin info by Frankenstyle component name.
     */
    public function getPluginInfoByComponent(Frankenstyle $component): PluginDto
    {
        return $this->getPluginInfo($component->type, $component->name);
    }

    /**
     * Check if a plugin is enabled by Frankenstyle component name.
     */
    public function isEnabledByComponent(Frankenstyle $component): bool
    {
        return $this->isEnabled($component->type, $component->name);
    }
}
