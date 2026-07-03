<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Moodle\Config;

use Middag\Framework\Kernel\Contract\ConfigResolverInterface;
use Middag\Moodle\Support\ConfigSupport;

/**
 * Moodle configuration resolver.
 *
 * Resolves config values from Moodle's get_config() API.
 * The entitySlug parameter is ignored (Moodle is single-tenant per instance).
 */
final class MoodleConfigResolver implements ConfigResolverInterface
{
    public function get(string $key, ?string $entitySlug = null, string $default = ''): string
    {
        $value = ConfigSupport::get($key);

        if (in_array($value, [false, null, ''], true)) {
            return $default;
        }

        return (string) $value;
    }

    public function has(string $key, ?string $entitySlug = null): bool
    {
        $value = ConfigSupport::get($key);

        return $value !== false && $value !== null;
    }
}
