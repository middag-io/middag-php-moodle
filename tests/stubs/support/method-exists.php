<?php

declare(strict_types=1);

/**
 * middag-io/moodle — MIDDAG Moodle adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

/*
 * Namespaced method_exists() for the Support wrappers that pick a code path by
 * Moodle version (e.g. UserSupport: \core\user::create_user() on 5.3+, the
 * legacy user_create_user() before). An unqualified method_exists() call in
 * Middag\Moodle\Support resolves to this function first, so a test can make
 * one "Class::method" probe answer false (or true) through
 * $GLOBALS['__middag_test_method_exists'] and reach the older branch with the
 * same stubs. Every other probe falls through to the real method_exists().
 * Loaded from the bootstrap, before any Support call, so PHP never caches the
 * global function for these call sites.
 */

namespace Middag\Moodle\Support;

if (!\function_exists(__NAMESPACE__ . '\method_exists')) {
    function method_exists(object|string $object_or_class, string $method): bool
    {
        // Only class-string probes can be forced; object probes always run for real.
        $forced = \is_string($object_or_class)
            ? $GLOBALS['__middag_test_method_exists'][ltrim($object_or_class, '\\') . '::' . $method] ?? null
            : null;

        return \is_bool($forced) ? $forced : \method_exists($object_or_class, $method);
    }
}
