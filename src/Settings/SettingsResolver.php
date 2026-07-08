<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Settings;

use admin_settingpage;
use Middag\Moodle\Support\LangSupport;

/**
 * Translates framework settings to Moodle admin_setting instances.
 *
 * Key naming is delegated to the injected {@see SettingsNamingPolicy}
 * (default: the MIDDAG `mdg_` prefix) so a client plugin on the same host
 * can run its own resolver without colliding with the product's keys.
 *
 * @internal
 */
class SettingsResolver
{
    public function __construct(
        private readonly SettingsNamingPolicy $policy = new SettingsNamingPolicy(),
    ) {}

    /**
     * Resolve a setting name to its canonical config key under this
     * resolver's naming policy.
     *
     * Convention: {prefix}{extension}_{name} (ADR-311).
     *
     * @param string $name      the raw setting name declared in the typed DSL
     * @param string $extension the extension slug (e.g. "core", "ecommerce")
     *
     * @return string the canonical config key
     */
    public function configKey(string $name, string $extension): string
    {
        return $this->policy->configKey($name, $extension);
    }

    /**
     * Default-policy convenience for the static support seams
     * (`SettingsSupport`, `ThemeSupport`) and DSL fallbacks. Policy-aware
     * callers construct the resolver with their policy and use
     * {@see self::configKey()} instead.
     *
     * @param string $name      the raw setting name declared in the typed DSL
     * @param string $extension the extension slug (e.g. "core", "ecommerce")
     *
     * @return string the canonical config key under the DEFAULT policy
     */
    public static function resolveConfigKey(string $name, string $extension): string
    {
        return (new SettingsNamingPolicy())->configKey($name, $extension);
    }

    /**
     * Resolve extension settings into Moodle admin_settingpage objects.
     *
     * Iterates the settings array. For each page instance, creates an
     * admin_settingpage and adds its child settings as admin_setting objects.
     *
     * @param string $extension_name the extension identifier
     * @param array  $settings       array of page and/or setting instances
     * @param string $plugin_name    the plugin frankenstyle name
     *
     * @return admin_settingpage[] array of admin_settingpage objects
     */
    public function resolveExtensionPages(
        string $extension_name,
        array $settings,
        string $plugin_name,
    ): array {
        $pages = [];

        foreach ($settings as $item) {
            if ($item instanceof page) {
                $admin_page = new admin_settingpage(
                    $item->resolve_id($extension_name, $plugin_name),
                    LangSupport::getString($item->resolve_label($extension_name), $plugin_name),
                );

                foreach ($item->settings as $child) {
                    if ($child instanceof AbstractSetting) {
                        $child->useNamingPolicy($this->policy);
                        $admin_page->add($child->toMoodleSetting($extension_name, $plugin_name));
                    }
                }

                $pages[] = $admin_page;
            }
        }

        return $pages;
    }
}
