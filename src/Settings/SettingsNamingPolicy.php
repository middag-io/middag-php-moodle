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
 * The canonical key shape is `{prefix}{extension}_{name}` (ADR-311). This is
 * an OSS, brand-agnostic library, so the shipped default is a neutral,
 * unbranded prefix — it only marks a key as "produced by an unconfigured
 * consumer of this policy", never any specific product's identity. A real
 * product built on this adapter (e.g. the MIDDAG product, via
 * `middag-io/core`) injects its own real prefix explicitly at its
 * composition root; a third-party plugin sharing the same Moodle host does
 * the same with its own policy (e.g. `new SettingsNamingPolicy('clientx_')`)
 * into {@see SettingsResolver} / the settings supports so its config keys
 * never collide with another consumer's (see MDL-021).
 *
 * @api
 */
final readonly class SettingsNamingPolicy
{
    /** @var string Neutral library default prefix for framework config keys; a product consumer overrides this explicitly (see MDL-021). */
    public const DEFAULT_PREFIX = 'mdglib_';

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
