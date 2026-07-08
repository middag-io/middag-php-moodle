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

/**
 * Naming policy for host-facing config keys.
 *
 * The canonical key shape is `{prefix}{extension}_{name}` (ADR-311); the
 * MIDDAG default prefix is `mdg_`. A client plugin sharing the same Moodle
 * host injects its own policy (e.g. `new SettingsNamingPolicy('clientx_')`)
 * into {@see SettingsResolver} / the settings supports so its config keys
 * never collide with the main MIDDAG product's.
 *
 * @api
 */
final readonly class SettingsNamingPolicy
{
    /** @var string MIDDAG default prefix for framework config keys. */
    public const DEFAULT_PREFIX = 'mdg_';

    public function __construct(
        public string $prefix = self::DEFAULT_PREFIX,
    ) {}

    /**
     * Resolve a setting name to its canonical config key.
     *
     * Names already carrying the `{prefix}{extension}_` prefix pass through
     * unchanged.
     *
     * @param string $name      the raw setting name declared in the typed DSL
     * @param string $extension the extension slug (e.g. "core", "ecommerce")
     */
    public function configKey(string $name, string $extension): string
    {
        $prefix = $this->prefix . $extension . '_';

        if (str_starts_with($name, $prefix)) {
            return $name;
        }

        return $prefix . $name;
    }
}
