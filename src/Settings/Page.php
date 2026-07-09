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

use Middag\Moodle\Config\ComponentContext;

/**
 * Settings page grouper (rendered as tab in admin UI).
 *
 * @api
 */
final readonly class Page
{
    public function __construct(
        public string $name,
        public ?string $id = null,
        public ?string $label = null,
        public array $settings = [],
    ) {}

    /**
     * Resolve the Moodle admin page identifier.
     *
     * When an explicit id is set, it is used directly. Otherwise the
     * resolver builds a conventional id from extension name + page name.
     */
    public function resolveId(string $extension_name, ?string $plugin_name = null): string
    {
        if ($this->id !== null) {
            return $this->id;
        }

        $plugin_name ??= ComponentContext::name();

        // Extract short name: "local_example" → "example", "local_yourplugin" → "yourplugin".
        $short = str_contains($plugin_name, '_')
            ? substr($plugin_name, (int) strpos($plugin_name, '_') + 1)
            : $plugin_name;

        return $short . '_' . $extension_name . '_' . $this->name;
    }

    /**
     * Resolve the lang string key for the page label.
     */
    public function resolveLabel(string $extension): string
    {
        if ($this->label !== null) {
            return $this->label;
        }

        if ($extension === 'core') {
            return 'settings_page_' . $this->name;
        }

        return 'settings_page_' . $extension . '_' . $this->name;
    }
}
