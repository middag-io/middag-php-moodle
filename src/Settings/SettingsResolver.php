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
 * @internal
 */
class SettingsResolver
{
    /** @var string Canonical prefix for all framework config keys. */
    private const PREFIX = 'mdg_';

    /**
     * Resolve a setting name to its canonical config key.
     *
     * Convention: mdg_{extension}_{name} (ADR-311).
     *
     * @param string $name      the raw setting name declared in the typed DSL
     * @param string $extension the extension slug (e.g. "core", "ecommerce")
     *
     * @return string the canonical config key
     */
    public static function resolveConfigKey(string $name, string $extension): string
    {
        $prefix = self::PREFIX . $extension . '_';

        if (str_starts_with($name, $prefix)) {
            return $name;
        }

        return $prefix . $name;
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
                    if ($child instanceof setting) {
                        $admin_page->add($child->toMoodleSetting($extension_name, $plugin_name));
                    }
                }

                $pages[] = $admin_page;
            }
        }

        return $pages;
    }
}
