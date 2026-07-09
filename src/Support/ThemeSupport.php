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

use Middag\Moodle\Settings\SettingsNamingPolicy;

/**
 * Theme Bridge Support.
 *
 * Bridges Moodle theme settings (brand color, etc.) into the MIDDAG design system.
 * Enables the framework to inherit the active Moodle theme's visual identity.
 *
 * @api
 */
class ThemeSupport
{
    /**
     * Get the brand color from the active Moodle theme.
     *
     * @return null|string Hex color (e.g. '#0f6cbf') or null if not set
     */
    public static function getBrandColor(): ?string
    {
        global $PAGE;

        if (!isset($PAGE->theme->settings->brandcolor)) {
            return null;
        }

        $color = $PAGE->theme->settings->brandcolor;

        return is_string($color) && $color !== '' ? $color : null;
    }

    /**
     * Check if theme color inheritance is enabled.
     *
     * Reads the `inherit_theme_colors` setting from core extension config
     * (canonical key under the default policy: mdg_core_inherit_theme_colors).
     *
     * @param null|SettingsNamingPolicy $policy naming policy for the config key (null = MIDDAG default `mdg_`)
     *
     * @return bool
     */
    public static function isInheritanceEnabled(?SettingsNamingPolicy $policy = null): bool
    {
        return (bool) ConfigSupport::get(
            ($policy ?? new SettingsNamingPolicy())->configKey('inherit_theme_colors', 'core'),
        );
    }

    /**
     * Get the CSS custom property injection string.
     *
     * Returns the CSS rule to inject as inline style, or null if inheritance is disabled
     * or no brand color is available.
     *
     * @return null|string CSS rule like ":root { --middag-brand: #0f6cbf; }"
     */
    public static function getCssInjection(): ?string
    {
        if (!self::isInheritanceEnabled()) {
            return null;
        }

        $brand = self::getBrandColor();
        if ($brand === null) {
            return null;
        }

        return sprintf(':root { --middag-brand: %s; }', $brand);
    }

    /**
     * Build theme data for Inertia shared props.
     *
     * @return array{brandColor: ?string, inherit: bool}
     */
    public static function buildTheme(): array
    {
        return [
            'brandColor' => self::isInheritanceEnabled() ? self::getBrandColor() : null,
            'inherit' => self::isInheritanceEnabled(),
        ];
    }
}
