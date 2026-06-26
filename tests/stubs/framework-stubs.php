<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Framework\Bus\Contract;

if (!interface_exists(UserContextResolverInterface::class)) {
    interface UserContextResolverInterface
    {
        public function getCurrentUserId(): ?int;
    }
}

namespace Middag\Framework\Contract;

if (!interface_exists(ConfigResolverInterface::class)) {
    interface ConfigResolverInterface
    {
        public function get(string $key, ?string $entitySlug = null, string $default = ''): string;

        public function has(string $key, ?string $entitySlug = null): bool;
    }
}
