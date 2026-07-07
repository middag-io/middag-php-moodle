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

use admin_setting;
use admin_setting_configselect;
use Closure;
use Middag\Moodle\Support\LangSupport;

/**
 * Dropdown select setting.
 *
 * @api
 */
final class Select extends Setting
{
    public function __construct(
        string $name,
        ?string $label = null,
        ?string $description = null,
        public readonly array $options = [],
        mixed $default = null,
        public readonly Closure|string|null $updatedCallback = null,
    ) {
        parent::__construct($name, $default, $label, $description);
    }

    public function toMoodleSetting(string $extension, string $plugin): admin_setting
    {
        $setting = new admin_setting_configselect(
            $plugin . '/' . $this->resolveConfigName($extension),
            LangSupport::getString($this->resolveLabel($extension, $plugin), $plugin),
            LangSupport::getString($this->resolveDescription($extension, $plugin), $plugin),
            $this->default,
            $this->resolveOptions($extension, $plugin),
        );

        if ($this->updatedCallback !== null) {
            $setting->set_updatedcallback($this->updatedCallback);
        }

        return $setting;
    }

    /**
     * Resolve options array for Moodle admin_setting_configselect.
     *
     * Indexed arrays auto-resolve lang keys from the base label key.
     * Associative arrays are used as-is (key = value, value = display label).
     */
    private function resolveOptions(string $extension, string $plugin): array
    {
        $resolved = [];
        $base_key = $this->resolveLabel($extension, $plugin);

        foreach ($this->options as $key => $value) {
            if (is_int($key) && is_string($value) && preg_match('/^[a-z][a-z0-9_]*$/', $value)) {
                // Array of slug values — auto-resolve lang keys (e.g. ['off', 'normal'] → debugmode_off, debugmode_normal).
                $resolved[$value] = get_string($base_key . '_' . $value, $plugin);
            } else {
                // Associative or pre-resolved — key is value, value is display label.
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }
}
