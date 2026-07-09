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
 * Per-area Moodle stubs for Middag\Moodle\Runtime\Kernel lifecycle coverage.
 *
 * Kernel::ensureAutoload() resolves the consumer plugin's autoload function name
 * (ComponentContext::autoloadFunction(), 'local_example_autoload' for the default
 * test component) and invokes it when it already exists — the "already loaded"
 * branch that skips the host-lib.php require. The test runtime has no real
 * consumer plugin, so this stand-in provides that function. It only records the
 * invocation count in $GLOBALS['__middag_test_local_example_autoload_calls'] so
 * the coverage test can assert ensureAutoload() called it. Guarded with
 * !function_exists so the file stays additive, order-independent, and
 * collision-free with parallel writers.
 */

if (!function_exists('local_example_autoload')) {
    function local_example_autoload(): void
    {
        $GLOBALS['__middag_test_local_example_autoload_calls']
            = ($GLOBALS['__middag_test_local_example_autoload_calls'] ?? 0) + 1;
    }
}
